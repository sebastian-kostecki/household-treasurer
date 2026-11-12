<?php

namespace Tests\Feature\Nfr;

/**
 * Wall-clock check shared by the NFR performance tests: a request must finish
 * within the two seconds of NFR-PERF-01 (context/foundation/prd-mvp.md §10).
 */
trait MeasuresRequestTime
{
    private function assertRequestWithinSeconds(callable $request, string $label): void
    {
        $maxSeconds = 2.0;
        $startedAt = microtime(true);

        $request();

        $elapsedSeconds = microtime(true) - $startedAt;

        $this->assertLessThanOrEqual(
            $maxSeconds,
            $elapsedSeconds,
            "{$label} took {$elapsedSeconds}s; expected ≤ {$maxSeconds}s.",
        );
    }
}
