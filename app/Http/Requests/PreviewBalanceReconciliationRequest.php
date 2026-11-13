<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The day of a Balance Reconciliation (ADR-0091): a date that is not in the future and not
 * before the Account opening date. The dialog reads the preview of that day before saving.
 */
class PreviewBalanceReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reconcile', $this->route('account')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reconciled_on' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $day = $this->input('reconciled_on');

                if ($validator->errors()->isNotEmpty() || ! is_string($day)) {
                    return;
                }

                /** @var Account $account */
                $account = $this->route('account');

                if ($day > now()->toDateString()) {
                    $validator->errors()->add('reconciled_on', __('The reconciliation date cannot be in the future.'));
                } elseif ($day < $account->opening_date->toDateString()) {
                    $validator->errors()->add('reconciled_on', __('The reconciliation date cannot be earlier than the account opening date.'));
                }
            },
        ];
    }
}
