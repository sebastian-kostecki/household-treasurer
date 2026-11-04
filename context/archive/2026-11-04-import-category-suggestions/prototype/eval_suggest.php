<?php

// Read-only offline evaluation: chronological replay of category suggestions.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$rows = DB::select('
    SELECT o.id, o.type, o.operation_date, o.description, o.counterparty, c.name AS cat
    FROM financial_operations o JOIN categories c ON c.id = o.category_id
    WHERE o.raw_statement_description IS NOT NULL
    ORDER BY o.operation_date, o.id
');

function tokens(string $text): array
{
    $t = mb_strtoupper($text);
    $t = strtr($t, ['Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z']);
    $t = preg_replace('/[^A-Z ]+/', ' ', $t);           // drop digits, dates, amounts, punctuation
    $out = [];
    foreach (preg_split('/\s+/', trim($t)) as $w) {
        if (mb_strlen($w) >= 3) {
            $out[$w] = true;
        }   // drop 1-2 letter fragments
    }

    return array_keys($out);
}

$n = count($rows);
$stats = [];
foreach (['A_exact_text', 'B_token_idf_knn'] as $m) {
    $stats[$m] = ['suggested' => 0, 'hit' => 0, 'top3' => 0];
}

$history = [];          // past rows: [type, cat, tokens, exactKey]
$tokenCats = [];        // token => [cat => count]  (learned from past only)
$fails = [];

foreach ($rows as $r) {
    $text = trim($r->description.' '.($r->counterparty ?? ''));
    $tok = tokens($text);
    $exactKey = $r->type.'|'.mb_strtoupper($text);

    // --- A: exact same text seen before
    $votes = [];
    foreach ($history as $h) {
        if ($h['exact'] === $exactKey) {
            $votes[$h['cat']] = ($votes[$h['cat']] ?? 0) + 1;
        }
    }
    if ($votes) {
        arsort($votes);
        $stats['A_exact_text']['suggested']++;
        if (array_key_first($votes) === $r->cat) {
            $stats['A_exact_text']['hit']++;
        }
        if (in_array($r->cat, array_slice(array_keys($votes), 0, 3), true)) {
            $stats['A_exact_text']['top3']++;
        }
    }

    // --- B: token weights = how specific a token is to categories (spread across many cats => ~0)
    $weight = function (string $w) use ($tokenCats) {
        if (! isset($tokenCats[$w])) {
            return 0.0;
        }
        $cats = count($tokenCats[$w]);

        return 1.0 / $cats ** 2;   // name, city, POL, ZAKUP... appear in many cats -> near zero
    };
    $scores = [];
    foreach ($history as $h) {
        if ($h['type'] !== $r->type) {
            continue;
        }
        $common = array_intersect($tok, $h['tok']);
        if (! $common) {
            continue;
        }
        $s = 0.0;
        foreach ($common as $w) {
            $s += $weight($w);
        }
        $den = 0.0;
        foreach (array_unique(array_merge($tok, $h['tok'])) as $w) {
            $den += max($weight($w), 0.05);
        }
        $sim = $den > 0 ? $s / $den : 0;
        if ($sim > 0) {
            $scores[] = [$sim, $h['cat']];
        }
    }
    usort($scores, fn ($a, $b) => $b[0] <=> $a[0]);
    $k = array_slice($scores, 0, 5);
    $votesB = [];
    foreach ($k as [$sim, $cat]) {
        $votesB[$cat] = ($votesB[$cat] ?? 0) + $sim;
    }
    if ($votesB && $k[0][0] >= 0.25) {
        arsort($votesB);
        $stats['B_token_idf_knn']['suggested']++;
        $best = array_key_first($votesB);
        if ($best === $r->cat) {
            $stats['B_token_idf_knn']['hit']++;
        } elseif (count($fails) < 12) {
            $fails[] = [mb_substr($text, 0, 60), $r->cat, $best];
        }
        if (in_array($r->cat, array_slice(array_keys($votesB), 0, 3), true)) {
            $stats['B_token_idf_knn']['top3']++;
        }
    }

    // learn
    $history[] = ['type' => $r->type, 'cat' => $r->cat, 'tok' => $tok, 'exact' => $exactKey];
    foreach ($tok as $w) {
        $tokenCats[$w][$r->cat] = ($tokenCats[$w][$r->cat] ?? 0) + 1;
    }
}

// last 3 months only (once history exists) — closer to steady-state use
echo "rows: $n\n";
foreach ($stats as $m => $s) {
    printf("%-18s suggested %4d/%d (%.0f%%)  correct %4d (%.0f%% of suggested)  in top3 %.0f%%\n",
        $m, $s['suggested'], $n, 100 * $s['suggested'] / $n, $s['hit'],
        $s['suggested'] ? 100 * $s['hit'] / $s['suggested'] : 0,
        $s['suggested'] ? 100 * $s['top3'] / $s['suggested'] : 0);
}
echo "\nsample misses (text | chosen | suggested):\n";
foreach ($fails as $f) {
    echo implode(' | ', $f), "\n";
}
