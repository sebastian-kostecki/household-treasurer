<?php

namespace App\Support\CsvImport;

use LogicException;

/**
 * The reference set of the suggestion benchmark: the Import history of one year
 * out of a `user:backup` snapshot, with every word of the bank text replaced by
 * a made-up one, so the set can live in the repository. Rows hold no amounts and
 * no production ids. Categories are named "Kategoria <key>", because the measurement
 * never reads a name, and the keys are numbered in an order drawn from the snapshot
 * itself, because the ids of the production Categories are named in the repository
 * (`database/sheet-load/mapping.php`) and a key equal to the id would name the row.
 *
 * The replacement is one-to-one and the model reads a row as a set of words, so
 * the words of a row are written sorted, which hides the template of the bank
 * text without changing a ranking. Before the set is returned the history is
 * replayed row by row on the original and on the replaced words, and a single
 * different ranking is an error.
 *
 * What stays readable is accepted on purpose (plan of testing-import-integrity,
 * "Ryzyko resztkowe zestawu referencyjnego"): the date, type and category key of
 * each row and how many words it has and shares with other rows.
 */
class ImportCategorySuggestionReference
{
    public const int FORMAT = 1;

    public const int MIN_SNAPSHOT_VERSION = 10;

    private const string LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function __construct(private readonly ImportCategorySuggester $suggester) {}

