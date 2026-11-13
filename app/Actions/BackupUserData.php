<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\MonthPlan;
use App\Models\TermDeposit;
use App\Models\User;
use App\Support\UserSnapshot\BalanceReconciliationRows;
use App\Support\UserSnapshot\LedgerRows;
use Illuminate\Support\Facades\Storage;

class BackupUserData
{
    public const SNAPSHOT_VERSION = 13;

    public const SNAPSHOT_DIRECTORY = 'user-snapshots';

    public function handle(User $user): string
    {
        $snapshot = [
            ...$this->header($user),
            ...$this->holdings($user),
            ...$this->budgetPlan($user),
            ...$this->ledger($user),
        ];

        $filename = sprintf('%d-%s.json', $user->id, now()->format('YmdHis'));

        Storage::disk('local')->makeDirectory(self::SNAPSHOT_DIRECTORY);

        Storage::disk('local')->put(
            self::SNAPSHOT_DIRECTORY.'/'.$filename,
            json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );

        return Storage::disk('local')->path(self::SNAPSHOT_DIRECTORY.'/'.$filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function header(User $user): array
    {
        return [
            'version' => self::SNAPSHOT_VERSION,
            'source' => [
                'user_id' => $user->id,
                'email' => $user->email,
            ],
            'created_at' => now()->toIso8601String(),
            'first_run_checklist_dismissed_at' => $user->first_run_checklist_dismissed_at?->toIso8601String(),
            'default_set_aside_percent' => $user->default_set_aside_percent,
        ];
    }

    /**
     * Accounts, their Balance Reconciliations and Term Deposits.
     *
     * @return array<string, mixed>
     */
    private function holdings(User $user): array
    {
        return [
            'accounts' => $user->accounts()
                ->orderBy('id')
                ->get()
                ->map(fn ($account): array => $this->serializeAccount($account))
                ->all(),
            'balance_reconciliations' => BalanceReconciliationRows::forUser($user),
            'term_deposits' => $user->termDeposits()
                ->orderBy('id')
                ->get()
                ->map(fn ($termDeposit): array => $this->serializeTermDeposit($termDeposit))
                ->all(),
        ];
    }

    /**
     * Budgets, the Category list and the Budget Lines.
     *
     * @return array<string, mixed>
     */
    private function budgetPlan(User $user): array
    {
        $budgetIds = $user->budgets()->pluck('id');

        return [
            'budgets' => $user->budgets()
                ->orderBy('id')
                ->get()
                ->map(fn ($budget): array => $this->serializeBudget($budget))
                ->all(),
            'category_groups' => $user->categoryGroups()
                ->orderBy('id')
                ->get()
                ->map(fn ($group): array => $this->serializeCategoryGroup($group))
                ->all(),
            'categories' => $user->categories()
                ->with('rhythms')
                ->orderBy('id')
                ->get()
                ->map(fn ($category): array => $this->serializeCategory($category))
                ->all(),
            'budget_lines' => BudgetLine::query()
                ->whereIn('budget_id', $budgetIds)
                ->orderBy('id')
                ->get()
                ->map(fn ($budgetLine): array => $this->serializeBudgetLine($budgetLine))
                ->all(),
        ];
    }

    /**
     * Operations, transfers, capital movements and Month Plans.
     *
     * @return array<string, mixed>
     */
    private function ledger(User $user): array
    {
        $budgetIds = $user->budgets()->pluck('id');

        return [
            ...LedgerRows::forUser($user),
            'month_plans' => MonthPlan::query()
                ->whereIn('budget_id', $budgetIds)
                ->with(['items' => fn ($query) => $query->orderBy('id')])
                ->orderBy('id')
                ->get()
                ->map(fn ($monthPlan): array => $this->serializeMonthPlan($monthPlan))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAccount(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type->value,
            'bank' => $account->bank?->value,
            'opening_date' => $account->opening_date->toDateString(),
            'opening_balance_minor_units' => $account->opening_balance_minor_units,
            'current_balance_minor_units' => $account->current_balance_minor_units,
            'sheet_year' => $account->sheet_year,
            'archived_at' => $account->archived_at?->toIso8601String(),
            'created_at' => $account->created_at?->toIso8601String(),
            'updated_at' => $account->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTermDeposit(TermDeposit $termDeposit): array
    {
        return [
            'id' => $termDeposit->id,
            'name' => $termDeposit->name,
            'bank' => $termDeposit->bank->value,
            'opening_date' => $termDeposit->opening_date->toDateString(),
            'end_date' => $termDeposit->end_date?->toDateString(),
            'opening_balance_minor_units' => $termDeposit->opening_balance_minor_units,
            'current_balance_minor_units' => $termDeposit->current_balance_minor_units,
            'archived_at' => $termDeposit->archived_at?->toIso8601String(),
            'created_at' => $termDeposit->created_at?->toIso8601String(),
            'updated_at' => $termDeposit->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBudget(Budget $budget): array
    {
        return [
            'id' => $budget->id,
            'year' => $budget->year,
            'phase' => $budget->phase->value,
            'created_at' => $budget->created_at?->toIso8601String(),
            'updated_at' => $budget->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCategoryGroup(CategoryGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'type' => $group->type->value,
            'sort_order' => $group->sort_order,
            'created_at' => $group->created_at?->toIso8601String(),
            'updated_at' => $group->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'category_group_id' => $category->category_group_id,
            'name' => $category->name,
            'icon' => $category->icon,
            'color' => $category->color,
            'description' => $category->description,
            'rhythms' => $category->rhythms
                ->map(fn ($entry): array => [
                    'rhythm' => $entry->rhythm->value,
                    'start_year' => $entry->start_year,
                    'start_month' => $entry->start_month,
                    'opening_collected_minor_units' => $entry->opening_collected_minor_units,
                ])
                ->all(),
            'sort_order' => $category->sort_order,
            'type' => $category->type->value,
            'archived_at' => $category->archived_at?->toIso8601String(),
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBudgetLine(BudgetLine $budgetLine): array
    {
        return [
            'id' => $budgetLine->id,
            'budget_id' => $budgetLine->budget_id,
            'category_id' => $budgetLine->category_id,
            'planned_amount_minor_units' => $budgetLine->planned_amount_minor_units,
            'initial_plan_minor_units' => $budgetLine->initial_plan_minor_units,
            'execution_minor_units' => $budgetLine->execution_minor_units,
            'archived_at' => $budgetLine->archived_at?->toIso8601String(),
            'created_at' => $budgetLine->created_at?->toIso8601String(),
            'updated_at' => $budgetLine->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMonthPlan(MonthPlan $monthPlan): array
    {
        return [
            'id' => $monthPlan->id,
            'budget_id' => $monthPlan->budget_id,
            'month' => $monthPlan->month,
            'approved_at' => $monthPlan->approved_at->toIso8601String(),
            'approved_set_aside_minor_units' => $monthPlan->approved_set_aside_minor_units,
            'current_set_aside_minor_units' => $monthPlan->current_set_aside_minor_units,
            'items' => $monthPlan->items
                ->map(fn ($item): array => [
                    'budget_line_id' => $item->budget_line_id,
                    'approved_amount_minor_units' => $item->approved_amount_minor_units,
                    'current_amount_minor_units' => $item->current_amount_minor_units,
                ])
                ->all(),
        ];
    }
}
