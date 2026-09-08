<?php

namespace App\Support\CsvImport;

class ImportRowFields
{
    private const int MAX_FIELD_LENGTH = 255;

    /**
     * @return array{
     *     counterparty: ?string,
     *     description: ?string,
     *     raw_statement_description: ?string,
     * }
     */
    public static function fromMbank(?string $operationDescription): array
    {
        $description = self::truncate($operationDescription);

        return [
            'counterparty' => null,
            'description' => $description,
            'raw_statement_description' => self::trimToNull($operationDescription),
        ];
    }

    /**
     * @return array{
     *     counterparty: ?string,
     *     description: ?string,
     *     raw_statement_description: ?string,
     * }
     */
    public static function fromBnpParibas(?string $sender, ?string $opis): array
    {
        $sender = self::trimToNull($sender);
        $opis = self::trimToNull($opis);

        if ($opis === null) {
            if ($sender === null) {
                return [
                    'counterparty' => null,
                    'description' => null,
                    'raw_statement_description' => null,
                ];
            }

            return [
                'counterparty' => self::truncate($sender),
                'description' => self::truncate($sender),
                'raw_statement_description' => $sender,
            ];
        }

        return [
            'counterparty' => self::truncate($sender),
            'description' => self::truncate($opis),
            'raw_statement_description' => self::joinSnapshotParts($sender, $opis),
        ];
    }

    /**
     * @return array{
     *     counterparty: ?string,
     *     description: ?string,
     *     raw_statement_description: ?string,
     * }
     */
    public static function fromOther(?string $descriptionCell, ?string $counterpartyCell = null): array
    {
        $descriptionCell = self::trimToNull($descriptionCell);
        $counterpartyCell = self::trimToNull($counterpartyCell);

        $rawParts = [];

        if ($descriptionCell !== null) {
            $rawParts[] = $descriptionCell;
        }

        if ($counterpartyCell !== null) {
            $rawParts[] = $counterpartyCell;
        }

        return [
            'counterparty' => self::truncate($counterpartyCell),
            'description' => self::truncate($descriptionCell),
            'raw_statement_description' => $rawParts === [] ? null : implode("\n", $rawParts),
        ];
    }

    private static function joinSnapshotParts(?string $sender, ?string $opis): ?string
    {
        $parts = array_values(array_filter(
            [$sender, $opis],
            static fn (?string $value): bool => $value !== null,
        ));

        if ($parts === []) {
            return null;
        }

        return implode("\n", $parts);
    }

    private static function truncate(?string $value): ?string
    {
        $value = self::trimToNull($value);

        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, self::MAX_FIELD_LENGTH);
    }

    private static function trimToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
