<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ErrorTunnelTest extends TestCase
{
    private const DSN = 'http://publickey@bugsink:8000/1';

    private const INGEST = 'http://bugsink:8000/api/1/envelope/';

    protected function setUp(): void
    {
        parent::setUp();

        config(['sentry.dsn' => self::DSN]);
        RateLimiter::clear('error-tunnel');
    }

    public function test_a_guest_event_envelope_is_forwarded_unchanged_without_a_csrf_token(): void
    {
        Http::fake([self::INGEST => Http::response('{"id":"abc"}', 200, ['Content-Type' => 'application/json'])]);
        $body = $this->envelope(['event']);

        $this->call('POST', route('error-tracking.tunnel'), [], [], [], ['CONTENT_TYPE' => 'text/plain;charset=UTF-8'], $body)
            ->assertOk()
            ->assertContent('{"id":"abc"}');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::INGEST
            && $request->body() === $body
            && $request->hasHeader('X-Sentry-Auth', 'Sentry sentry_version=7, sentry_key=publickey'));
    }

    public function test_the_status_of_the_tracker_is_passed_through(): void
    {
        Http::fake([self::INGEST => Http::response('{"error":"bad"}', 400)]);

        $this->postEnvelope($this->envelope(['event']))->assertStatus(400);
    }

    public function test_an_item_with_a_length_may_contain_newlines(): void
    {
        Http::fake([self::INGEST => Http::response('{}', 200)]);
        $payload = "{\"a\":\n1}";
        $body = json_encode(['dsn' => self::DSN])."\n"
            .json_encode(['type' => 'event', 'length' => strlen($payload)])."\n".$payload."\n";

        $this->postEnvelope($body)->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->body() === $body);
    }

    public function test_a_foreign_dsn_is_refused_and_nothing_is_forwarded(): void
    {
        Http::fake();

        $this->postEnvelope($this->envelope(['event'], 'http://otherkey@bugsink:8000/2'))->assertForbidden();
        $this->postEnvelope($this->envelope(['event'], null))->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_an_oversize_body_is_refused_and_nothing_is_forwarded(): void
    {
        Http::fake();
        $body = $this->envelope(['event']).str_repeat('x', 256 * 1024);

        $this->postEnvelope($body)->assertStatus(413);

        Http::assertNothingSent();
    }

    public function test_a_declared_oversize_length_is_refused_before_the_body_is_read(): void
    {
        Http::fake();
        $valid = $this->envelope(['event']);

        $this->call('POST', route('error-tracking.tunnel'), [], [], [], [
            'CONTENT_TYPE' => 'text/plain;charset=UTF-8',
            'CONTENT_LENGTH' => 256 * 1024 + 1,
        ], $valid)->assertStatus(413);

        Http::assertNothingSent();
    }

    public function test_any_item_other_than_event_is_refused_and_nothing_is_forwarded(): void
    {
        Http::fake();

        $this->postEnvelope($this->envelope(['session']))->assertStatus(422);
        $this->postEnvelope($this->envelope(['event', 'attachment']))->assertStatus(422);
        $this->postEnvelope($this->envelope([]))->assertStatus(422);
        $this->postEnvelope('not an envelope')->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_an_unreachable_tracker_answers_502_and_the_exception_is_reported(): void
    {
        Exceptions::fake();
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->postEnvelope($this->envelope(['event']))->assertStatus(502);

        Exceptions::assertReported(ConnectionException::class);
    }

    public function test_the_route_is_missing_while_the_tracker_is_off(): void
    {
        config(['sentry.dsn' => null]);
        Http::fake();

        $this->postEnvelope($this->envelope(['event']))->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_the_thirty_first_request_in_a_minute_is_throttled(): void
    {
        Http::fake([self::INGEST => Http::response('{}', 200)]);
        $body = $this->envelope(['event']);

        foreach (range(1, 30) as $ignored) {
            $this->postEnvelope($body)->assertOk();
        }

        $this->postEnvelope($body)->assertTooManyRequests();
    }

    public function test_the_route_sits_outside_the_web_group(): void
    {
        $route = app('router')->getRoutes()->getByName('error-tracking.tunnel');

        $this->assertNotNull($route);
        $this->assertSame(['throttle:error-tunnel'], $route->gatherMiddleware());
    }

    public function test_the_page_renders_the_runtime_config_only_when_the_dsn_is_set(): void
    {
        config(['app.release' => 'abc123', 'app.env' => 'production']);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('id="error-tracking"', false)
            ->assertSee('data-error-tracking-dsn="'.self::DSN.'"', false)
            ->assertSee('data-error-tracking-release="abc123"', false)
            ->assertSee('data-error-tracking-environment="production"', false)
            ->assertSee('data-error-tracking-tunnel="'.route('error-tracking.tunnel').'"', false);

        config(['sentry.dsn' => null]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('id="error-tracking"', false)
            ->assertDontSee('data-error-tracking-dsn', false);
    }

    /**
     * @param  list<string>  $itemTypes
     */
    private function envelope(array $itemTypes, ?string $dsn = self::DSN): string
    {
        $header = $dsn === null ? [] : ['dsn' => $dsn];
        $lines = [json_encode($header + ['event_id' => 'abc'])];

        foreach ($itemTypes as $type) {
            $lines[] = json_encode(['type' => $type]);
            $lines[] = json_encode(['message' => 'boom']);
        }

        return implode("\n", $lines)."\n";
    }

    private function postEnvelope(string $body): TestResponse
    {
        return $this->call('POST', route('error-tracking.tunnel'), [], [], [], ['CONTENT_TYPE' => 'text/plain;charset=UTF-8'], $body);
    }
}
