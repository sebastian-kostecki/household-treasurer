<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewCsvImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('archived_at'),
            ],
            'has_header' => ['required', 'boolean'],
            'date_column' => ['required', 'integer', 'min:0'],
            'description_column' => ['required', 'integer', 'min:0'],
            'amount_layout' => ['required', Rule::in(['signed', 'split'])],
            'amount_column' => ['required_if:amount_layout,signed', 'nullable', 'integer', 'min:0'],
            'inflow_column' => ['required_if:amount_layout,split', 'nullable', 'integer', 'min:0'],
            'outflow_column' => ['required_if:amount_layout,split', 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{
     *     date_column: int,
     *     description_column: int,
     *     amount_layout: 'signed'|'split',
     *     amount_column?: int,
     *     inflow_column?: int,
     *     outflow_column?: int,
     *     has_header: bool,
     * }
     */
    public function mapping(): array
    {
        $mapping = [
            'date_column' => (int) $this->input('date_column'),
            'description_column' => (int) $this->input('description_column'),
            'amount_layout' => $this->string('amount_layout')->toString(),
            'has_header' => $this->boolean('has_header'),
        ];

        if ($mapping['amount_layout'] === 'signed') {
            $mapping['amount_column'] = (int) $this->input('amount_column');
        } else {
            $mapping['inflow_column'] = (int) $this->input('inflow_column');
            $mapping['outflow_column'] = (int) $this->input('outflow_column');
        }

        return $mapping;
    }
}
