<?php

namespace App\Http\Requests;

use App\Concerns\TermDepositValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTermDepositRequest extends FormRequest
{
    use TermDepositValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->storeTermDepositRules();
    }
}
