<?php

namespace App\Http\Requests;

use App\Models\Budget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBudgetRequest extends FormRequest
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
        return [
            'year' => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique('budgets', 'year')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            'source_budget_id' => [
                'nullable',
                'integer',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('source_budget_id') === '') {
            $this->merge(['source_budget_id' => null]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sourceBudgetId = $this->input('source_budget_id');

            if ($sourceBudgetId === null) {
                return;
            }

            $year = $this->integer('year');

            $sourceBudget = Budget::query()
                ->whereKey($sourceBudgetId)
                ->where('user_id', $this->user()?->id)
                ->first();

            if ($sourceBudget === null) {
                $validator->errors()->add('source_budget_id', __('The selected source budget is invalid.'));

                return;
            }

            if ($sourceBudget->year >= $year) {
                $validator->errors()->add('source_budget_id', __('The selected source budget is invalid.'));

                return;
            }

            if (! $sourceBudget->budgetLines()->whereNull('archived_at')->exists()) {
                $validator->errors()->add('source_budget_id', __('The selected source budget is invalid.'));
            }
        });
    }
}
