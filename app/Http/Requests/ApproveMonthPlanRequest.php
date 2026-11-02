<?php

namespace App\Http\Requests;

use App\Models\Budget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveMonthPlanRequest extends FormRequest
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
            'set_aside_minor_units' => ['required', 'integer', 'min:-1000000000000', 'max:1000000000000'],
            'items' => ['sometimes', 'array'],
            'items.*.category_id' => ['required', 'integer', 'distinct'],
            'items.*.amount_minor_units' => ['required', 'integer', 'min:0', 'max:1000000000000'],
        ];
    }
}
