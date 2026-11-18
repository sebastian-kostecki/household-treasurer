<?php

namespace Tests\Feature;

use App\Support\RehearsalClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
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
        $this->assertSame('2027-01-01', CarbonImmutable::today('UTC')->toDateString());
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

        $this->artisan('app:rehearsal-clock', ['moment' => '2027-01-01 00:30'])->assertExitCode(0);

        $this->assertSame('2027-01-01 00:30:00', RehearsalClock::current()?->format('Y-m-d H:i:s'));
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('rehearsal-clock');
        $this->expectExceptionMessage('not a moment');

        RehearsalClock::apply();
    }
}
