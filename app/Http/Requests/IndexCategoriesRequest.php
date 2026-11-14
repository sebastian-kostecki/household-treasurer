<?php

namespace App\Http\Requests;

use App\Enums\CategoriesListTab;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCategoriesRequest extends FormRequest
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
            'tab' => ['nullable', Rule::enum(CategoriesListTab::class)],
        ];
    }

    public function tab(): CategoriesListTab
    {
        $tab = $this->validated('tab');

        return $tab === null
            ? CategoriesListTab::Expense
            : CategoriesListTab::from($tab);
    }

    protected function prepareForValidation(): void
    {
        $tab = $this->input('tab');

        $this->merge([
            'tab' => $tab === '' ? null : $tab,
        ]);
    }
}
