<?php

namespace App\Support;

use RuntimeException;

/**
 * `/up` fails with this while the application is down for maintenance (a deployment is running,
 * or one failed and stays down on purpose). The exception handler does not report it, because
 * every uptime ping during a deployment would otherwise reach the error tracker.
 */
class ApplicationDownForMaintenance extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The application is down for maintenance.');
    }
}
