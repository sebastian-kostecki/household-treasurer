<?php

namespace App\Support\CsvImport;

use InvalidArgumentException;

class MbankCsvMappingResolver
{
    private const DATE_HEADER = 'data operacji';

    private const DESCRIPTION_HEADER = 'opis operacji';

    private const AMOUNT_HEADER = 'kwota';

    /**
     * @return array{
     *     contents: string,
     *     mapping: array{
     *         date_column: int,
     *         description_column: int,
     *         amount_layout: 'signed',
     *         amount_column: int,
     *         has_header: bool,
     *     },
     * }
     */
    public function resolve(string $contents): array
    {
        $lines = $this->splitLines($contents);

        foreach ($lines as $lineIndex => $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '') {
                continue;
            }

            $delimiter = $this->detectDelimiter($trimmedLine);
            $cells = str_getcsv($trimmedLine, $delimiter, '"', '\\');
            $normalizedHeaders = array_map($this->normalizeHeader(...), $cells);

            $dateColumn = array_search(self::DATE_HEADER, $normalizedHeaders, true);

            if ($dateColumn === false) {
                continue;
            }

            $descriptionColumn = array_search(self::DESCRIPTION_HEADER, $normalizedHeaders, true);
            $amountColumn = array_search(self::AMOUNT_HEADER, $normalizedHeaders, true);

            if ($descriptionColumn === false || $amountColumn === false) {
                continue;
            }

            return [
                'contents' => implode("\n", array_slice($lines, $lineIndex)),
                'mapping' => [
                    'date_column' => $dateColumn,
                    'description_column' => $descriptionColumn,
                    'amount_layout' => 'signed',
                    'amount_column' => $amountColumn,
                    'has_header' => true,
                ],
            ];
        }

        throw new InvalidArgumentException(__('The mBank export header was not found in the file.'));
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

    private function normalizeHeader(string $cell): string
    {
        $cell = trim($cell);
        $cell = ltrim($cell, '#');

        return mb_strtolower(trim($cell));
    }
}
