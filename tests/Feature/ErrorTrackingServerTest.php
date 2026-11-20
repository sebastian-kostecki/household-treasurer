<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ErrorTracking\SentryEventScrubber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use PDOException;
use RuntimeException;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\HttpClient\Response;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Stacktrace;
use Sentry\State\HubInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class ErrorTrackingServerTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET_AMOUNT = '1234,56';

    private const SECRET_QUERY = 'secret-query';

    /** @var list<string> */
    private array $payloads = [];

    private string|false $previousIgnoreArgs = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousIgnoreArgs = ini_set('zend.exception_ignore_args', '0');

        $this->payloads = [];
        $recorder = $this->recorder();

        config([
            'sentry.dsn' => 'http://publickey@bugsink.test:8000/1',
            'app.release' => 'test-release',
            'sentry.release' => 'test-release',
            'sentry.http_client' => $recorder,
        ]);

        $this->app->forgetInstance(HubInterface::class);
        $this->app->forgetInstance('sentry');
        SentrySdk::setCurrentHub($this->app->make(HubInterface::class));
    }

    protected function tearDown(): void
    {
        if ($this->previousIgnoreArgs !== false) {
            ini_set('zend.exception_ignore_args', $this->previousIgnoreArgs);
        }

        parent::tearDown();
    }

    public function test_an_exception_in_a_request_reaches_the_tracker_without_the_body_and_with_context_tags(): void
    {
        $user = User::factory()->create();

        Route::middleware('web')->post('/_test/boom', function (\Illuminate\Http\Request $request): never {
            (new ThrowingCollaborator)->explode((string) $request->input('amount'));
        })->name('test.boom');

        $this->actingAs($user)
            ->post('/_test/boom?q='.self::SECRET_QUERY, ['amount' => self::SECRET_AMOUNT])
            ->assertStatus(500);

        $event = $this->singleEvent();

        $this->assertSame(RuntimeException::class, $event['exception']['values'][0]['type']);
        $this->assertSame('test.boom', $event['tags']['route']);
        $this->assertSame((string) $user->id, $event['tags']['user_id']);
        $this->assertSame('test-release', $event['tags']['release']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $event['tags']['request_id']);

        $request = $event['request'] ?? [];
        $this->assertSame([], array_diff(array_keys($request), ['method', 'url']));
        $this->assertStringNotContainsString(self::SECRET_QUERY, (string) ($request['url'] ?? ''));

        $frames = $event['exception']['values'][0]['stacktrace']['frames'];
        $this->assertNotEmpty($frames);
        foreach ($frames as $frame) {
            $this->assertArrayNotHasKey('vars', $frame);
        }

        foreach ($this->payloads as $payload) {
            $this->assertStringNotContainsString(self::SECRET_AMOUNT, $payload);
            $this->assertStringNotContainsString(self::SECRET_QUERY, $payload);
        }
    }

    public function test_the_scrubber_keeps_only_method_and_url_path_of_a_request_and_empties_frame_vars(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'method' => 'POST',
            'url' => 'https://budget.kostecki.dev/expenses?q=secret-query',
            'query_string' => 'q=secret-query',
            'data' => ['amount' => self::SECRET_AMOUNT],
            'cookies' => ['session' => 'x'],
            'headers' => ['Cookie' => 'x'],
            'env' => ['REMOTE_ADDR' => '203.0.113.9'],
        ]);
        $event->setStacktrace(new Stacktrace([new Frame('f', 'file.php', 1, null, null, ['amount' => self::SECRET_AMOUNT])]));

        $scrubbed = SentryEventScrubber::beforeSend($event);

        $this->assertSame(['method' => 'POST', 'url' => 'https://budget.kostecki.dev/expenses'], $scrubbed->getRequest());
        $this->assertSame([], $scrubbed->getStacktrace()->getFrames()[0]->getVars());
    }

    public function test_the_scrubber_masks_the_relay_secret_in_the_request_path(): void
    {
        $event = Event::createEvent();
        $event->setRequest(['method' => 'POST', 'url' => 'https://budget.kostecki.dev/error-tracking/alerts/s3cret-value-123?x=1']);

        $scrubbed = SentryEventScrubber::beforeSend($event);

        $this->assertSame('https://budget.kostecki.dev/error-tracking/alerts/{secret}', $scrubbed->getRequest()['url']);
    }

    public function test_the_scrubber_redacts_database_exception_messages_but_keeps_the_sqlstate(): void
    {
        $message = 'SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key DETAIL: Key (description)=(Prąd '
            .self::SECRET_AMOUNT.') already exists. (Connection: pgsql, SQL: insert into t (amount) values ('
            .self::SECRET_AMOUNT.'))';
        $event = Event::createEvent();
        $event->setExceptions([
            new ExceptionDataBag(new QueryException('pgsql', 'insert into t (amount) values (?)', [self::SECRET_AMOUNT], new PDOException($message))),
            new ExceptionDataBag(new RuntimeException('Failed: '.self::SECRET_AMOUNT)),
        ]);
        $event->getExceptions()[0]->setValue($message);

        $scrubbed = SentryEventScrubber::beforeSend($event);

        $database = $scrubbed->getExceptions()[0];
        $this->assertSame('SQLSTATE[23505]: database error, details redacted', $database->getValue());
        $this->assertStringNotContainsString(self::SECRET_AMOUNT, json_encode($database->getValue()));
        $this->assertStringContainsString(self::SECRET_AMOUNT, $scrubbed->getExceptions()[1]->getValue());
    }

    public function test_the_trace_would_carry_the_amount_without_the_scrubber(): void
    {
        $this->assertStringContainsString(self::SECRET_AMOUNT, json_encode($this->throwFromCollaborator()->getTrace()));
    }

    public function test_an_exception_in_an_artisan_command_reaches_the_tracker(): void
    {
        Artisan::command('test:boom', function (): never {
            throw new RuntimeException('command failed');
        });

        $status = $this->app->make(Kernel::class)->handle(new ArrayInput(['command' => 'test:boom']), new BufferedOutput);

        $this->assertSame(1, $status);
        $this->assertSame('command failed', $this->singleEvent()['exception']['values'][0]['value']);
    }

    public function test_a_failing_queued_job_carries_the_request_id_of_the_request_that_dispatched_it(): void
    {
        config(['queue.default' => 'database']);

        $user = User::factory()->create();

        Route::middleware('web')->post('/_test/dispatch', function () {
            dispatch(new ThrowingJob);

            return response()->noContent();
        });

        $response = $this->actingAs($user)->post('/_test/dispatch')->assertNoContent();
        $requestId = $response->headers->get('X-Request-Id');
        $this->assertNotEmpty($requestId);
        $this->assertSame([], $this->payloads);

        Context::flush();

        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1]);

        $event = $this->singleEvent();

        $this->assertSame('queued job failed', $event['exception']['values'][0]['value']);
        $this->assertSame($requestId, $event['tags']['request_id']);
        $this->assertSame('test-release', $event['tags']['release']);
    }

    public function test_the_privacy_options_are_pinned_and_the_config_holds_no_closure(): void
    {
        $config = config('sentry');

        $this->assertFalse($config['send_default_pii']);
        $this->assertSame('never', $config['max_request_body_size']);
        $this->assertNull($config['data_collection']);
        $this->assertSame(1, $config['http_connect_timeout']);
        $this->assertSame(2, $config['http_timeout']);
        $this->assertNull($config['traces_sample_rate']);
        $this->assertNull($config['profiles_sample_rate']);
        $this->assertFalse($config['breadcrumbs']['sql_bindings']);
        $this->assertFalse($config['breadcrumbs']['logs']);
        $this->assertSame(
            [SentryEventScrubber::class, 'beforeSend'],
            $config['before_send'],
        );

        array_walk_recursive($config, function (mixed $value, string|int $key): void {
            $this->assertNotInstanceOf(\Closure::class, $value, "config('sentry.{$key}') must not be a Closure.");
        });
    }

    public function test_the_dsn_is_empty_in_the_example_env_and_phpunit_so_the_suite_sends_nothing(): void
    {
        $this->assertMatchesRegularExpression('/^(SENTRY_LARAVEL_DSN=)?$/m', (string) file_get_contents(base_path('.env.example')));
        $this->assertDoesNotMatchRegularExpression('/^SENTRY_(LARAVEL_)?DSN=.+/m', (string) file_get_contents(base_path('.env.example')));
        $this->assertStringNotContainsString('SENTRY_LARAVEL_DSN', (string) file_get_contents(base_path('phpunit.xml')));
    }

    private function throwFromCollaborator(): RuntimeException
    {
        try {
            (new ThrowingCollaborator)->explode(self::SECRET_AMOUNT);
        } catch (RuntimeException $e) {
            return $e;
        }

        $this->fail('The collaborator did not throw.');
    }

    private function recorder(): HttpClientInterface
    {
        return new class($this) implements HttpClientInterface
        {
            public function __construct(private ErrorTrackingServerTest $test) {}

            public function sendRequest(Request $request, Options $options): Response
            {
                $this->test->capture((string) $request->getStringBody());

                return new Response(200, [], '');
            }
        };
    }

    public function capture(string $payload): void
    {
        $this->payloads[] = $payload;
    }

    /**
     * The one `event` envelope item sent so far, decoded.
     *
     * @return array<string, mixed>
     */
    private function singleEvent(): array
    {
        $events = [];

        foreach ($this->payloads as $payload) {
            foreach (explode("\n", $payload) as $line) {
                $decoded = json_decode($line, true);

                if (is_array($decoded) && isset($decoded['exception'])) {
                    $events[] = $decoded;
                }
            }
        }

        $this->assertCount(1, $events, 'Expected exactly one exception event.');

        return $events[0];
    }
}

class ThrowingCollaborator
{
    public function explode(string $amount): never
    {
        throw new RuntimeException('boom');
    }
}

class ThrowingJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        throw new RuntimeException('queued job failed');
    }
}
