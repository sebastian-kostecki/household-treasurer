<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AccountBalancesTool;
use App\Mcp\Tools\AddCashOperationTool;
use App\Mcp\Tools\ListCategoriesTool;
use App\Mcp\Tools\MonthPlanTool;
use App\Mcp\Tools\MonthToSpendTool;
use App\Mcp\Tools\RecentOperationsTool;
use App\Mcp\Tools\YearPlanTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Domowy Skarbnik')]
#[Version('1.0.0')]
#[Instructions('Read-only access to the household budget of one user. Answer in Polish and give amounts in zł (every amount comes as *_minor_units, an integer in grosz, and as a display string with " zł"). Every answer carries `app_day`, the calendar day in Poland the answer is based on: compute relative dates ("w tym miesiącu", "wczoraj", "w listopadzie") from `app_day`, never from your own clock. Use `list-categories-tool` to map a Category name to its id before filtering operations. A year without a Budget in execution has no To Spend and no month plan: say its phase instead of guessing another year.')]
class TreasurerServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        AccountBalancesTool::class,
        MonthToSpendTool::class,
        MonthPlanTool::class,
        YearPlanTool::class,
        RecentOperationsTool::class,
        ListCategoriesTool::class,
        AddCashOperationTool::class,
    ];
}
