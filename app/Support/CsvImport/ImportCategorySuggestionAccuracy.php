<?php

namespace App\Support\CsvImport;

use App\Models\User;

/**
 * How accurate the Import suggestions would have been had every imported row
 * been imported again, learning only from the rows before it. Reads the history
 * and the targets through the same code as the preview. Read-only.
 *
 * The file format is shared with the "before" script of the measurement
 * (`context/changes/import-suggestions-on-category-list/measure/before.php`).
 */
class ImportCategorySuggestionAccuracy
{
    public const int FORMAT = 1;

    /** @var array<string, array{from: string, to: string}> */
    private const array PERIODS = [
        'year' => ['from' => '01-01', 'to' => '12-31'],
        'aug_nov' => ['from' => '08-01', 'to' => '11-30'],
    ];

    public function __construct(private readonly ImportCategorySuggester $suggester) {}

    public function latestYear(User $user): ?int
    {
        $latest = null;

        foreach ($this->suggester->loadHistory($user) as $entry) {
            $latest = $latest === null || $entry['operation_date'] > $latest ? $entry['operation_date'] : $latest;
        }

        return $latest === null ? null : (int) substr($latest, 0, 4);
    }

    /**
     * @return array{
     *     format: int,
     *     user_id: int,
     *     year: int,
     *     periods: array<string, array{
     *         model: array{rows: int, suggested: int, correct: int, top3: int},
     *         suggestion: array{rows: int, suggested: int, correct: int},
     *     }>,
     *     rows: list<array{
     *         operation_id: int,
     *         operation_date: string,
     *         type: string,
     *         model: array{suggested: bool, correct: bool, top3: bool},
     *         suggestion: array{suggested: bool, correct: bool},
     *     }>,
     * }
     */
    public function measure(User $user, int $year): array
    {
        $history = iterator_to_array($this->suggester->loadHistory($user), false);
        usort($history, fn (array $a, array $b): int => [$a['operation_date'], $a['id']] <=> [$b['operation_date'], $b['id']]);

        $targets = ImportCategorySuggestionTargets::forUser($user);
        $rows = [];

        foreach ($history as $position => $entry) {
            if (! $this->isInPeriod($entry['operation_date'], 'year', $year)) {
                continue;
            }

            $model = ImportCategorySuggestionModel::fromHistory(array_slice($history, 0, $position));
            $rows[] = $this->measureRow($entry, $model, $targets);
        }

        return [
            'format' => self::FORMAT,
            'user_id' => $user->id,
            'year' => $year,
            'periods' => $this->summarise($rows, $year),
            'rows' => $rows,
        ];
    }

    /**
     * Drops are compared as integer fractions, never as rounded percentages. Files
     * that do not hold the same operation ids are not the same data: an error.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{drops: list<string>, rows: list<string>, error: ?string}
     */
    public function differences(array $before, array $after): array
    {
        $beforeRows = $this->rowsById($before['rows'] ?? []);
        $afterRows = $this->rowsById($after['rows'] ?? []);

        if (array_keys($beforeRows) !== array_keys($afterRows)) {
            $missing = count(array_diff_key($beforeRows, $afterRows));
            $extra = count(array_diff_key($afterRows, $beforeRows));

            return [
                'drops' => [],
                'rows' => [],
                'error' => "The files do not hold the same rows ({$missing} only in the first, {$extra} only in the second): not the same data.",
            ];
        }

        $drops = [];

        foreach (array_keys(self::PERIODS) as $period) {
            foreach (['model', 'suggestion'] as $level) {
                $drops = [...$drops, ...$this->drops($period, $level, $before['periods'][$period][$level] ?? [], $after['periods'][$period][$level] ?? [])];
            }
        }

        $rows = [];

        foreach ($beforeRows as $id => $beforeRow) {
            foreach (['model', 'suggestion'] as $level) {
                if ($beforeRow[$level] !== $afterRows[$id][$level]) {
                    $rows[] = "operation {$id} {$level}: ".$this->flags($beforeRow[$level]).' -> '.$this->flags($afterRows[$id][$level]);
                }
            }
        }

        return ['drops' => $drops, 'rows' => $rows, 'error' => null];
    }

