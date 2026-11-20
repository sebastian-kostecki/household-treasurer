<?php

namespace Tests\Feature;

use App\Support\ErrorTracking\NtfyAlert;
use App\Support\ErrorTracking\NtfyUnreachable;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ErrorAlertRelayTest extends TestCase
{
    private const SECRET = 'relay-secret-for-tests';

    private const NTFY = 'https://ntfy.test/topic-for-tests';

    private const ISSUE = 'https://errors.example.test/issues/497f6eca-6276-4993-bfeb-53cbbbba6f08/';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'error-tracking.alert_secret' => self::SECRET,
            'error-tracking.alert_ntfy_url' => self::NTFY,
            'error-tracking.alert_issue_url_prefix' => 'https://errors.example.test/',
        ]);
        RateLimiter::clear('error-alerts');
    }

    public function test_only_the_fixed_title_reason_class_and_issue_link_reach_ntfy(): void
    {
        Http::fake([self::NTFY => Http::response('{}', 200)]);

        $this->relay(self::SECRET)->assertNoContent();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $sent = $request->body().json_encode($request->headers(), JSON_UNESCAPED_UNICODE);

            return $request->url() === self::NTFY
                && $request->method() === 'POST'
                && $request->body() === 'NEW: App\\Support\\InvariantViolation'
                && $request->header('Title') === ['=?UTF-8?B?'.base64_encode('Domowy Skarbnik: nowy błąd').'?=']
                && $request->header('Click') === [self::ISSUE]
                && $request->header('Tags') === ['warning']
                && ! str_contains($sent, 'Prąd')
                && ! str_contains($sent, '1 234,56');
        });
    }

    public function test_the_title_header_decodes_to_the_fixed_polish_title(): void
    {
        $encoded = '=?UTF-8?B?'.base64_encode(NtfyAlert::TITLE).'?=';

        $this->assertSame(NtfyAlert::TITLE, iconv_mime_decode($encoded, 0, 'UTF-8'));
    }

    public function test_an_issue_url_outside_the_bugsink_host_is_not_forwarded_as_a_link(): void
    {
        Http::fake([self::NTFY => Http::response('{}', 200)]);

        $this->relay(self::SECRET, ['url' => 'https://evil.example.test/phish'])->assertNoContent();

        Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Click'));
    }

    public function test_a_wrong_secret_is_a_404_and_sends_nothing(): void
    {
        Http::fake();

        $this->relay('wrong-secret')->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_an_empty_configured_secret_is_a_404_and_sends_nothing(): void
    {
        Http::fake();
        config(['error-tracking.alert_secret' => '']);

        $this->relay(self::SECRET)->assertNotFound();
        $this->relay('x')->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_an_ntfy_server_error_fails_the_relay_and_is_reported(): void
    {
        Exceptions::fake();
        Http::fake([self::NTFY => Http::response('boom', 500)]);

        $this->relay(self::SECRET)->assertServerError();

        Exceptions::assertReported(RequestException::class);
    }

    public function test_an_unreachable_ntfy_is_reported_without_the_topic_url(): void
    {
        Exceptions::fake();
        Http::fake([self::NTFY => fn () => throw new ConnectionException('cURL error 7: Failed to connect for url: '.self::NTFY)]);

        $this->relay(self::SECRET)->assertServerError();

        Exceptions::assertReported(fn (NtfyUnreachable $e): bool => ! str_contains($e->getMessage(), 'topic-for-tests')
            && $e->getPrevious() === null);
    }

    public function test_a_token_with_a_trailing_newline_is_replaced_not_forwarded(): void
    {
        Http::fake([self::NTFY => Http::response('{}', 200)]);
        // TrimStrings would cut the newline first; the pattern itself must refuse it too.
        $this->withoutMiddleware(TrimStrings::class);

        $this->relay(self::SECRET, ['alert_reason' => "NEW\n", 'calculated_type' => "App\\Boom\n"])->assertNoContent();

        Http::assertSent(fn (Request $request): bool => $request->body() === 'ALERT: unknown');
    }

    public function test_an_issue_url_on_a_host_that_only_starts_like_the_prefix_gets_no_link(): void
    {
        Http::fake([self::NTFY => Http::response('{}', 200)]);
        config(['error-tracking.alert_issue_url_prefix' => 'https://errors.example.test']);

        $this->relay(self::SECRET, ['url' => 'https://errors.example.test.evil.tld/issues/1/'])->assertNoContent();
        $this->relay(self::SECRET, ['url' => self::ISSUE.'?x=1'])->assertNoContent();

        Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Click'));
        Http::assertSent(fn (Request $request): bool => $request->header('Click') === [self::ISSUE.'?x=1']);
    }

    public function test_the_relay_is_limited_to_ten_requests_a_minute(): void
    {
        Http::fake([self::NTFY => Http::response('{}', 200)]);

        for ($i = 0; $i < 10; $i++) {
            $this->relay(self::SECRET)->assertNoContent();
        }

        $this->relay(self::SECRET)->assertTooManyRequests();
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function relay(string $secret, array $override = []): TestResponse
    {
        $payload = array_merge(
            json_decode((string) file_get_contents(base_path('tests/Fixtures/bugsink-webhook.json')), true, flags: JSON_THROW_ON_ERROR),
            $override,
        );

        return $this->postJson('/error-tracking/alerts/'.$secret, $payload);
    }
}
