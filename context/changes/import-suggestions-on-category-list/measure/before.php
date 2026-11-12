<?php

// "Before" side of the S-23 measurement. Runs on the code of bbf49ba^ (before the
// category list), against the same restored database as the "after" side, and
// writes the file format of `import:suggestion-accuracy` (format 1).
//
// Run in a container holding the OLD code:
//   USER_ID=1 YEAR=2026 OUTPUT=storage/app/private/suggestion-accuracy/2026-09-11-before.json php < before.php
//
// The history, the vote (by name) and the target (`categoryIdsByYearTypeAndName()` +
// `uniqueId()`) are the old ImportCategorySuggester's own methods, called through
// Closure::bind, not rewritten. Read-only.
use App\Models\User;
use App\Support\CsvImport\ImportCategorySuggester;
use App\Support\CsvImport\ImportCategorySuggestionModel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$userId = (int) getenv('USER_ID');
$year = (int) getenv('YEAR');
$output = (string) getenv('OUTPUT');

if ($userId === 0 || $year === 0) {
    fwrite(STDERR, "USER_ID and YEAR are required.\n");
    exit(1);
}

$user = User::query()->findOrFail($userId);
$suggester = new ImportCategorySuggester;

$call = fn (string $method, mixed ...$args): mixed => Closure::bind(
    fn () => $this->{$method}(...$args),
    $suggester,
    ImportCategorySuggester::class,
)();

$history = iterator_to_array($call('loadHistory', $user), false);
usort($history, fn (array $a, array $b): int => [$a['operation_date'], $a['id']] <=> [$b['operation_date'], $b['id']]);

$categoryIds = $call('categoryIdsByYearTypeAndName', $user);
$names = DB::table('categories')->pluck('name', 'id')->all();

$periods = ['year' => ['01-01', '12-31'], 'aug_nov' => ['08-01', '11-30']];
$inPeriod = fn (string $date, string $period): bool => $date >= "$year-{$periods[$period][0]}" && $date <= "$year-{$periods[$period][1]}";

$rows = [];

foreach ($history as $position => $entry) {
    if (! $inPeriod($entry['operation_date'], 'year')) {
        continue;
    }

    $model = ImportCategorySuggestionModel::fromHistory(array_slice($history, 0, $position));
    $name = $model->suggest($entry['type'], $entry['text']);
    $top3 = array_column(array_slice($model->rank($entry['type'], $entry['text']), 0, 3), 'category_name');
    $targetId = $name === null ? null : $call('uniqueId', $categoryIds, (int) substr($entry['operation_date'], 0, 4), $entry['type'], $name);

    $rows[] = [
        'operation_id' => $entry['id'],
        'operation_date' => $entry['operation_date'],
        'type' => $entry['type'],
        'model' => [
            'suggested' => $name !== null,
            'correct' => $name === $entry['category_name'],
            'top3' => in_array($entry['category_name'], $top3, true),
        ],
        'suggestion' => [
            'suggested' => $targetId !== null,
            'correct' => $targetId !== null && ($names[$targetId] ?? null) === $entry['category_name'],
        ],
    ];
}

$summary = [];

foreach (array_keys($periods) as $period) {
    $model = ['rows' => 0, 'suggested' => 0, 'correct' => 0, 'top3' => 0];
    $suggestion = ['rows' => 0, 'suggested' => 0, 'correct' => 0];

    foreach ($rows as $row) {
        if (! $inPeriod($row['operation_date'], $period)) {
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

    $summary[$period] = ['model' => $model, 'suggestion' => $suggestion];
}

$result = ['format' => 1, 'user_id' => $userId, 'year' => $year, 'periods' => $summary, 'rows' => $rows];

$percent = fn (int $part, int $whole): string => $whole === 0 ? '-' : number_format($part / $whole * 100, 1)."% ($part/$whole)";
echo "User $userId, year $year (code before the category list)\n";

foreach (['year' => 'whole year', 'aug_nov' => 'Aug-Nov'] as $period => $label) {
    foreach (['model', 'suggestion'] as $level) {
        $s = $summary[$period][$level];
        printf("%-11s %-10s rows %4d  with suggestion %s  correct %s  top3 %s\n", $label, $level, $s['rows'], $percent($s['suggested'], $s['rows']), $percent($s['correct'], $s['suggested']), isset($s['top3']) ? $percent($s['top3'], $s['rows']) : '-');
    }
}

if ($output !== '') {
    $path = str_starts_with($output, '/') ? $output : '/var/www/html/'.$output;
    @mkdir(dirname($path), 0775, true);
    file_put_contents($path, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    echo "Result written to $output.\n";
}
