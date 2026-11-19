<?php

namespace App\Enums;

/**
 * How urgent a Dashboard notice is. Exceeded: a limit or balance is already
 * broken. Warning: still within bounds but close to breaking (emitted by
 * month_plan_not_approved, month_pace_ahead, collected_not_covered
 * and budget_not_in_execution).
 */
enum DashboardNoticeSeverity: string
{
    case Exceeded = 'exceeded';
    case Warning = 'warning';
}
