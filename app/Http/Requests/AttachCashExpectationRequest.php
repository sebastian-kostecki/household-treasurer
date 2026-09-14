<?php

namespace App\Http\Requests;

use App\Models\CashExpectation;
use App\Models\FinancialOperation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachCashExpectationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cashExpectation = $this->route('cash_expectation');

        return $cashExpectation instanceof CashExpectation
            && $this->user()?->can('update', $cashExpectation) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'financial_operation_id' => [
                'required',
                'integer',
                Rule::exists('financial_operations', 'id')->where('user_id', $userId),
            ],
        ];
    }

    public function financialOperation(): FinancialOperation
    {
        return FinancialOperation::query()->findOrFail($this->integer('financial_operation_id'));
    }
}
