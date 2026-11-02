<?php

namespace App\Http\Requests;

use App\Models\Budget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMonthPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        if (! $budget instanceof Budget) {
            return false;
        }

        return $this->user()?->can('manageMonthPlan', $budget) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.category_id' => ['required', 'integer', 'distinct'],
            'items.*.amount_minor_units' => ['required', 'integer', 'min:0', 'max:1000000000000'],
        ];
    }
}
