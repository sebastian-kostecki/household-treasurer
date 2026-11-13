<?php

namespace App\Support;

use App\Enums\OperationsListSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class OperationsListQuery
{
    /**
     * The filter keys of the Operations list URL, also mirrored in `resources/js/lib/operations-list-query.ts`.
     */
    public const array FILTER_KEYS = ['q', 'year', 'category_id', 'account_id', 'term_deposit_id', 'date_from', 'date_to'];

    /**
     * The page names of the three Operations paginators.
     */
    public const array PAGE_KEYS = ['operations_page', 'own_transfers_page', 'capital_movements_page'];

    public function __construct(
        public ?string $q,
        public ?int $year,
        public ?int $categoryId,
        public ?int $accountId,
        public ?int $termDepositId,
        public ?string $dateFrom,
        public ?string $dateTo,
    ) {}

    /**
     * @param  array{q?: ?string, year?: int|string|null, category_id?: int|string|null, account_id?: int|string|null, term_deposit_id?: int|string|null, date_from?: ?string, date_to?: ?string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $year = $validated['year'] ?? null;
        $categoryId = $validated['category_id'] ?? null;
        $accountId = $validated['account_id'] ?? null;
        $termDepositId = $validated['term_deposit_id'] ?? null;

        return new self(
            q: $validated['q'] ?? null,
            year: $year === null ? null : (int) $year,
            categoryId: $categoryId === null ? null : (int) $categoryId,
            accountId: $accountId === null ? null : (int) $accountId,
            termDepositId: $termDepositId === null ? null : (int) $termDepositId,
            dateFrom: $validated['date_from'] ?? null,
            dateTo: $validated['date_to'] ?? null,
        );
    }

    public function isActive(): bool
    {
        return $this->q !== null
            || $this->year !== null
            || $this->categoryId !== null
            || $this->accountId !== null
            || $this->termDepositId !== null
            || $this->dateFrom !== null
            || $this->dateTo !== null;
    }

    public function omitsCapitalMovements(): bool
    {
        return $this->year !== null
            || $this->categoryId !== null;
    }

    public function hasFinancialOperationFilters(): bool
    {
        return $this->q !== null
            || $this->year !== null
            || $this->categoryId !== null
            || $this->accountId !== null
            || $this->dateFrom !== null
            || $this->dateTo !== null;
    }

    public function hidesFinancialOperations(): bool
    {
        return $this->termDepositId !== null && ! $this->hasFinancialOperationFilters();
    }

    /**
     * @return list<OperationsListSection>
     */
    public function suppressedSections(): array
    {
        $sections = [];

        if ($this->hidesFinancialOperations()) {
            $sections[] = OperationsListSection::FinancialOperations;
        }

        if ($this->isActive()) {
            $sections[] = OperationsListSection::OwnTransfers;
        }

        if ($this->omitsCapitalMovements()) {
            $sections[] = OperationsListSection::CapitalMovements;
        }

        return $sections;
    }

    /**
     * @return array{q: ?string, year: ?int, category_id: ?int, account_id: ?int, term_deposit_id: ?int, date_from: ?string, date_to: ?string}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->q,
            'year' => $this->year,
            'category_id' => $this->categoryId,
            'account_id' => $this->accountId,
            'term_deposit_id' => $this->termDepositId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }

    /**
     * @param  Builder<\App\Models\FinancialOperation>|Relation<*, *, *>  $query
     * @return Builder<\App\Models\FinancialOperation>|Relation<*, *, *>
     */
    public function apply(Builder|Relation $query): Builder|Relation
    {
        if ($this->q !== null) {
            $like = '%'.$this->escapeLike($this->q).'%';

            $query->where(function (Builder $search) use ($like): void {
                $search
                    ->whereRaw('LOWER(description) LIKE LOWER(?) ESCAPE ?', [$like, '\\'])
                    ->orWhereRaw('LOWER(COALESCE(counterparty, \'\')) LIKE LOWER(?) ESCAPE ?', [$like, '\\'])
                    ->orWhereRaw('LOWER(COALESCE(raw_statement_description, \'\')) LIKE LOWER(?) ESCAPE ?', [$like, '\\']);
            });
        }

        if ($this->year !== null) {
            $query->whereHas('budget', fn (Builder $budget) => $budget->where('year', $this->year));
        }

        if ($this->categoryId !== null) {
            $query->whereHas('budgetLine', fn (Builder $line) => $line->where('category_id', $this->categoryId));
        }

        if ($this->accountId !== null) {
            $query->where('account_id', $this->accountId);
        }

        if ($this->dateFrom !== null) {
            $query->whereDate('operation_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== null) {
            $query->whereDate('operation_date', '<=', $this->dateTo);
        }

        return $query;
    }

    /**
     * @param  Builder<\App\Models\CapitalMovement>|Relation<*, *, *>  $query
     * @return Builder<\App\Models\CapitalMovement>|Relation<*, *, *>
     */
    public function applyToCapitalMovements(Builder|Relation $query): Builder|Relation
    {
        if ($this->termDepositId !== null) {
            $query->where('term_deposit_id', $this->termDepositId);
        }

        if ($this->accountId !== null) {
            $query->where('account_id', $this->accountId);
        }

        if ($this->dateFrom !== null) {
            $query->whereDate('movement_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== null) {
            $query->whereDate('movement_date', '<=', $this->dateTo);
        }

        if ($this->q !== null) {
            $like = '%'.$this->escapeLike($this->q).'%';

            $query->where(function (Builder $search) use ($like): void {
                $search
                    ->whereRaw('LOWER(description) LIKE LOWER(?) ESCAPE ?', [$like, '\\'])
                    ->orWhereRaw('LOWER(COALESCE(raw_statement_description, \'\')) LIKE LOWER(?) ESCAPE ?', [$like, '\\']);
            });
        }

        return $query;
    }

    private function escapeLike(string $term): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            Str::lower($term),
        );
    }
}
