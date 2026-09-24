<?php

namespace App\Enums;

/**
 * How urgent a Dashboard notice is. Exceeded: a limit or balance is already
 * broken. Warning: still within bounds but close to breaking (no notice kind
 * emits it yet).
 */
enum DashboardNoticeSeverity: string
{
    case Exceeded = 'exceeded';
    case Warning = 'warning';
}
