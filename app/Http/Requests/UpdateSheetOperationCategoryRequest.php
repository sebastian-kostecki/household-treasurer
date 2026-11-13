<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Models\FinancialOperation;
use App\Support\CategoryList\OperationCategoryChoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The Category change of an operation of a Sheet Account (ADR-0089): only
 * `category_id`, of the operation's type, checked in the operation's year.
 */
class UpdateSheetOperationCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $financialOperation = $this->route('financial_operation');

        return $financialOperation instanceof FinancialOperation
            && $this->user()?->can('changeCategory', $financialOperation) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $operation = $this->route('financial_operation');

            if (! $operation instanceof FinancialOperation) {
                return;
            }

            $message = OperationCategoryChoice::checkSheetRecategorisation(
                $operation->user,
                $this->categoryId(),
                $operation->type === FinancialOperationType::Income ? CategoryType::Income : CategoryType::Expense,
                $operation->operation_date->year,
                $operation->budgetLine->category_id,
            );

            if ($message !== null) {
                $validator->errors()->add('category_id', $message);
            }
        });
    }

    public function categoryId(): int
    {
        return $this->integer('category_id');
    }
}
