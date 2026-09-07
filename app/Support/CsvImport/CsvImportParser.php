<?php

namespace App\Support\CsvImport;

use InvalidArgumentException;

class CsvImportParser
{
    public const MAX_ROWS = 5000;

    /**
     * @param  array{
     *     date_column: int,
     *     description_column: int,
     *     amount_layout: 'signed'|'split',
     *     amount_column?: int,
     *     inflow_column?: int,
     *     outflow_column?: int,
     *     has_header: bool,
     * }  $mapping
     * @return array<int, array{
     *     index: int,
     *     operation_date: ?string,
     *     description: ?string,
     *     amount_grosze: ?int,
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

            $rows[] = [
                'index' => $rowIndex,
                'operation_date' => $this->parseDate($this->cell($cells, $mapping['date_column'])),
                'description' => $this->parseDescription($this->cell($cells, $mapping['description_column'])),
                'amount_grosze' => $this->parseAmount($cells, $mapping),
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

    private function parseDescription(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, 255);
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array{
     *     amount_layout: 'signed'|'split',
     *     amount_column?: int,
     *     inflow_column?: int,
     *     outflow_column?: int,
     * }  $mapping
     */
    private function parseAmount(array $cells, array $mapping): ?int
    {
        if ($mapping['amount_layout'] === 'signed') {
            return $this->parseMoney($this->cell($cells, (int) $mapping['amount_column']));
        }

        $inflow = $this->parseMoney($this->cell($cells, (int) $mapping['inflow_column']));
        $outflow = $this->parseMoney($this->cell($cells, (int) $mapping['outflow_column']));

        if ($inflow !== null && $outflow !== null) {
            return null;
        }

        if ($inflow !== null) {
            return $inflow;
        }

        if ($outflow !== null) {
            return -abs($outflow);
        }

        return null;
    }

    private function parseMoney(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        $normalized = preg_replace('/zł$/ui', '', $normalized) ?? $normalized;
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
