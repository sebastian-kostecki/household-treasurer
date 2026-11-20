<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;
use RuntimeException;
use Tests\TestCase;

class RequestContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_exception_in_a_named_web_route_is_logged_with_route_user_request_id_and_release(): void
    {
        config([
            'app.release' => 'abc1234',
            'logging.default' => 'capture',
            'logging.channels.capture' => ['driver' => 'monolog', 'handler' => TestHandler::class],
        ]);
        $user = User::factory()->create();
        Route::middleware('web')->get('/test-context-failure', fn () => throw new RuntimeException('context failure'))
            ->name('test.context-failure');

        $response = $this->actingAs($user)->get('/test-context-failure');

        $response->assertServerError();
        $requestId = $response->headers->get('X-Request-Id');
        $this->assertNotEmpty($requestId);

        /** @var TestHandler $handler */
        $handler = Log::channel('capture')->getHandlers()[0];
        $record = collect($handler->getRecords())->first(fn ($record): bool => $record->message === 'context failure');

        $this->assertNotNull($record);
        $this->assertSame('test.context-failure', $record->extra['route'] ?? null);
        $this->assertSame($user->id, $record->extra['user_id'] ?? null);
        $this->assertSame($requestId, $record->extra['request_id'] ?? null);
        $this->assertSame('abc1234', $record->extra['release'] ?? null);
    }

    public function test_the_incoming_request_id_is_ignored(): void
    {
        $response = $this->withHeader('X-Request-Id', 'client-chosen')->get('/');

        $this->assertNotSame('client-chosen', $response->headers->get('X-Request-Id'));
    }
}
