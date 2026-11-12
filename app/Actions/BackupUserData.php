<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CapitalMovement;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\OwnTransfer;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class BackupUserData
{
    public const SNAPSHOT_VERSION = 11;

    public const SNAPSHOT_DIRECTORY = 'user-snapshots';

    public function handle(User $user): string
    {
        $budgetIds = $user->budgets()->pluck('id');

        $snapshot = [
            'version' => self::SNAPSHOT_VERSION,
            'source' => [
                'user_id' => $user->id,
                'email' => $user->email,
            ],
            'created_at' => now()->toIso8601String(),
            'first_run_checklist_dismissed_at' => $user->first_run_checklist_dismissed_at?->toIso8601String(),
            'default_set_aside_percent' => $user->default_set_aside_percent,
            'accounts' => $user->accounts()
                ->orderBy('id')
                ->get()
                ->map(fn ($account): array => $this->serializeAccount($account))
                ->all(),
            'term_deposits' => $user->termDeposits()
                ->orderBy('id')
                ->get()
                ->map(fn ($termDeposit): array => $this->serializeTermDeposit($termDeposit))
                ->all(),
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
            'financial_operations' => $user->financialOperations()
                ->orderBy('id')
                ->get()
                ->map(fn ($operation): array => $this->serializeFinancialOperation($operation))
                ->all(),
            'own_transfers' => $user->ownTransfers()
                ->orderBy('id')
                ->get()
                ->map(fn ($transfer): array => $this->serializeOwnTransfer($transfer))
                ->all(),
            'capital_movements' => $user->capitalMovements()
                ->orderBy('id')
                ->get()
                ->map(fn ($movement): array => $this->serializeCapitalMovement($movement))
                ->all(),
            'month_plans' => MonthPlan::query()
                ->whereIn('budget_id', $budgetIds)
                ->with(['items' => fn ($query) => $query->orderBy('id')])
                ->orderBy('id')
                ->get()
                ->map(fn ($monthPlan): array => $this->serializeMonthPlan($monthPlan))
                ->all(),
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

    /**
     * @return array<string, mixed>
     */
    private function serializeFinancialOperation(FinancialOperation $operation): array
    {
        return [
            'id' => $operation->id,
            'type' => $operation->type->value,
            'account_id' => $operation->account_id,
            'budget_line_id' => $operation->budget_line_id,
            'budget_id' => $operation->budget_id,
            'operation_date' => $operation->operation_date->toDateString(),
            'amount_minor_units' => $operation->amount_minor_units,
            'description' => $operation->description,
            'counterparty' => $operation->counterparty,
            'raw_statement_description' => $operation->raw_statement_description,
            'import_identity_account_id' => $operation->import_identity_account_id,
            'import_identity_date' => $operation->import_identity_date?->toDateString(),
            'import_identity_amount_minor_units' => $operation->import_identity_amount_minor_units,
            'import_identity_digest' => $operation->import_identity_digest,
            'created_at' => $operation->created_at?->toIso8601String(),
            'updated_at' => $operation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOwnTransfer(OwnTransfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'source_account_id' => $transfer->source_account_id,
            'destination_account_id' => $transfer->destination_account_id,
            'transfer_date' => $transfer->transfer_date->toDateString(),
            'amount_minor_units' => $transfer->amount_minor_units,
            'description' => $transfer->description,
            'source_raw_statement_description' => $transfer->source_raw_statement_description,
            'destination_raw_statement_description' => $transfer->destination_raw_statement_description,
            'source_import_identity_account_id' => $transfer->source_import_identity_account_id,
            'source_import_identity_date' => $transfer->source_import_identity_date?->toDateString(),
            'source_import_identity_amount_minor_units' => $transfer->source_import_identity_amount_minor_units,
            'source_import_identity_digest' => $transfer->source_import_identity_digest,
            'destination_import_identity_account_id' => $transfer->destination_import_identity_account_id,
            'destination_import_identity_date' => $transfer->destination_import_identity_date?->toDateString(),
            'destination_import_identity_amount_minor_units' => $transfer->destination_import_identity_amount_minor_units,
            'destination_import_identity_digest' => $transfer->destination_import_identity_digest,
            'created_at' => $transfer->created_at?->toIso8601String(),
            'updated_at' => $transfer->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCapitalMovement(CapitalMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'account_id' => $movement->account_id,
            'term_deposit_id' => $movement->term_deposit_id,
            'movement_date' => $movement->movement_date->toDateString(),
            'amount_minor_units' => $movement->amount_minor_units,
            'direction' => $movement->direction->value,
            'description' => $movement->description,
            'raw_statement_description' => $movement->raw_statement_description,
            'import_identity_account_id' => $movement->import_identity_account_id,
            'import_identity_date' => $movement->import_identity_date?->toDateString(),
            'import_identity_amount_minor_units' => $movement->import_identity_amount_minor_units,
            'import_identity_digest' => $movement->import_identity_digest,
            'created_at' => $movement->created_at?->toIso8601String(),
            'updated_at' => $movement->updated_at?->toIso8601String(),
        ];
    }
}
