<?php

namespace App\Http\Requests;

use App\Enums\OperationsListSection;
use App\Support\OperationsListQuery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOperationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::listRules($this->user()?->id);
    }

    /**
     * The rules of the list state, shared with the redirect back to the list after a write.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function listRules(?int $userId): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'term_deposit_id' => [
                'nullable',
                'integer',
                Rule::exists('term_deposits', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'section' => ['nullable', Rule::enum(OperationsListSection::class)],
        ];
    }

    public function filters(): OperationsListQuery
    {
        return OperationsListQuery::fromValidated($this->safe()->only(OperationsListQuery::FILTER_KEYS));
    }

    public function section(): OperationsListSection
    {
        $section = $this->validated('section');

        return $section === null
            ? OperationsListSection::FinancialOperations
            : OperationsListSection::from($section);
    }

    protected function prepareForValidation(): void
    {
        $q = $this->input('q');

        $this->merge([
            'q' => is_string($q) ? (trim($q) === '' ? null : trim($q)) : null,
            'year' => $this->blankToNull('year'),
            'category_id' => $this->blankToNull('category_id'),
            'account_id' => $this->blankToNull('account_id'),
            'term_deposit_id' => $this->blankToNull('term_deposit_id'),
            'date_from' => $this->blankToNull('date_from'),
            'date_to' => $this->blankToNull('date_to'),
            'section' => $this->blankToNull('section'),
        ]);
    }

    private function blankToNull(string $key): mixed
    {
        $value = $this->input($key);

        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }
}
