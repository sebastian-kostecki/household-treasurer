<?php

namespace Tests\Feature;

use App\Support\AppDay;
use App\Support\RehearsalClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RehearsalClockTest extends TestCase
{
    private string $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnv = $this->app['env'];
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        CarbonImmutable::setTestNow();
        $this->app['env'] = $this->originalEnv;

        parent::tearDown();
    }

    public function test_local_environment_applies_the_stored_moment(): void
    {
        Storage::fake('local');
        $this->app['env'] = 'local';
        RehearsalClock::set(CarbonImmutable::parse('2027-01-01 00:30', 'UTC'));

        RehearsalClock::apply();

        $this->assertSame('2027-01-01 00:30:00', now()->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-01', AppDay::today()->toDateString());
    }

    #[DataProvider('nonLocalEnvironments')]
    public function test_other_environments_ignore_the_stored_moment(string $environment): void
    {
        Storage::fake('local');
        RehearsalClock::set(CarbonImmutable::parse('2027-01-01 00:30', 'UTC'));
        $this->app['env'] = $environment;

        RehearsalClock::apply();

        $this->assertFalse(Date::hasTestNow());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonLocalEnvironments(): array
    {
        return ['testing' => ['testing'], 'production' => ['production']];
    }

    public function test_command_outside_local_fails_and_writes_nothing(): void
    {
        Storage::fake('local');
        $this->app['env'] = 'production';

        $this->artisan('app:rehearsal-clock', ['moment' => '2027-01-01 00:30'])->assertExitCode(1);

        Storage::disk('local')->assertMissing(RehearsalClock::FILE);
    }

    public function test_command_stores_the_moment_in_local(): void
    {
        Storage::fake('local');
        $this->app['env'] = 'local';
        $this->travelTo('2026-11-18 12:00:00');

        $this->artisan('app:rehearsal-clock', ['moment' => '2027-01-01 00:30'])
            ->expectsOutputToContain('UTC: 2027-01-01 00:30:00, Europe/Warsaw: 2027-01-01 01:30:00')
            ->assertExitCode(0);

        $this->assertSame('2027-01-01 00:30:00', RehearsalClock::current()?->format('Y-m-d H:i:s'));
    }

    #[DataProvider('unreadableMoments')]
    public function test_command_rejects_a_moment_that_is_not_an_exact_utc_date_and_time(string $moment): void
    {
        Storage::fake('local');
        $this->app['env'] = 'local';

        $this->artisan('app:rehearsal-clock', ['moment' => $moment])->assertExitCode(1);

        Storage::disk('local')->assertMissing(RehearsalClock::FILE);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadableMoments(): array
    {
        return [
            'day past the end of the month' => ['2027-02-30 10:00'],
            'relative word' => ['tomorrow'],
            'month first' => ['12/31/2026 10:00'],
            'empty' => [''],
        ];
    }

    public function test_command_rejects_a_moment_more_than_a_session_lifetime_in_the_past(): void
    {
        Storage::fake('local');
        $this->app['env'] = 'local';
        $this->travelTo('2026-11-18 12:00:00');

        $this->artisan('app:rehearsal-clock', ['moment' => '2026-11-18 09:00'])->assertExitCode(1);

        Storage::disk('local')->assertMissing(RehearsalClock::FILE);
    }

    public function test_a_failed_write_is_reported_with_the_file_path(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->andReturnFalse();
        $disk->shouldReceive('path')->with(RehearsalClock::FILE)->andReturn('/srv/storage/app/private/rehearsal-clock');
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('/srv/storage/app/private/rehearsal-clock');

        RehearsalClock::set(CarbonImmutable::parse('2027-01-01 00:30', 'UTC'));
    }

    public function test_a_failed_delete_is_reported_with_the_file_path(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->with(RehearsalClock::FILE)->andReturnTrue();
        $disk->shouldReceive('delete')->andReturnFalse();
        $disk->shouldReceive('path')->with(RehearsalClock::FILE)->andReturn('/srv/storage/app/private/rehearsal-clock');
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('/srv/storage/app/private/rehearsal-clock');

        RehearsalClock::clear();
    }

    public function test_an_unreadable_file_does_not_block_the_clock_command_itself(): void
    {
        Storage::fake('local');
        $this->app['env'] = 'local';
        Storage::disk('local')->put(RehearsalClock::FILE, 'not a moment');
        $originalArgv = $_SERVER['argv'];
        $_SERVER['argv'] = ['artisan', 'app:rehearsal-clock', '--off'];

        try {
            RehearsalClock::apply();
        } finally {
            $_SERVER['argv'] = $originalArgv;
        }

        $this->assertFalse(Date::hasTestNow());
    }

    public function test_off_removes_the_file(): void
    {
        Storage::fake('local');
        $this->app['env'] = 'local';
        RehearsalClock::set(CarbonImmutable::parse('2027-01-01 00:30', 'UTC'));

        $this->artisan('app:rehearsal-clock', ['--off' => true])->assertExitCode(0);

        Storage::disk('local')->assertMissing(RehearsalClock::FILE);
    }

    public function test_unreadable_content_throws_naming_file_and_content(): void
    {
        Storage::fake('local');
        $this->app['env'] = 'local';
        Storage::disk('local')->put(RehearsalClock::FILE, 'not a moment');

        try {
            RehearsalClock::apply();
            $this->fail('An unreadable rehearsal clock file must not be skipped.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(Storage::disk('local')->path(RehearsalClock::FILE), $exception->getMessage());
            $this->assertStringContainsString('not a moment', $exception->getMessage());
        }
    }
}
