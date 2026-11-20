<?php

namespace Tests\Feature;

use App\Actions\BackupUserData;
use App\Models\User;
use App\Support\ApplicationDownForMaintenance;
use App\Support\Backup\HealthyBackupWasFoundNotification;
use App\Support\UserSnapshot\UserSnapshotException;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Notifications\Notifiable;
use Tests\TestCase;

class BackupSignalsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        if ($this->app !== null && $this->app->isDownForMaintenance()) {
            $this->app->maintenanceMode()->deactivate();
        }

        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function backupCommands(): array
    {
        return [
            'backup' => ['backup:run', 'schedule-backup-run.log'],
            'cleanup' => ['backup:clean', 'schedule-backup-clean.log'],
            'monitor' => ['backup:monitor', 'schedule-backup-monitor.log'],
        ];
    }

    #[DataProvider('backupCommands')]
    public function test_backup_commands_run_in_maintenance_mode_and_keep_their_last_output(string $command, string $outputFile): void
    {
        foreach ($this->scheduledEvents($command) as $event) {
            $this->assertTrue($event->runsInMaintenanceMode());
            $this->assertSame(storage_path('logs/'.$outputFile), $event->output);
            $this->assertFalse($event->shouldAppendOutput);
        }
    }

    public function test_a_failed_backup_command_mails_its_output_to_the_backup_recipient(): void
    {
        config(['backup.notifications.mail.to' => 'owner@example.test']);
        $event = $this->scheduledEvents('backup:run')[0];
        $event->output = tempnam(sys_get_temp_dir(), 'backup-output');
        file_put_contents($event->output, 'TypeError: pg_dump wrapper broke');

        try {
            $event->finish($this->app, 1);
        } finally {
            unlink($event->output);
        }

        $messages = $this->app->make('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('owner@example.test', $messages[0]->getEnvelope()->getRecipients()[0]->getAddress());
        $this->assertStringContainsString('TypeError: pg_dump wrapper broke', $messages[0]->getOriginalMessage()->getTextBody());
    }

    public function test_a_successful_backup_command_sends_no_output_mail(): void
    {
        $event = $this->scheduledEvents('backup:run')[0];

        $event->finish($this->app, 0);

        $this->assertCount(0, $this->app->make('mailer')->getSymfonyTransport()->messages());
    }

    public function test_the_healthy_backup_mail_goes_out_on_mondays_only(): void
    {
        Notification::fake();
        $event = new HealthyBackupWasFound(diskName: 'backups', backupName: 'Household Treasurer');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-23 04:00', 'Europe/Warsaw'));
        event($event);

        Notification::assertSentTo(
            new Notifiable,
            HealthyBackupWasFoundNotification::class,
            fn (HealthyBackupWasFoundNotification $notification): bool => $notification->via() === ['mail'],
        );

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-24 04:00', 'Europe/Warsaw'));
        $this->assertSame([], (new HealthyBackupWasFoundNotification($event))->via());
    }

    public function test_health_check_is_up_when_the_database_answers(): void
    {
        config(['app.debug' => false]);

        $this->get('/up')->assertOk();
    }

    public function test_health_check_fails_while_the_application_is_down_for_maintenance(): void
    {
        config(['app.debug' => false]);
        Exceptions::fake();
        $this->app->maintenanceMode()->activate([]);

        $this->get('/up')->assertInternalServerError();

        // Named and deliberately unreported: every uptime ping during a deployment would reach the error tracker.
        Exceptions::assertNotReported(ApplicationDownForMaintenance::class);
    }

    public function test_health_check_fails_when_the_database_does_not_answer(): void
    {
        config(['app.debug' => false]);
        Exceptions::fake();
        DB::shouldReceive('select')->once()->with('select 1')->andThrow(new RuntimeException('Connection refused'));

        $this->get('/up')->assertInternalServerError();

        Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Connection refused');
    }

    public function test_user_snapshot_that_cannot_be_written_fails_instead_of_returning_a_path(): void
    {
        $user = User::factory()->create();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('makeDirectory')->andReturnTrue();
        $disk->shouldReceive('put')->andReturnFalse();
        $disk->shouldReceive('path')->andReturnUsing(fn (string $path): string => '/storage/app/private/'.$path);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->expectException(UserSnapshotException::class);
        $this->expectExceptionMessage('Could not write the snapshot file');

        app(BackupUserData::class)->handle($user);
    }

    /**
     * Artisan's starting callbacks are static, so a test process can register the schedule more
     * than once; every copy must carry the same settings.
     *
     * @return list<ScheduledEvent>
     */
    private function scheduledEvents(string $command): array
    {
        // withSchedule() registers its events only once the Artisan console starts.
        Artisan::all();

        $events = array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            fn (ScheduledEvent $event): bool => str_contains((string) $event->command, $command),
        ));

        $this->assertNotEmpty($events, "A scheduled event runs {$command}.");

        return $events;
    }
}
