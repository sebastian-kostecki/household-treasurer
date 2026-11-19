<?php

namespace Tests\Feature;

use App\Support\AppDay;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The App Day is the calendar date in Europe/Warsaw (ADR-0103), tested on the Polish midnight, never the UTC one.
 */
class AppDayTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function boundaries(): array
    {
        return [
            'winter, one second before Polish midnight' => ['2026-12-31 22:59:59', '2026-12-31'],
            'winter, Polish midnight' => ['2026-12-31 23:00:00', '2027-01-01'],
            'summer, one second before Polish midnight' => ['2027-06-30 21:59:59', '2027-06-30'],
            'summer, Polish midnight' => ['2027-06-30 22:00:00', '2027-07-01'],
            'start of summer time' => ['2027-03-27 23:00:00', '2027-03-28'],
            'end of summer time' => ['2027-10-30 22:00:00', '2027-10-31'],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_today_is_the_polish_calendar_date(string $utcNow, string $expected): void
    {
        $this->travelTo(CarbonImmutable::parse($utcNow, 'UTC'));

        $this->assertSame($expected, AppDay::today()->toDateString());
    }

    public function test_the_value_is_midnight_in_the_application_time_zone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-31 23:30:00', 'UTC'));

        $today = AppDay::today();

        $this->assertSame('00:00:00', $today->format('H:i:s'));
        $this->assertSame(config('app.timezone'), $today->getTimezone()->getName());
        $this->assertTrue($today->equalTo(CarbonImmutable::create(2027, 1, 1)));
    }

    public function test_of_gives_the_polish_date_of_an_instant(): void
    {
        $this->assertSame(
            '2027-02-01',
            AppDay::of(CarbonImmutable::parse('2027-01-31 23:30:00', 'UTC'))->toDateString(),
        );
        $this->assertSame(
            '2027-01-31',
            AppDay::of(CarbonImmutable::parse('2027-01-31 22:59:59', 'UTC'))->toDateString(),
        );
    }
}
