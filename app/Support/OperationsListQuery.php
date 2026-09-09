<?php

namespace App\Support;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class OperationsListQuery
{
    public function __construct(
        public ?string $q,
        public ?int $year,
        public ?string $categoryName,
        public ?string $categoryType,
        public ?int $accountId,
        public ?int $termDepositId,
        public ?string $dateFrom,
        public ?string $dateTo,
    ) {}

    /**
     * @param  array{q?: ?string, year?: int|string|null, category_name?: ?string, category_type?: ?string, account_id?: int|string|null, term_deposit_id?: int|string|null, date_from?: ?string, date_to?: ?string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $year = $validated['year'] ?? null;
        $accountId = $validated['account_id'] ?? null;
        $termDepositId = $validated['term_deposit_id'] ?? null;

        return new self(
            q: $validated['q'] ?? null,
            year: $year === null ? null : (int) $year,
            categoryName: $validated['category_name'] ?? null,
            categoryType: $validated['category_type'] ?? null,
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
            || $this->categoryName !== null
            || $this->accountId !== null
            || $this->termDepositId !== null
            || $this->dateFrom !== null
            || $this->dateTo !== null;
    }

    public function omitsCapitalMovements(): bool
    {
        return $this->year !== null
            || $this->categoryName !== null
            || $this->categoryType !== null;
    }

    public function hasFinancialOperationFilters(): bool
    {
        return $this->q !== null
            || $this->year !== null
            || $this->categoryName !== null
            || $this->categoryType !== null
            || $this->accountId !== null
            || $this->dateFrom !== null
            || $this->dateTo !== null;
    }

    public function hidesFinancialOperations(): bool
    {
        return $this->termDepositId !== null && ! $this->hasFinancialOperationFilters();
    }

    /**
     * @return array{q: ?string, year: ?int, category_name: ?string, category_type: ?string, account_id: ?int, term_deposit_id: ?int, date_from: ?string, date_to: ?string}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->q,
            'year' => $this->year,
            'category_name' => $this->categoryName,
            'category_type' => $this->categoryType,
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

        if ($this->categoryName !== null && $this->categoryType !== null) {
            $type = CategoryType::from($this->categoryType);

            $query->whereHas('category', function (Builder $category) use ($type): void {
                $category
                    ->where('name', $this->categoryName)
                    ->where('type', $type);
            });
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