    /**
     * @param  array<string, mixed>  $snapshot
     *
     * @throws ImportSuggestionReferenceException
     */
    public function latestYear(array $snapshot): ?int
    {
        $this->assertSupportedVersion($snapshot);
        $latest = null;

        foreach ($this->importedOperations($snapshot) as $operation) {
            $latest = max($latest ?? 0, (int) substr($operation['operation_date'], 0, 4));
        }

        return $latest;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array{snapshot: string, sha256: string, created_at: string|null}  $source
     * @return array{
     *     format: int,
     *     source: array{snapshot: string, sha256: string, created_at: string|null},
     *     year: int,
     *     categories: list<array{key: int, name: string, type: string, list_archived: bool, line_archived: bool|null}>,
     *     rows: list<array{key: int, date: string, type: string, text: string, category_key: int}>,
     * }
     *
     * @throws ImportSuggestionReferenceException a snapshot the set cannot be built from
     * @throws LogicException a ranking that differs after the words are replaced
     */
    public function build(array $snapshot, int $year, array $source): array
    {
        $this->assertSupportedVersion($snapshot);

        $operations = array_values(array_filter(
            $this->importedOperations($snapshot),
            fn (array $operation): bool => str_starts_with($operation['operation_date'], "{$year}-"),
        ));

        if ($operations === []) {
            throw new ImportSuggestionReferenceException("The snapshot has no imported rows in {$year}.");
        }

        $categoryKeys = $this->categoryKeys($snapshot, $operations);
        $originalHistory = [];

        foreach ($operations as $position => $operation) {
            $originalHistory[] = $this->suggester->historyEntry(
                $position + 1,
                $operation['type'],
                $categoryKeys[$operation['category_id']],
                $operation['description'],
                $operation['counterparty'],
                $operation['operation_date'],
            );
        }

        $pseudonyms = $this->pseudonyms($originalHistory);
        $rows = [];
        $referenceHistory = [];

        foreach ($originalHistory as $entry) {
            $words = array_map(fn (string $token): string => $pseudonyms[$token], ImportCategorySuggestionModel::tokens($entry['text']));
            sort($words, SORT_STRING);
            $text = implode(' ', $words);
            $rows[] = [
                'key' => $entry['id'],
                'date' => $entry['operation_date'],
                'type' => $entry['type'],
                'text' => $text,
                'category_key' => $entry['category_id'],
            ];
            $referenceHistory[] = [...$entry, 'text' => $text];
        }

        $this->assertSameRankings($originalHistory, $referenceHistory);

        return [
            'format' => self::FORMAT,
            'source' => $source,
            'year' => $year,
            'categories' => $this->categories($snapshot, $categoryKeys, $year),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     *
     * @throws ImportSuggestionReferenceException
     */
    private function assertSupportedVersion(array $snapshot): void
    {
        $version = $snapshot['version'] ?? null;

        if (! is_int($version) || $version < self::MIN_SNAPSHOT_VERSION) {
            throw new ImportSuggestionReferenceException('The snapshot version must be at least '.self::MIN_SNAPSHOT_VERSION.', got '.json_encode($version).'.');
        }
    }

    /**
     * The imported operations the history learns from, in the order of the
     * replay: date, then id. A type or a line the history cannot read is an
     * error, never a guess.
     *
     * @param  array<string, mixed>  $snapshot
     * @return list<array{id: int, type: 'income'|'expense', category_id: int, description: string, counterparty: string|null, operation_date: string}>
     *
     * @throws ImportSuggestionReferenceException
     */
    private function importedOperations(array $snapshot): array
    {
        $categoryByLine = [];

        foreach ($snapshot['budget_lines'] ?? [] as $line) {
            $categoryByLine[$line['id']] = (int) $line['category_id'];
        }

        $operations = [];

        foreach ($snapshot['financial_operations'] ?? [] as $operation) {
            if (! $this->suggester->learnsFrom($operation['raw_statement_description'] ?? null, $operation['description'] ?? null)) {
                continue;
            }

            $type = $operation['type'] ?? null;

            if ($type !== 'income' && $type !== 'expense') {
                throw new ImportSuggestionReferenceException("Operation {$operation['id']} has an unknown type ".json_encode($type).'.');
            }

            $line = $operation['budget_line_id'] ?? null;

            if (! is_int($line) || ! isset($categoryByLine[$line])) {
                throw new ImportSuggestionReferenceException("Operation {$operation['id']} points at a Budget Line outside the snapshot.");
            }

            $operations[] = [
                'id' => (int) $operation['id'],
                'type' => $type,
                'category_id' => $categoryByLine[$line],
                'description' => (string) $operation['description'],
                'counterparty' => $operation['counterparty'] ?? null,
                'operation_date' => substr((string) $operation['operation_date'], 0, 10),
            ];
        }

        usort($operations, fn (array $a, array $b): int => [$a['operation_date'], $a['id']] <=> [$b['operation_date'], $b['id']]);

        return $operations;
    }

    /**
     * Running numbers in an order keyed by the whole snapshot: the same snapshot
     * always gives the same keys, and without the snapshot the order of the ids
     * cannot be rebuilt (the published sha256 of the file is a different hash).
     *
     * @param  array<string, mixed>  $snapshot
     * @param  list<array{category_id: int, ...}>  $operations
     * @return array<int, int> category id => key
     */
    private function categoryKeys(array $snapshot, array $operations): array
    {
        $secret = hash('sha256', 'category-order'.json_encode($snapshot, JSON_THROW_ON_ERROR));
        $ids = array_map(fn (array $category): int => (int) $category['id'], $snapshot['categories'] ?? []);
        usort($ids, fn (int $a, int $b): int => hash_hmac('sha256', (string) $a, $secret) <=> hash_hmac('sha256', (string) $b, $secret));

        $keys = [];

        foreach ($ids as $position => $id) {
            $keys[$id] = $position + 1;
        }

        foreach ($operations as $operation) {
            if (! isset($keys[$operation['category_id']])) {
                throw new ImportSuggestionReferenceException('A row points at a Category outside the snapshot.');
            }
        }

        return $keys;
    }

    /**
     * Every Category of the list, since the suggestion targets look at the whole
     * list. `line_archived` is null when the Budget of the year has no line of it.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, int>  $categoryKeys
     * @return list<array{key: int, name: string, type: string, list_archived: bool, line_archived: bool|null}>
     */
    private function categories(array $snapshot, array $categoryKeys, int $year): array
    {
        $budgetId = null;

        foreach ($snapshot['budgets'] ?? [] as $budget) {
            if ((int) $budget['year'] === $year) {
                $budgetId = $budget['id'];
            }
        }

        $lineArchived = [];

        foreach ($snapshot['budget_lines'] ?? [] as $line) {
            if ($budgetId !== null && $line['budget_id'] === $budgetId) {
                $lineArchived[(int) $line['category_id']] = $line['archived_at'] !== null;
            }
        }

        $categories = [];

        foreach ($snapshot['categories'] ?? [] as $category) {
            $key = $categoryKeys[(int) $category['id']];
            $categories[] = [
                'key' => $key,
                'name' => "Kategoria {$key}",
                'type' => (string) $category['type'],
                'list_archived' => $category['archived_at'] !== null,
                'line_archived' => $lineArchived[(int) $category['id']] ?? null,
            ];
        }

        usort($categories, fn (array $a, array $b): int => $a['key'] <=> $b['key']);

        return $categories;
    }

    /**
     * A made-up word of 3 or more capital letters for each word of the history,
     * numbered in order of first appearance, so the same snapshot always gives
     * the same words. A word is not derived from its original, so it cannot be
     * looked up in a dictionary; an alphabetical numbering would leak letters.
     *
     * @param  list<array{text: string, ...}>  $history
     * @return array<string, string>
     */
    private function pseudonyms(array $history): array
    {
        $pseudonyms = [];

        foreach ($history as $entry) {
            foreach (ImportCategorySuggestionModel::tokens($entry['text']) as $token) {
                $pseudonyms[$token] ??= $this->wordNumber(count($pseudonyms));
            }
        }

        return $pseudonyms;
    }

    private function wordNumber(int $number): string
    {
        $word = '';

        do {
            $word = self::LETTERS[$number % 26].$word;
            $number = intdiv($number, 26);
        } while ($number > 0);

        return str_pad($word, 3, 'A', STR_PAD_LEFT);
    }

    /**
     * Rankings are compared at the precision the model decides with (9 decimals,
     * as in its neighbour order and its tie epsilon): sorted words add the same
     * weights in another order, which moves a score by its last bit only.
     *
     * @param  list<array{id: int, type: 'income'|'expense', category_id: int, text: string, operation_date: string}>  $original
     * @param  list<array{id: int, type: 'income'|'expense', category_id: int, text: string, operation_date: string}>  $reference
     */
    private function assertSameRankings(array $original, array $reference): void
    {
        $atModelPrecision = fn (array $ranking): array => array_map(
            fn (array $vote): array => [$vote['category_id'], round($vote['score'], 9)],
            $ranking,
        );

        foreach ($original as $position => $entry) {
            $before = ImportCategorySuggestionModel::fromHistory(array_slice($original, 0, $position))->rank($entry['type'], $entry['text']);
            $after = ImportCategorySuggestionModel::fromHistory(array_slice($reference, 0, $position))->rank($entry['type'], $reference[$position]['text']);

            if ($atModelPrecision($before) !== $atModelPrecision($after)) {
                throw new LogicException("The ranking of row {$entry['id']} differs after the words are replaced: ".json_encode(['original' => $before, 'reference' => $after]));
            }
        }
    }
}
