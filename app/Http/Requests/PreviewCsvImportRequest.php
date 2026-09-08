<?php

namespace App\Http\Requests;

use App\Enums\ImportFormat;
use App\Support\CsvImport\BnpParibasXlsxParser;
use App\Support\CsvImport\MbankCsvMappingResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class PreviewCsvImportRequest extends FormRequest
{
    /**
     * @var array{
     *     contents: string,
     *     mapping: array{
     *         date_column: int,
     *         description_column: int,
     *         amount_layout: 'signed'|'split',
     *         amount_column?: int,
     *         inflow_column?: int,
     *         outflow_column?: int,
     *         has_header: bool,
     *     },
     * }|null
     */
    private ?array $resolvedImport = null;

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

        $fileRules = ['required', 'file', 'max:5120'];

        if ($this->requestedImportFormat() === ImportFormat::BnpParibas->value) {
            $fileRules[] = 'mimes:xlsx';
        } else {
            $fileRules[] = 'mimes:csv,txt';
        }

        return [
            'import_format' => ['required', Rule::enum(ImportFormat::class)],
            'file' => $fileRules,
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('archived_at'),
            ],
            'has_header' => ['required_if:import_format,other', 'boolean'],
            'date_column' => ['required_if:import_format,other', 'integer', 'min:0'],
            'description_column' => ['required_if:import_format,other', 'integer', 'min:0'],
            'amount_layout' => ['required_if:import_format,other', Rule::in(['signed', 'split'])],
            'amount_column' => ['required_if:amount_layout,signed', 'nullable', 'integer', 'min:0'],
            'inflow_column' => ['required_if:amount_layout,split', 'nullable', 'integer', 'min:0'],
            'outflow_column' => ['required_if:amount_layout,split', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $format = $this->importFormat();
            $extension = strtolower($this->file('file')?->getClientOriginalExtension() ?? '');

            if ($format === ImportFormat::Mbank || $format === ImportFormat::Other) {
                if (in_array($extension, ['xlsx', 'xls'], true)) {
                    $validator->errors()->add('file', __('Only CSV files are supported for this import format.'));

                    return;
                }
            }

            if ($format === ImportFormat::BnpParibas) {
                if ($extension === 'csv' || $extension === 'txt') {
                    $validator->errors()->add('file', __('Only XLSX files are supported for the BNP Paribas import format.'));

                    return;
                }
            }

            try {
                if ($format === ImportFormat::BnpParibas) {
                    $path = $this->file('file')?->getRealPath();

                    if (! is_string($path)) {
                        throw new InvalidArgumentException(__('The uploaded file could not be read.'));
                    }

                    app(BnpParibasXlsxParser::class)->parse($path);
                } else {
                    $this->parsedImport();
                }
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('file', $exception->getMessage());
            }
        });
    }

    private function requestedImportFormat(): ?string
    {
        $format = $this->input('import_format');

        return is_string($format) ? $format : null;
    }

    public function importFormat(): ImportFormat
    {
        return ImportFormat::from($this->string('import_format')->toString());
    }

    /**
     * @return array{
     *     contents: string,
     *     mapping: array{
     *         date_column: int,
     *         description_column: int,
     *         amount_layout: 'signed'|'split',
     *         amount_column?: int,
     *         inflow_column?: int,
     *         outflow_column?: int,
     *         has_header: bool,
     *     },
     * }
     */
    public function parsedImport(): array
    {
        if ($this->resolvedImport !== null) {
            return $this->resolvedImport;
        }

        $contents = $this->file('file')?->get();

        if (! is_string($contents)) {
            throw new InvalidArgumentException(__('The uploaded file could not be read.'));
        }

        if ($this->importFormat() === ImportFormat::Mbank) {
            $this->resolvedImport = app(MbankCsvMappingResolver::class)->resolve($contents);

            return $this->resolvedImport;
        }

        $this->resolvedImport = [
            'contents' => $contents,
            'mapping' => $this->mapping(),
        ];

        return $this->resolvedImport;
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
