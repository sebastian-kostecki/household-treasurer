<?php

// Read-only offline evaluation of the production suggestion model:
// chronological replay of one user's Import history.
//
// Run: docker exec -i household-treasurer-laravel.test-1 php < context/changes/import-category-suggestions/prototype/eval_service.php
//
// Thresholds checked manually (plan, Phase 4):
//   from January:  >= 78% rows with a suggestion, >= 92% correct
//   Aug-Nov:       >= 79% rows with a suggestion, >= 95% correct
use App\Support\CsvImport\ImportCategorySuggestionModel;
use App\Support\CsvImport\ImportRowFields;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Set to a user id, or leave null for the first user with imported operations.
$USER_ID = null;
// Evaluation year; null means the latest year with imported operations.
$YEAR = null;

$userId = $USER_ID ?? DB::table('financial_operations')
    ->whereNotNull('raw_statement_description')
    ->orderBy('user_id')
    ->value('user_id');

if ($userId === null) {
    echo "No imported operations found.\n";
    exit(1);
}

$rows = DB::table('financial_operations as o')
    ->join('categories as c', 'c.id', '=', 'o.category_id')
    ->where('o.user_id', $userId)
    ->whereNotNull('o.raw_statement_description')
    ->where('o.description', '<>', ImportRowFields::EMPTY_DESCRIPTION_FALLBACK)
    ->whereIn('o.type', ['income', 'expense'])
    ->orderBy('o.operation_date')
    ->orderBy('o.id')
    ->get(['o.id', 'o.type', 'o.operation_date', 'o.description', 'o.counterparty', 'c.name as category_name'])
    ->map(fn ($r): array => [
        'id' => (int) $r->id,
        'type' => $r->type,
        'category_name' => $r->category_name,
        'text' => trim($r->description.' '.($r->counterparty ?? '')),
        'operation_date' => substr((string) $r->operation_date, 0, 10),
    ])
    ->all();

$YEAR ??= (int) substr(end($rows)['operation_date'], 0, 4);

$periods = [
    'od stycznia' => ["$YEAR-01-01", "$YEAR-12-31"],
    'sierpien-listopad' => ["$YEAR-08-01", "$YEAR-11-30"],
];

$stats = [];
foreach (array_keys($periods) as $name) {
    $stats[$name] = ['rows' => 0, 'suggested' => 0, 'hit' => 0, 'top3' => 0];
}

$misses = [];

foreach ($rows as $position => $row) {
    $inAny = false;
    foreach ($periods as [$from, $to]) {
        $inAny = $inAny || ($row['operation_date'] >= $from && $row['operation_date'] <= $to);
    }
    if (! $inAny) {
        continue;
    }

    // History = every earlier entry by (operation_date, id).
    $model = ImportCategorySuggestionModel::fromHistory(array_slice($rows, 0, $position));
    $suggested = $model->suggest($row['type'], $row['text']);
    $ranked = $model->rank($row['type'], $row['text']);
    $top3 = array_slice(array_column($ranked, 'category_name'), 0, 3);

    foreach ($periods as $name => [$from, $to]) {
        if ($row['operation_date'] < $from || $row['operation_date'] > $to) {
            continue;
        }

        $stats[$name]['rows']++;

        if ($suggested === null) {
            continue;
        }

        $stats[$name]['suggested']++;

        if ($suggested === $row['category_name']) {
            $stats[$name]['hit']++;
        } elseif (count($misses) < 12) {
            $misses[] = [mb_substr($row['text'], 0, 60), $row['category_name'], $suggested];
        }

        if (in_array($row['category_name'], $top3, true)) {
            $stats[$name]['top3']++;
        }
    }
}

echo 'user: '.$userId.', year: '.$YEAR.', history rows: '.count($rows)."\n";

foreach ($stats as $name => $s) {
    printf(
        "%-18s rows %4d  suggested %4d (%.1f%%)  correct %4d (%.1f%% of suggested)  in top3 %.1f%% of suggested\n",
        $name,
        $s['rows'],
        $s['suggested'],
        $s['rows'] ? 100 * $s['suggested'] / $s['rows'] : 0,
        $s['hit'],
        $s['suggested'] ? 100 * $s['hit'] / $s['suggested'] : 0,
        $s['suggested'] ? 100 * $s['top3'] / $s['suggested'] : 0,
    );
}

echo "\nsample misses (text | chosen | suggested):\n";
foreach ($misses as $miss) {
    echo implode(' | ', $miss), "\n";
}
