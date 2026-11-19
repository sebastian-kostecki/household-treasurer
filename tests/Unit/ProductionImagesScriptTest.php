<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ProductionImagesScriptTest extends TestCase
{
    public function test_it_removes_the_oldest_tags_beyond_the_kept_ones(): void
    {
        $removed = $this->tagsToRemove(3, [], [
            ['aaa1111', '2026-11-01T10:00:00Z', 'sha256:1'],
            ['bbb2222', '2026-11-02T10:00:00Z', 'sha256:2'],
            ['ccc3333', '2026-11-03T10:00:00Z', 'sha256:3'],
            ['ddd4444', '2026-11-04T10:00:00Z', 'sha256:4'],
            ['eee5555', '2026-11-05T10:00:00Z', 'sha256:5'],
        ]);

        $this->assertSame(['bbb2222', 'aaa1111'], $removed);
    }

    public function test_prod_is_neither_counted_nor_printed(): void
    {
        $removed = $this->tagsToRemove(3, [], [
            ['prod', '2026-11-09T10:00:00Z', 'sha256:5'],
            ['<none>', '2026-11-09T10:00:00Z', 'sha256:6'],
            ['aaa1111', '2026-11-01T10:00:00Z', 'sha256:1'],
            ['bbb2222', '2026-11-02T10:00:00Z', 'sha256:2'],
            ['ccc3333', '2026-11-03T10:00:00Z', 'sha256:3'],
            ['ddd4444', '2026-11-04T10:00:00Z', 'sha256:4'],
        ]);

        $this->assertSame(['aaa1111'], $removed);
    }

    public function test_a_tag_with_a_protected_image_id_stays_even_when_it_is_the_oldest(): void
    {
        $removed = $this->tagsToRemove(3, ['sha256:1'], [
            ['aaa1111', '2026-11-01T10:00:00Z', 'sha256:1'],
            ['bbb2222', '2026-11-02T10:00:00Z', 'sha256:2'],
            ['ccc3333', '2026-11-03T10:00:00Z', 'sha256:3'],
            ['ddd4444', '2026-11-04T10:00:00Z', 'sha256:4'],
            ['eee5555', '2026-11-05T10:00:00Z', 'sha256:5'],
        ]);

        $this->assertSame(['bbb2222'], $removed);
    }

    public function test_two_protected_image_ids_both_stay_when_outside_the_newest_three(): void
    {
        $removed = $this->tagsToRemove(3, ['sha256:1', 'sha256:2'], [
            ['aaa1111', '2026-11-01T10:00:00Z', 'sha256:1'],
            ['bbb2222', '2026-11-02T10:00:00Z', 'sha256:2'],
            ['ccc3333', '2026-11-03T10:00:00Z', 'sha256:3'],
            ['ddd4444', '2026-11-04T10:00:00Z', 'sha256:4'],
            ['eee5555', '2026-11-05T10:00:00Z', 'sha256:5'],
            ['fff6666', '2026-11-06T10:00:00Z', 'sha256:6'],
        ]);

        $this->assertSame(['ccc3333'], $removed);
    }

    public function test_a_legacy_tag_with_fractional_seconds_sorts_by_time_like_the_others(): void
    {
        $removed = $this->tagsToRemove(3, [], [
            ['legacy-20261101', '2026-11-01T10:00:00.123456789Z', 'sha256:1'],
            ['bbb2222', '2026-11-01T10:00:01Z', 'sha256:2'],
            ['ccc3333', '2026-11-02T10:00:00Z', 'sha256:3'],
            ['ddd4444', '2026-11-03T10:00:00Z', 'sha256:4'],
        ]);

        $this->assertSame(['legacy-20261101'], $removed);
    }

    public function test_nothing_is_removed_when_there_are_at_most_three_tags(): void
    {
        $removed = $this->tagsToRemove(3, [], [
            ['prod', '2026-11-05T10:00:00Z', 'sha256:3'],
            ['aaa1111', '2026-11-01T10:00:00Z', 'sha256:1'],
            ['bbb2222', '2026-11-02T10:00:00Z', 'sha256:2'],
            ['ccc3333', '2026-11-03T10:00:00Z', 'sha256:3'],
        ]);

        $this->assertSame([], $removed);
    }

    /**
     * @param  list<string>  $protectedIds
     * @param  list<array{string, string, string}>  $rows
     * @return list<string>
     */
    private function tagsToRemove(int $keep, array $protectedIds, array $rows): array
    {
        $input = implode('', array_map(fn (array $row): string => implode("\t", $row)."\n", $rows));
        $arguments = implode(' ', array_map('escapeshellarg', [(string) $keep, ...$protectedIds]));

        $result = Process::path(base_path())
            ->input($input)
            ->run(['bash', '-c', 'source scripts/lib/production-images.sh; production_image_tags_to_remove '.$arguments]);

        $this->assertTrue($result->successful(), $result->errorOutput());

        return array_values(array_filter(explode("\n", $result->output()), fn (string $line): bool => $line !== ''));
    }
}
