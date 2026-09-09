<?php

namespace App\Http\Requests;

use App\Concerns\CapitalMovementValidationRules;
use App\Models\CapitalMovement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCapitalMovementRequest extends FormRequest
{
    use CapitalMovementValidationRules;

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
        return $this->capitalMovementRules();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return $this->capitalMovementAfterValidators();
    }
}
