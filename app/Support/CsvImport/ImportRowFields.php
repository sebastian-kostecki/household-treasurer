<?php

namespace App\Support\CsvImport;

class ImportRowFields
{
    private const int MAX_FIELD_LENGTH = 255;

    /**
     * Stored as the live description when the statement row carries no usable text
     * (bank exports sometimes leave both the description and the counterparty empty).
     */
    public const string EMPTY_DESCRIPTION_FALLBACK = 'Brak opisu';

    /**
     * @return array{
     *     counterparty: ?string,
     *     description: ?string,
     *     raw_statement_description: ?string,
     * }
     */
    public static function fromMbank(?string $operationDescription): array
    {
        $rawStatementDescription = self::trimToNull($operationDescription);

        return self::finalizeLiveFields(
            descriptionSource: $operationDescription,
            counterpartySource: null,
            rawStatementDescription: $rawStatementDescription,
        );
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

            return self::finalizeLiveFields(
                descriptionSource: null,
                counterpartySource: $sender,
                rawStatementDescription: $sender,
            );
        }

        return self::finalizeLiveFields(
            descriptionSource: $opis,
            counterpartySource: $sender,
            rawStatementDescription: self::joinSnapshotParts($sender, $opis),
        );
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

        return self::finalizeLiveFields(
            descriptionSource: $descriptionCell,
            counterpartySource: $counterpartyCell,
            rawStatementDescription: $rawParts === [] ? null : implode("\n", $rawParts),
        );
    }

    /**
     * @return array{description: ?string, counterparty: ?string}
     */
    public static function omitLiveFields(?string $description, ?string $counterparty): array
    {
        $omitter = self::omitter();
        $counterparty = self::truncate($omitter->omit($counterparty));
        $description = self::truncate($omitter->omit($description));

        if ($description === null && $counterparty !== null) {
            $description = $counterparty;
        }

        return [
            'description' => $description,
            'counterparty' => $counterparty,
        ];
    }

    /**
     * @return array{
     *     counterparty: ?string,
     *     description: ?string,
     *     raw_statement_description: ?string,
     * }
     */
    private static function finalizeLiveFields(
        ?string $descriptionSource,
        ?string $counterpartySource,
        ?string $rawStatementDescription,
    ): array {
        $liveFields = self::omitLiveFields($descriptionSource, $counterpartySource);

        return [
            'counterparty' => $liveFields['counterparty'],
            'description' => $liveFields['description'],
            'raw_statement_description' => $rawStatementDescription,
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

    private static function omitter(): PaymentInstrumentNumberOmitter
    {
        return app(PaymentInstrumentNumberOmitter::class);
    }
}
