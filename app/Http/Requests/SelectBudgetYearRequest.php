<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectBudgetYearRequest extends FormRequest
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
                Rule::exists('budgets', 'year')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
        ];
    }
}
