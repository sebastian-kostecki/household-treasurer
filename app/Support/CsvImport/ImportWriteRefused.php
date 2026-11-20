<?php

namespace App\Support\CsvImport;

use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Reported (not thrown) when the import write refuses a row or a pair that the import preview
 * showed as valid. The user sees the unchanged validation message; this event is what lets a
 * responder see that "preview OK, write refused" happened, and for which ids (observability
 * audit 2026-11-19, IMP-2). It carries ids and rule names only, never amounts or descriptions.
 */
class ImportWriteRefused extends RuntimeException
{
    /**
     * @param  'row'|'own_transfer_pair'|'financial_operation_pair'  $stage
     * @param  list<string>  $rule  the failed validator field keys, or one fixed rule name
     * @param  array<string, int|null>  $relatedIds  e.g. category_id, term_deposit_id, own_transfer_id; null entries are dropped
     */
    public function __construct(
        private readonly string $stage,
        private readonly array $rule,
        private readonly int $accountId,
        private readonly int $year,
        private readonly ?int $rowIndex = null,
        private readonly ?string $rowType = null,
        private readonly array $relatedIds = [],
    ) {
        parent::__construct(sprintf(
            'Import write refused at stage %s for account %d, year %d: %s.',
            $stage,
            $accountId,
            $year,
            implode(', ', $rule),
        ));
    }

    /**
     * Reports the refusal of a row the preview showed as valid and returns the unchanged
     * validation message for the user.
     *
     * @param  array{index: int, operation_date: string, type: string}  $row
     * @param  array<int|string>  $ruleKeys
     * @param  array<string, int|null>  $relatedIds
     */
    public static function reportRowRefusal(array $row, int $accountId, array $ruleKeys, string $message, array $relatedIds): ValidationException
    {
        report(new self(
            'row',
            array_map(strval(...), array_values($ruleKeys)),
            $accountId,
            (int) substr($row['operation_date'], 0, 4),
            $row['index'],
            $row['type'],
            $relatedIds,
        ));

        return ValidationException::withMessages(['rows' => $message]);
    }

    /**
     * The user submitted nothing to import: a plain validation message, never reported.
     *
     * @param  array<int, mixed>  $rows
     */
    public static function ensureRowsSelected(array $rows): void
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'rows' => __('Select at least one row to import.'),
            ]);
        }
    }

    /**
     * Does nothing for a passing import validator check. For failed checks it reports the
     * refusal, with the failed field keys as the rule, and throws the validation message the
     * user sees (the first one).
     *
     * @param  array{index: int, operation_date: string, type: string}  $row
     * @param  array<string, int|null>  $relatedIds
     */
    public static function refuseFailed(array $row, int $accountId, MessageBag $errors, array $relatedIds): void
    {
        if ($errors->isEmpty()) {
            return;
        }

        throw self::reportRowRefusal($row, $accountId, $errors->keys(), $errors->all()[0], $relatedIds);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'stage' => $this->stage,
            'rule' => $this->rule,
            'row_index' => $this->rowIndex,
            'row_type' => $this->rowType,
            'account_id' => $this->accountId,
            'year' => $this->year,
            ...array_filter($this->relatedIds, fn (?int $id): bool => $id !== null),
        ];
    }
}
