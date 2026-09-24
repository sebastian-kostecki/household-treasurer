<?php

namespace App\Http\Requests;

use App\Concerns\OperationRebuildValidationRules;
use App\Concerns\OwnTransferValidationRules;
use App\Models\OwnTransfer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnTransferRequest extends FormRequest
{
    use OperationRebuildValidationRules, OwnTransferValidationRules;

    public function authorize(): bool
    {
        $ownTransfer = $this->route('own_transfer');

        if (! $ownTransfer instanceof OwnTransfer) {
            return false;
        }

        return $this->user()?->can('update', $ownTransfer) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->boolean('convert_to_capital_movement')) {
            return $this->ownTransferToCapitalMovementRules();
        }

        return $this->ownTransferRules();
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->boolean('convert_to_capital_movement')) {
            $this->withOwnTransferToCapitalMovementValidation($validator);

            return;
        }

        foreach ($this->ownTransferAfterValidators() as $afterValidator) {
            $validator->after($afterValidator);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        if ($this->boolean('convert_to_capital_movement')) {
            return $this->capitalMovementConversionPayload('transfer_date');
        }

        return $this->ownTransferPayload();
    }

    /**
     * @return array{
     *     source_account_id: int,
     *     destination_account_id: int,
     *     transfer_date: string,
     *     amount_minor_units: int,
     *     description: string,
     * }
     */
    public function ownTransferPayload(): array
    {
        $validated = $this->safe()->only([
            'source_account_id',
            'destination_account_id',
            'transfer_date',
            'amount_minor_units',
            'description',
        ]);

        return [
            'source_account_id' => (int) $validated['source_account_id'],
            'destination_account_id' => (int) $validated['destination_account_id'],
            'transfer_date' => (string) $validated['transfer_date'],
            'amount_minor_units' => (int) $validated['amount_minor_units'],
            'description' => (string) $validated['description'],
        ];
    }
}