    private function isInPeriod(string $date, string $period, int $year): bool
    {
        return $date >= "$year-".self::PERIODS[$period]['from'] && $date <= "$year-".self::PERIODS[$period]['to'];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function drops(string $period, string $level, array $before, array $after): array
    {
        $rowsBefore = (int) ($before['rows'] ?? 0);
        $rowsAfter = (int) ($after['rows'] ?? 0);
        $suggestedBefore = (int) ($before['suggested'] ?? 0);
        $suggestedAfter = (int) ($after['suggested'] ?? 0);
        $correctBefore = (int) ($before['correct'] ?? 0);
        $correctAfter = (int) ($after['correct'] ?? 0);
        $drops = [];

        if ($suggestedAfter * $rowsBefore < $suggestedBefore * $rowsAfter) {
            $drops[] = "{$period} {$level}: rows with a suggestion {$suggestedBefore}/{$rowsBefore} -> {$suggestedAfter}/{$rowsAfter}";
        }

        if ($correctAfter * $suggestedBefore < $correctBefore * $suggestedAfter) {
            $drops[] = "{$period} {$level}: correct among suggested {$correctBefore}/{$suggestedBefore} -> {$correctAfter}/{$suggestedAfter}";
        }

        return $drops;
    }

    /**
     * @param  array{id: int, type: 'income'|'expense', category_id: int, text: string, operation_date: string}  $entry
     * @return array{
     *     operation_id: int,
     *     operation_date: string,
     *     type: string,
     *     model: array{suggested: bool, correct: bool, top3: bool},
     *     suggestion: array{suggested: bool, correct: bool},
     * }
     */
    private function measureRow(array $entry, ImportCategorySuggestionModel $model, ImportCategorySuggestionTargets $targets): array
    {
        $suggested = $model->suggest($entry['type'], $entry['text']);
        $top3 = array_column(array_slice($model->rank($entry['type'], $entry['text']), 0, 3), 'category_id');
        $target = $targets->resolve($entry['type'], $suggested, (int) substr($entry['operation_date'], 0, 4));

        return [
            'operation_id' => $entry['id'],
            'operation_date' => $entry['operation_date'],
            'type' => $entry['type'],
            'model' => [
                'suggested' => $suggested !== null,
                'correct' => $suggested === $entry['category_id'],
                'top3' => in_array($entry['category_id'], $top3, true),
            ],
            'suggestion' => [
                'suggested' => $target !== null,
                'correct' => $target === $entry['category_id'],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{
     *     model: array{rows: int, suggested: int, correct: int, top3: int},
     *     suggestion: array{rows: int, suggested: int, correct: int},
     * }>
     */
    private function summarise(array $rows, int $year): array
    {
        $periods = [];

        foreach (array_keys(self::PERIODS) as $name) {
            $model = ['rows' => 0, 'suggested' => 0, 'correct' => 0, 'top3' => 0];
            $suggestion = ['rows' => 0, 'suggested' => 0, 'correct' => 0];

            foreach ($rows as $row) {
                if (! $this->isInPeriod($row['operation_date'], $name, $year)) {
                    continue;
                }

                $model['rows']++;
                $model['suggested'] += (int) $row['model']['suggested'];
                $model['correct'] += (int) $row['model']['correct'];
                $model['top3'] += (int) $row['model']['top3'];
                $suggestion['rows']++;
                $suggestion['suggested'] += (int) $row['suggestion']['suggested'];
                $suggestion['correct'] += (int) $row['suggestion']['correct'];
            }

            $periods[$name] = ['model' => $model, 'suggestion' => $suggestion];
        }

        return $periods;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rowsById(array $rows): array
    {
        $byId = [];

        foreach ($rows as $row) {
            $byId[(int) $row['operation_id']] = $row;
        }

        ksort($byId);

        return $byId;
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function flags(array $flags): string
    {
        return implode(' ', array_map(fn (string $name, bool $value): string => $name.'='.(int) $value, array_keys($flags), $flags));
    }
}
