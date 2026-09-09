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
        public ?string $dateFrom,
        public ?string $dateTo,
    ) {}

    /**
     * @param  array{q?: ?string, year?: int|string|null, category_name?: ?string, category_type?: ?string, account_id?: int|string|null, date_from?: ?string, date_to?: ?string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $year = $validated['year'] ?? null;
        $accountId = $validated['account_id'] ?? null;

        return new self(
            q: $validated['q'] ?? null,
            year: $year === null ? null : (int) $year,
            categoryName: $validated['category_name'] ?? null,
            categoryType: $validated['category_type'] ?? null,
            accountId: $accountId === null ? null : (int) $accountId,
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
            || $this->dateFrom !== null
            || $this->dateTo !== null;
    }

    /**
     * @return array{q: ?string, year: ?int, category_name: ?string, category_type: ?string, account_id: ?int, date_from: ?string, date_to: ?string}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->q,
            'year' => $this->year,
            'category_name' => $this->categoryName,
            'category_type' => $this->categoryType,
            'account_id' => $this->accountId,
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

    private function escapeLike(string $term): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            Str::lower($term),
        );
    }
}
