<?php

namespace App\Support;

/**
 * The proof of the legacy budget import (ADR-0083, `app:control-numbers --allow-additions`):
 * between two control number documents the import may only add rows. Everything that exists
 * before must read the same after; `ControlNumbers::differences()` decides "the same".
 */
final class ControlNumberAdditions
{
    /**
     * Sections whose new ids the import may add (each is checked by `additionViolation()`).
     *
     * @var list<string>
     */
    private const ADDITION_SECTIONS = ['budgets', 'lines', 'accounts'];

    /**
     * Tables whose row count may grow in an import.
     *
     * @var list<string>
     */
    private const ADDITION_ROW_COUNTS = ['budgets', 'budget_lines', 'financial_operations', 'accounts'];

    public function __construct(private readonly ControlNumbers $controlNumbers) {}

    /**
     * What the legacy budget import may change and nothing else: every violation of the
     * rule "only rows were added", as a readable path and reason (empty when it holds).
     *
     * Allowed: new Budgets, new lines of new Budgets, new archived Sheet Accounts with a
     * zero current balance, new archived Categories (their Rhythm entries are part of the
     * entry), more rows in `budgets`, `budget_lines`, `financial_operations`, `accounts`
     * and a higher `max_financial_operation_id`. Any other path must be identical.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    public function violations(array $before, array $after): array
    {
        $violations = [];

        foreach (self::ADDITION_SECTIONS as $section) {
            foreach (array_diff_key($after[$section] ?? [], $before[$section] ?? []) as $id => $entry) {
                $violation = $this->additionViolation($section, $entry, $before);

                if ($violation !== null) {
                    $violations[] = "{$section}.{$id}: {$violation}";
                }

                unset($after[$section][$id]);
            }
        }

        if (is_array($before['categories'] ?? null) && is_array($after['categories'] ?? null)) {
            foreach (array_diff_key($after['categories'], $before['categories']) as $id => $entry) {
                if (($entry['archived'] ?? false) !== true) {
                    $violations[] = "categories.{$id}: a new Category must be archived";
                }

                unset($after['categories'][$id]);
            }
        }

        foreach (self::ADDITION_ROW_COUNTS as $table) {
            $count = $after['row_counts'][$table] ?? null;

            if ($count !== null && $count >= ($before['row_counts'][$table] ?? 0)) {
                $after['row_counts'][$table] = $before['row_counts'][$table] ?? 0;
            }
        }

        $maxId = $after['max_financial_operation_id'] ?? null;

        if ($maxId !== null && $maxId >= ($before['max_financial_operation_id'] ?? 0)) {
            $after['max_financial_operation_id'] = $before['max_financial_operation_id'] ?? null;
        }

        return [...$violations, ...$this->controlNumbers->differences($before, $after)];
    }

    /**
     * How many rows the import added, per kind (the summary the command prints).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, int>
     */
    public function summary(array $before, array $after): array
    {
        $summary = [];

        foreach (self::ADDITION_SECTIONS as $section) {
            $summary[$section] = count(array_diff_key($after[$section] ?? [], $before[$section] ?? []));
        }

        $summary['categories'] = count(array_diff_key($after['categories'] ?? [], $before['categories'] ?? []));
        $summary['financial_operations'] = ($after['row_counts']['financial_operations'] ?? 0) - ($before['row_counts']['financial_operations'] ?? 0);

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $before
     */
    private function additionViolation(string $section, array $entry, array $before): ?string
    {
        if ($section === 'lines' && array_key_exists($entry['budget_id'] ?? 0, $before['budgets'] ?? [])) {
            return "a line added to the existing Budget {$entry['budget_id']}";
        }

        if ($section === 'accounts' && (
            ($entry['archived'] ?? false) !== true
            || ($entry['sheet_year'] ?? null) === null
            || ($entry['current'] ?? null) !== 0
        )) {
            return 'a new Account must be an archived Sheet Account with a current balance of 0';
        }

        return null;
    }
}
