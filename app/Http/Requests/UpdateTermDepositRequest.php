<?php

namespace App\Http\Requests;

use App\Concerns\TermDepositValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTermDepositRequest extends FormRequest
{
    use TermDepositValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('term_deposit')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->updateTermDepositRules();
    }
}
