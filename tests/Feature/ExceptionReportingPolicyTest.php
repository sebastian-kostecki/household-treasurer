<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ApplicationDownForMaintenance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ExceptionReportingPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        if ($this->app !== null && $this->app->isDownForMaintenance()) {
            $this->app->maintenanceMode()->deactivate();
        }

        parent::tearDown();
    }

    private function shouldReport(\Throwable $exception): bool
    {
        return app(ExceptionHandler::class)->shouldReport($exception);
    }

    public function test_server_errors_raised_with_abort_are_reported(): void
    {
        $this->assertTrue($this->shouldReport(new HttpException(500)));
        $this->assertTrue($this->shouldReport(new HttpException(503)));
    }

    public function test_client_errors_validation_and_guest_refusals_stay_quiet(): void
    {
        $this->assertFalse($this->shouldReport(new HttpException(403)));
        $this->assertFalse($this->shouldReport(new HttpException(404)));
        $this->assertFalse($this->shouldReport(ValidationException::withMessages(['field' => 'invalid'])));
        $this->assertFalse($this->shouldReport(new AuthorizationException));
    }

    public function test_the_maintenance_503_and_the_health_failure_are_not_reported_in_maintenance(): void
    {
        $this->app->maintenanceMode()->activate([]);

        $this->assertFalse($this->shouldReport(new HttpException(503)));
        $this->assertFalse($this->shouldReport(new ApplicationDownForMaintenance));
        $this->assertTrue($this->shouldReport(new HttpException(500)));
    }

    public function test_the_health_failure_is_reported_outside_maintenance(): void
    {
        $this->assertTrue($this->shouldReport(new ApplicationDownForMaintenance));
    }

    public function test_an_artisan_command_failing_in_maintenance_is_reported(): void
    {
        Exceptions::fake();
        Artisan::command('test:boom', fn () => throw new RuntimeException('boom'));
        $this->app->maintenanceMode()->activate([]);

        app(Kernel::class)->handle(new ArrayInput(['command' => 'test:boom']), new BufferedOutput);

        Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'boom');
    }

    public function test_an_authorization_exception_of_a_signed_in_user_is_reported_once_a_minute_per_route(): void
    {
        Exceptions::fake();
        $this->actingAs(User::factory()->create());
        Route::middleware('web')->get('/test-refusal-a', fn () => throw new AuthorizationException)->name('test.refusal-a');
        Route::middleware('web')->get('/test-refusal-b', fn () => throw new AuthorizationException)->name('test.refusal-b');

        $this->get('/test-refusal-a')->assertForbidden();
        $this->get('/test-refusal-a')->assertForbidden();
        Exceptions::assertReportedCount(1);

        $this->get('/test-refusal-b')->assertForbidden();
        Exceptions::assertReportedCount(2);
    }
}
