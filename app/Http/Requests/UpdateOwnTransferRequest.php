<?php

namespace App\Http\Requests;

use App\Concerns\OwnTransferValidationRules;
use App\Models\OwnTransfer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnTransferRequest extends FormRequest
{
    use OwnTransferValidationRules;

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
        return $this->ownTransferRules();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return $this->ownTransferAfterValidators();
    }
}
