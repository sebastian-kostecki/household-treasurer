<?php

namespace App\Support\CsvImport;

use InvalidArgumentException;

/**
 * @phpstan-type CsvImportMapping array{
 *     date_column: int,
 *     description_column: int,
 *     amount_layout: 'signed',
 *     amount_column: int,
 *     counterparty_column?: int,
 *     has_header: bool,
 * }|array{
 *     date_column: int,
 *     description_column: int,
 *     amount_layout: 'split',
 *     inflow_column: int,
 *     outflow_column: int,
 *     counterparty_column?: int,
 *     has_header: bool,
 * }
 */
class CsvImportParser
{
    public const MAX_ROWS = 5000;

    /**
     * @param  CsvImportMapping  $mapping
     * @return array<int, array{
     *     index: int,
     *     operation_date: ?string,
     *     description: ?string,
     *     counterparty: ?string,
     *     raw_statement_description: ?string,
     *     amount_grosze: ?int,
     *     amount_has_foreign_currency: bool,
     * }>
     */
    public function parse(string $contents, array $mapping): array
    {
        $lines = $this->splitLines($contents);

        if ($lines === []) {
            return [];
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $rows = [];
        $startIndex = $mapping['has_header'] ? 1 : 0;

        for ($lineIndex = $startIndex; $lineIndex < count($lines); $lineIndex++) {
            $line = trim($lines[$lineIndex]);

            if ($line === '') {
                continue;
            }

            $cells = str_getcsv($line, $delimiter, '"', '\\');
            $rowIndex = count($rows);

            $amount = $this->parseAmount($cells, $mapping);
            $descriptionCell = $this->cell($cells, $mapping['description_column']);
            $fields = array_key_exists('counterparty_column', $mapping)
                ? ImportRowFields::fromOther(
                    $descriptionCell,
                    $this->cell($cells, (int) $mapping['counterparty_column']),
                )
                : ImportRowFields::fromMbank($descriptionCell);

            $rows[] = [
                'index' => $rowIndex,
                'operation_date' => $this->parseDate($this->cell($cells, $mapping['date_column'])),
                'description' => $fields['description'],
                'counterparty' => $fields['counterparty'],
                'raw_statement_description' => $fields['raw_statement_description'],
                'amount_grosze' => $amount['amount_grosze'],
                'amount_has_foreign_currency' => $amount['amount_has_foreign_currency'],
            ];

            if (count($rows) > self::MAX_ROWS) {
                throw new InvalidArgumentException(__('CSV files can contain at most :max rows.', [
                    'max' => self::MAX_ROWS,
                ]));
            }
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function splitLines(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        return preg_split('/\R/', $contents) ?: [];
    }

    private function detectDelimiter(string $line): string
    {
        $commaCount = substr_count($line, ',');
        $semicolonCount = substr_count($line, ';');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function cell(array $cells, int $column): ?string
    {
        if (! array_key_exists($column, $cells)) {
            return null;
        }

        $value = trim($cells[$column]);

        return $value === '' ? null : $value;
    }

    private function parseDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $this->validDate($value) ? $value : null;
        }

        if (preg_match('#^(\d{1,2})[./](\d{1,2})[./](\d{4})$#', $value, $matches) === 1) {
            $date = sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);

            return $this->validDate($date) ? $date : null;
        }

        return null;
    }

    private function validDate(string $value): bool
    {
        [$year, $month, $day] = array_map(intval(...), explode('-', $value));

        return checkdate($month, $day, $year);
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array{
     *     amount_layout: 'signed',
     *     amount_column: int,
     * }|array{
     *     amount_layout: 'split',
     *     inflow_column: int,
     *     outflow_column: int,
     * }  $mapping
     * @return array{amount_grosze: ?int, amount_has_foreign_currency: bool}
     */
    private function parseAmount(array $cells, array $mapping): array
    {
        if ($mapping['amount_layout'] === 'signed') {
            return $this->parseAmountCell($this->cell($cells, $mapping['amount_column']));
        }

        $inflow = $this->parseAmountCell($this->cell($cells, $mapping['inflow_column']));
        $outflow = $this->parseAmountCell($this->cell($cells, $mapping['outflow_column']));

        if ($inflow['amount_has_foreign_currency'] || $outflow['amount_has_foreign_currency']) {
            return [
                'amount_grosze' => null,
                'amount_has_foreign_currency' => true,
            ];
        }

        if ($inflow['amount_grosze'] !== null && $outflow['amount_grosze'] !== null) {
            return [
                'amount_grosze' => null,
                'amount_has_foreign_currency' => false,
            ];
        }

        if ($inflow['amount_grosze'] !== null) {
            return $inflow;
        }

        if ($outflow['amount_grosze'] !== null) {
            return [
                'amount_grosze' => -abs($outflow['amount_grosze']),
                'amount_has_foreign_currency' => false,
            ];
        }

        return [
            'amount_grosze' => null,
            'amount_has_foreign_currency' => false,
        ];
    }

    /**
     * @return array{amount_grosze: ?int, amount_has_foreign_currency: bool}
     */
    private function parseAmountCell(?string $value): array
    {
        if ($value === null) {
            return [
                'amount_grosze' => null,
                'amount_has_foreign_currency' => false,
            ];
        }

        if ($this->hasForeignCurrency($value)) {
            return [
                'amount_grosze' => null,
                'amount_has_foreign_currency' => true,
            ];
        }

        return [
            'amount_grosze' => $this->parseMoney($value),
            'amount_has_foreign_currency' => false,
        ];
    }

    private function hasForeignCurrency(string $value): bool
    {
        if (preg_match('/(?:^|\s)(PLN|zł)(?:\s|$)/ui', $value) === 1) {
            return false;
        }

        if (preg_match('/\b([A-Z]{3})\b/i', $value, $matches) !== 1) {
            return false;
        }

        return strtoupper($matches[1]) !== 'PLN';
    }

    private function parseMoney(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        $normalized = preg_replace('/(?:PLN|zł)$/ui', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);
        $normalized = str_replace(',', '.', $normalized);

        if (! preg_match('/^-?\d+(\.\d{0,2})?$/', $normalized)) {
            return null;
        }

        $negative = str_starts_with($normalized, '-');
        $absolute = ltrim($normalized, '-');
        [$zlPart, $grPart] = array_pad(explode('.', $absolute, 2), 2, '0');
        $grosze = ((int) $zlPart * 100) + (int) substr(str_pad($grPart, 2, '0'), 0, 2);

        return $negative ? -$grosze : $grosze;
    }
}
