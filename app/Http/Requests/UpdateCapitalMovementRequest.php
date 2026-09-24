<?php

namespace App\Http\Requests;

use App\Concerns\CapitalMovementValidationRules;
use App\Concerns\OperationRebuildValidationRules;
use App\Enums\CapitalMovementDirection;
use App\Models\CapitalMovement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCapitalMovementRequest extends FormRequest
{
    use CapitalMovementValidationRules, OperationRebuildValidationRules;

    public function authorize(): bool
    {
        $capitalMovement = $this->route('capital_movement');

        if (! $capitalMovement instanceof CapitalMovement) {
            return false;
        }

        return $this->user()?->can('update', $capitalMovement) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->boolean('convert_to_income')) {
            return $this->capitalMovementToIncomeRules();
        }

        if ($this->boolean('convert_to_expense')) {
            return $this->capitalMovementToExpenseRules();
        }

        if ($this->boolean('convert_to_own_transfer')) {
            return $this->capitalMovementToOwnTransferRules();
        }

        return $this->capitalMovementRules();
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->boolean('convert_to_income')) {
            $this->withCapitalMovementToIncomeValidation($validator);

            return;
        }

        if ($this->boolean('convert_to_expense')) {
            $this->withCapitalMovementToExpenseValidation($validator);

            return;
        }

        if ($this->boolean('convert_to_own_transfer')) {
            $this->withCapitalMovementToOwnTransferValidation($validator);

            return;
        }

        foreach ($this->capitalMovementAfterValidators() as $afterValidator) {
            $validator->after($afterValidator);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        if ($this->boolean('convert_to_income')) {
            return $this->incomeConversionPayload();
        }

        if ($this->boolean('convert_to_expense')) {
            return $this->expenseConversionPayload();
        }

        if ($this->boolean('convert_to_own_transfer')) {
            $capitalMovement = $this->route('capital_movement');

            if (! $capitalMovement instanceof CapitalMovement) {
                return [];
            }

            return $this->ownTransferConversionPayload($capitalMovement->direction);
        }

        return $this->capitalMovementPayload();
    }

    /**
     * @return array{
     *     account_id: int,
     *     term_deposit_id: int,
     *     movement_date: string,
     *     amount_minor_units: int,
     *     direction: CapitalMovementDirection,
     *     description: string,
     * }
     */
    public function capitalMovementPayload(): array
    {
        $validated = $this->safe()->only([
            'account_id',
            'term_deposit_id',
            'movement_date',
            'amount_minor_units',
            'direction',
            'description',
        ]);

        return [
            'account_id' => (int) $validated['account_id'],
            'term_deposit_id' => (int) $validated['term_deposit_id'],
            'movement_date' => (string) $validated['movement_date'],
            'amount_minor_units' => (int) $validated['amount_minor_units'],
            'direction' => CapitalMovementDirection::from((string) $validated['direction']),
            'description' => (string) $validated['description'],
        ];
    }
}
