<?php

namespace Tests\Unit;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
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

    public function test_a_row_without_a_time_or_an_image_id_stops_the_selection(): void
    {
        $result = $this->runImagesScript('production_image_tags_to_remove 3 sha256:9', input: "aaa1111\t\t\nbbb2222\t2026-11-02T10:00:00Z\tsha256:2\n");

        $this->assertFalse($result->successful());
        $this->assertSame('', $result->output());
        $this->assertStringContainsString('aaa1111', $result->errorOutput());
    }

    public function test_a_failed_image_inspect_stops_the_cleanup_before_any_removal(): void
    {
        $result = $this->runImagesScript('production_remove_old_image_tags 3', fakeDocker: <<<'BASH'
            case "$1 $2" in
                "image ls")
                    if [[ "$3" == "-q" ]]; then echo "sha256:5"; else printf 'prod\neee5555\nddd4444\nccc3333\nbbb2222\naaa1111\n'; fi ;;
                "image inspect")
                    if [[ "$5" == *:bbb2222 ]]; then echo "Error response from daemon: transient" >&2; return 1; fi
                    case "$4" in
                        *deployed-at*) echo "2026-11-0${5: -1}T10:00:00Z" ;;
                        *) echo "sha256:${5: -1}" ;;
                    esac ;;
                *) echo "CALL docker $*" ;;
            esac
            BASH);

        $this->assertFalse($result->successful());
        $this->assertStringNotContainsString('CALL docker rmi', $result->output());
        $this->assertStringNotContainsString('CALL docker image prune', $result->output());
    }

    public function test_a_daemon_error_is_not_read_as_a_missing_prod_image(): void
    {
        $result = $this->runImagesScript('prod_id="$(production_prod_image_id)"; echo "read: ${prod_id}"', fakeDocker: <<<'BASH'
            echo "Cannot connect to the Docker daemon" >&2
            return 1
            BASH);

        $this->assertFalse($result->successful());
        $this->assertStringNotContainsString('read:', $result->output());
    }

    public function test_a_missing_prod_image_reads_as_empty(): void
    {
        $result = $this->runImagesScript('prod_id="$(production_prod_image_id)"; echo "read: [${prod_id}]"', fakeDocker: <<<'BASH'
            if [[ "$1 $2 $3" == "image ls -q" ]]; then return 0; fi
            echo "Error: No such image: $*" >&2
            return 1
            BASH);

        $this->assertTrue($result->successful(), $result->errorOutput());
        $this->assertSame("read: []\n", $result->output());
    }

    public function test_redeploying_a_commit_keeps_its_previous_build_under_a_dated_tag(): void
    {
        $result = $this->runImagesScript('production_tag_built_image abc1234', fakeDocker: <<<'BASH'
            case "$1 $2" in
                "image ls") echo "sha256:old" ;;
                "image inspect")
                    case "$4 $5" in
                        *deployed-at*abc1234) echo "2026-11-18T09:30:15Z" ;;
                        *:abc1234) echo "sha256:old" ;;
                        *:prod) echo "sha256:new" ;;
                    esac ;;
                *) echo "CALL docker $*" ;;
            esac
            BASH);

        $this->assertTrue($result->successful(), $result->errorOutput());
        $this->assertSame([
            'CALL docker tag household-treasurer-app:abc1234 household-treasurer-app:abc1234-20261118093015',
            'CALL docker tag household-treasurer-app:prod household-treasurer-app:abc1234',
        ], $this->callLines($result->output()));
    }

    public function test_a_first_build_of_a_commit_only_gets_its_sha_tag(): void
    {
        $result = $this->runImagesScript('production_tag_built_image abc1234', fakeDocker: <<<'BASH'
            case "$1 $2" in
                "image ls") ;;
                *) echo "CALL docker $*" ;;
            esac
            BASH);

        $this->assertTrue($result->successful(), $result->errorOutput());
        $this->assertSame([
            'CALL docker tag household-treasurer-app:prod household-treasurer-app:abc1234',
        ], $this->callLines($result->output()));
    }

    public function test_rollback_retry_compares_with_a_stopped_app_container_not_with_prod(): void
    {
        $result = $this->runRollback('aaa1111', <<<'BASH'
            if [[ "$1" == "compose" ]]; then
                shift 5
                case "$*" in
                    "ps --all --quiet app") echo "container-1" ;;
                    "exec -T pgsql "*) echo "Stopped by the test after the guards" >&2; return 1 ;;
                esac
                return 0
            fi
            fake_image_docker "$@"
            BASH);

        $this->assertFalse($result->successful());
        $this->assertStringNotContainsString('already runs', $result->errorOutput());
        $this->assertStringContainsString('Comparing migrations', $result->output());
    }

    public function test_a_failure_before_prod_moves_does_not_advise_rolling_back(): void
    {
        $result = $this->runRollback('aaa1111', <<<'BASH'
            if [[ "$1" == "compose" ]]; then
                shift 5
                case "$*" in
                    "ps -q app"|"ps --all --quiet app"|"ps --status running --quiet app") echo "container-1" ;;
                    "exec -T pgsql "*) echo "2026_01_01_000000_a" ;;
                esac
                return 0
            fi
            if [[ "$1" == "tag" ]]; then echo "tag refused" >&2; return 1; fi
            fake_image_docker "$@"
            BASH);

        $this->assertFalse($result->successful());
        $this->assertStringContainsString('STAYS in maintenance mode', $result->errorOutput());
        $this->assertStringNotContainsString('Way back', $result->errorOutput());
    }

    public function test_a_failure_after_prod_moves_advises_the_way_back(): void
    {
        $result = $this->runRollback('aaa1111', <<<'BASH'
            if [[ "$1" == "compose" ]]; then
                shift 5
                case "$*" in
                    "ps -q app"|"ps --all --quiet app"|"ps --status running --quiet app") echo "container-1" ;;
                    "exec -T pgsql "*) echo "2026_01_01_000000_a" ;;
                    "up -d --no-build app") echo "up failed" >&2; return 1 ;;
                esac
                return 0
            fi
            if [[ "$1" == "tag" ]]; then return 0; fi
            fake_image_docker "$@"
            BASH);

        $this->assertFalse($result->successful());
        $this->assertStringContainsString('Way back to the previous image: scripts/rollback.sh bbb2222', $result->errorOutput());
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

    public function test_a_migration_only_in_the_database_is_unknown_and_not_pending(): void
    {
        $database = ['2026_01_01_000000_a', '2026_02_01_000000_b'];
        $image = ['2026_01_01_000000_a.php'];

        $this->assertSame(['2026_02_01_000000_b'], $this->migrationDiff('production_unknown_migrations', $database, $image));
        $this->assertSame([], $this->migrationDiff('production_pending_migrations', $database, $image));
    }

    public function test_a_migration_only_in_the_image_is_pending_and_not_unknown(): void
    {
        $database = ['2026_01_01_000000_a'];
        $image = ['2026_01_01_000000_a.php', '2026_02_01_000000_b.php'];

        $this->assertSame([], $this->migrationDiff('production_unknown_migrations', $database, $image));
        $this->assertSame(['2026_02_01_000000_b'], $this->migrationDiff('production_pending_migrations', $database, $image));
    }

    public function test_equal_migration_lists_with_php_suffixes_on_the_image_side_differ_in_nothing(): void
    {
        $database = ['2026_01_01_000000_a', '2026_02_01_000000_b'];
        $image = ['2026_02_01_000000_b.php', '', '2026_01_01_000000_a.php'];

        $this->assertSame([], $this->migrationDiff('production_unknown_migrations', $database, $image));
        $this->assertSame([], $this->migrationDiff('production_pending_migrations', $database, $image));
    }

    /**
     * @param  list<string>  $database
     * @param  list<string>  $image
     * @return list<string>
     */
    private function migrationDiff(string $function, array $database, array $image): array
    {
        $databaseFile = tempnam(sys_get_temp_dir(), 'migrations-db-');
        $imageFile = tempnam(sys_get_temp_dir(), 'migrations-image-');
        file_put_contents($databaseFile, implode("\n", $database)."\n");
        file_put_contents($imageFile, implode("\n", $image)."\n");

        try {
            $result = Process::path(base_path())->run([
                'bash', '-c', 'set -euo pipefail; source scripts/lib/production-images.sh; '.$function.' "$1" "$2"', 'bash', $databaseFile, $imageFile,
            ]);
        } finally {
            unlink($databaseFile);
            unlink($imageFile);
        }

        $this->assertTrue($result->successful(), $result->errorOutput());

        return $this->lines($result->output());
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

        $result = $this->runImagesScript('production_image_tags_to_remove '.$arguments, input: $input);

        $this->assertTrue($result->successful(), $result->errorOutput());

        return $this->lines($result->output());
    }

    /**
     * Runs a snippet with the image library sourced, under the shell options of the
     * production scripts. A fake `docker` shell function replaces the real one, so no
     * Docker call is made; the commands it does not answer print `CALL docker …`.
     */
    private function runImagesScript(string $script, string $fakeDocker = 'echo "CALL docker $*"', string $input = ''): ProcessResult
    {
        return Process::path(base_path())
            ->input($input)
            ->run(['bash', '-c', "set -euo pipefail\nsource scripts/lib/production-images.sh\ndocker() {\n{$fakeDocker}\n}\n{$script}"]);
    }

    /**
     * Runs a copy of scripts/rollback.sh in a temporary root with its own .env.prod.
     * `$docker` is the body of the fake `docker` function; it can hand image questions
     * to `fake_image_docker`, which knows :prod and aaa1111 (image a) and bbb2222
     * (image b), with container-1 running image b.
     */
    private function runRollback(string $tag, string $docker): ProcessResult
    {
        $root = sys_get_temp_dir().'/rollback-test-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($root.'/scripts/lib');
        File::copy(base_path('scripts/rollback.sh'), $root.'/scripts/rollback.sh');
        File::copy(base_path('scripts/lib/production-deploy.sh'), $root.'/scripts/lib/production-deploy.sh');
        File::copy(base_path('scripts/lib/production-images.sh'), $root.'/scripts/lib/production-images.sh');
        File::put($root.'/.env.prod', "APP_ENV=production\n");

        $fakeImageDocker = <<<'BASH'
            fake_image_docker() {
                local ref id
                case "$1 $2" in
                    "image ls")
                        if [[ "$3" == "-q" ]]; then
                            case "$4" in *:prod|*:aaa1111|*:bbb2222) echo "sha256:${4: -1}" ;; esac
                        else
                            printf 'prod\naaa1111\nbbb2222\n'
                        fi ;;
                    "image inspect")
                        if [[ "$3" != "--format" ]]; then return 0; fi
                        ref="$5"
                        case "${ref}" in *:prod|*:aaa1111) id="sha256:a" ;; *:bbb2222) id="sha256:b" ;; *) return 1 ;; esac
                        case "$4" in
                            *deployed-at*) echo "2026-11-1${id: -1}T10:00:00Z" ;;
                            *revision*) echo "${ref##*:}" ;;
                            *Created*) echo "2026-11-01T10:00:00Z" ;;
                            *) echo "${id}" ;;
                        esac ;;
                    "inspect --format") echo "sha256:b" ;;
                    "run --rm") echo "2026_01_01_000000_a.php" ;;
                esac
            }
            BASH;

        try {
            return Process::path($root)->run([
                'bash', '-c', $fakeImageDocker."\ndocker() {\n{$docker}\n}\nexport -f docker fake_image_docker\nbash scripts/rollback.sh \"\$1\"", 'bash', $tag,
            ]);
        } finally {
            File::deleteDirectory($root);
        }
    }

    /**
     * @return list<string>
     */
    private function callLines(string $output): array
    {
        return array_values(array_filter($this->lines($output), fn (string $line): bool => str_starts_with($line, 'CALL ')));
    }

    /**
     * @return list<string>
     */
    private function lines(string $output): array
    {
        return array_values(array_filter(explode("\n", $output), fn (string $line): bool => $line !== ''));
    }
}
