<?php

namespace App\Enums;

/**
 * How urgent a Dashboard notice is. Exceeded: a limit or balance is already
 * broken. Warning: still within bounds but close to breaking (emitted by
 * month_plan_not_approved and month_pace_ahead).
 */
enum DashboardNoticeSeverity: string
{
    case Exceeded = 'exceeded';
    case Warning = 'warning';
}
