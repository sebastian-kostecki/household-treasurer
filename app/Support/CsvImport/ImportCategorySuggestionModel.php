<?php

namespace App\Support\CsvImport;

/**
 * Word-weight model behind Import category suggestions. Pure PHP, no database.
 *
 * A word weighs 1 / k², where k is the number of distinct (type, category name)
 * pairs it appeared with in the history, so card-payment noise weighs about 0
 * and a shop name seen with one category weighs 1.
 */
class ImportCategorySuggestionModel
{
    public const int NEIGHBOURS = 5;

    public const float MIN_SIMILARITY = 0.25;

    public const float WEIGHT_FLOOR = 0.05;

    private const float VOTE_EPSILON = 1e-9;

    /**
     * @param  array<string, float>  $weights
     * @param  array<string, array<string, list<int>>>  $index  type => token => entry positions
     * @param  list<array{
     *     id: int,
     *     type: string,
     *     category_name: string,
     *     operation_date: string,
     *     tokens: array<string, true>,
     *     denominator: float,
     * }>  $entries
     */
    private function __construct(
        private readonly array $weights,
        private readonly array $index,
        private readonly array $entries,
    ) {}

    /**
     * @param  iterable<array{
     *     id: int,
     *     type: 'income'|'expense',
     *     category_name: string,
     *     text: string,
     *     operation_date: string,
     * }>  $entries
     */
    public static function fromHistory(iterable $entries): self
    {
        $stored = [];
        $pairsByToken = [];

        foreach ($entries as $entry) {
            $tokens = self::tokens($entry['text']);

            if ($tokens === []) {
                continue;
            }

            $pair = $entry['type']."\0".$entry['category_name'];

            foreach ($tokens as $token) {
                $pairsByToken[$token][$pair] = true;
            }

            $stored[] = [
                'id' => $entry['id'],
                'type' => $entry['type'],
                'category_name' => $entry['category_name'],
                'operation_date' => $entry['operation_date'],
                'tokens' => array_fill_keys($tokens, true),
                'denominator' => 0.0,
            ];
        }

        $weights = array_map(
            fn (array $pairs): float => 1 / count($pairs) ** 2,
            $pairsByToken,
        );

        $index = [];

        foreach ($stored as $position => $entry) {
            foreach (array_keys($entry['tokens']) as $token) {
                $index[$entry['type']][$token][] = $position;
                $stored[$position]['denominator'] += max($weights[$token], self::WEIGHT_FLOOR);
            }
        }

        return new self($weights, $index, $stored);
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $text): array
    {
        $normalised = strtr(mb_strtoupper($text), [
            'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N',
            'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z',
        ]);
        $normalised = (string) preg_replace('/[^A-Z ]+/', ' ', $normalised);

        $tokens = [];

        foreach (preg_split('/\s+/', trim($normalised)) ?: [] as $word) {
            if (mb_strlen($word) >= 3) {
                $tokens[$word] = true;
            }
        }

        return array_keys($tokens);
    }

    /**
     * @return list<array{category_name: string, score: float}>
     */
    public function rank(string $type, string $text): array
    {
        $rowTokens = self::tokens($text);
        $neighbours = $this->neighbours($type, $rowTokens);

        if ($neighbours === [] || $neighbours[0]['similarity'] < self::MIN_SIMILARITY) {
            return [];
        }

        $votes = [];

        foreach ($neighbours as $neighbour) {
            $name = $neighbour['category_name'];
            $votes[$name] = ($votes[$name] ?? 0.0) + $neighbour['similarity'];
        }

        arsort($votes);

        $ranked = [];

        foreach ($votes as $name => $score) {
            $ranked[] = ['category_name' => (string) $name, 'score' => $score];
        }

        return $ranked;
    }

    public function suggest(string $type, string $text): ?string
    {
        $ranked = $this->rank($type, $text);

        if ($ranked === []) {
            return null;
        }

        if (isset($ranked[1]) && $ranked[0]['score'] - $ranked[1]['score'] < self::VOTE_EPSILON) {
            return null;
        }

        return $ranked[0]['category_name'];
    }

    /**
     * Candidates come only from row tokens with weight >= WEIGHT_FLOOR; the
     * similarity itself still uses every token of the union.
     *
     * @param  list<string>  $rowTokens
     * @return list<array{similarity: float, operation_date: string, id: int, category_name: string}>
     */
    private function neighbours(string $type, array $rowTokens): array
    {
        $candidates = [];

        foreach ($rowTokens as $token) {
            if (($this->weights[$token] ?? 0.0) < self::WEIGHT_FLOOR) {
                continue;
            }

            foreach ($this->index[$type][$token] ?? [] as $position) {
                $candidates[$position] = true;
            }
        }

        if ($candidates === []) {
            return [];
        }

        $rowDenominator = 0.0;

        foreach ($rowTokens as $token) {
            $rowDenominator += max($this->weights[$token] ?? 0.0, self::WEIGHT_FLOOR);
        }

        $scored = [];

        foreach (array_keys($candidates) as $position) {
            $entry = $this->entries[$position];
            $similarity = $this->similarity($rowTokens, $rowDenominator, $entry);

            if ($similarity > 0) {
                $scored[] = [
                    'similarity' => $similarity,
                    'operation_date' => $entry['operation_date'],
                    'id' => $entry['id'],
                    'category_name' => $entry['category_name'],
                ];
            }
        }

        usort($scored, fn (array $a, array $b): int => [$b['similarity'], $b['operation_date'], $b['id']]
            <=> [$a['similarity'], $a['operation_date'], $a['id']]);

        return array_slice($scored, 0, self::NEIGHBOURS);
    }

    /**
     * @param  list<string>  $rowTokens
     * @param  array{tokens: array<string, true>, denominator: float, ...}  $entry
     */
    private function similarity(array $rowTokens, float $rowDenominator, array $entry): float
    {
        $common = 0.0;
        $commonFloored = 0.0;

        foreach ($rowTokens as $token) {
            if (! isset($entry['tokens'][$token])) {
                continue;
            }

            $weight = $this->weights[$token] ?? 0.0;
            $common += $weight;
            $commonFloored += max($weight, self::WEIGHT_FLOOR);
        }

        $union = $rowDenominator + $entry['denominator'] - $commonFloored;

        return $union > 0 ? $common / $union : 0.0;
    }
}
