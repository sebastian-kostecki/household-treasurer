<?php

namespace App\Support;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;

/**
 * What `/up` checks beyond booting the framework. Every page needs the database (sessions and
 * cache live there), and a deployment that failed stays in maintenance mode on purpose, so `/up`
 * answers 500 in both cases instead of "up" while users get errors or the maintenance page.
 * The maintenance failure is the named `ApplicationDownForMaintenance`, which the handler does not report.
 * The health route reports the exception and renders the 500 itself.
 */
class ApplicationHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        if (app()->isDownForMaintenance()) {
            throw new ApplicationDownForMaintenance;
        }

        DB::select('select 1');
    }
}
