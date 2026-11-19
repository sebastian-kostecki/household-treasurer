<?php

namespace App\Support;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * What `/up` checks beyond booting the framework. Every page needs the database (sessions and
 * cache live there), and a deployment that failed stays in maintenance mode on purpose, so `/up`
 * answers 500 in both cases instead of "up" while users get errors or the maintenance page.
 * The health route reports the exception and renders the 500 itself.
 */
class ApplicationHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        if (app()->isDownForMaintenance()) {
            throw new RuntimeException('The application is down for maintenance.');
        }

        DB::select('select 1');
    }
}
