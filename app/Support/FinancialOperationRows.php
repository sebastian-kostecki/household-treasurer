<?php

namespace App\Support;

use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The user's financial operations for a filter, newest first, and the domain fields
 * of one row, shared by the Operations page and MCP tools.
 */
final class FinancialOperationRows
{
    /**
     * @return Builder<FinancialOperation>
     */
    public static function query(User $user, OperationsListQuery $filters): Builder
    {
        /** @var Builder<FinancialOperation> $query */
        $query = $filters->apply($user->financialOperations()->getQuery());

        return $query
            ->with(['account:id,name,type,bank,sheet_year', 'budgetLine:id,category_id,archived_at'])
            ->orderByDesc('operation_date')
            ->orderByDesc('id');
    }

    /**
     * @return array{
     *     id: int,
     *     operation_date: string,
     *     description: string,
     *     counterparty: string|null,
     *     type: string,
     *     amount_minor_units: int,
     *     account_id: int,
     *     category_id: int,
     *     category_label: string,
     *     category_archived: bool,
     * }
     */
    public static function row(FinancialOperation $operation): array
    {
        return [
            'id' => $operation->id,
            'operation_date' => $operation->operation_date->toDateString(),
            'description' => $operation->description,
            'counterparty' => $operation->counterparty,
            'type' => $operation->type->value,
            'amount_minor_units' => $operation->amount_minor_units,
            'account_id' => $operation->account_id,
            'category_id' => $operation->budgetLine->category_id,
            'category_label' => $operation->budgetLine->category->name,
            'category_archived' => $operation->budgetLine->isArchived() || $operation->budgetLine->category->isArchived(),
        ];
    }
}
