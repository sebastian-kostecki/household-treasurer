<?php

use Illuminate\Support\Env;

/**
 * Infrastructure identifiers must not follow APP_NAME (ADR-0059).
 */
const PINNED_ENV_KEYS = ['APP_NAME', 'SESSION_COOKIE', 'CACHE_PREFIX', 'REDIS_PREFIX', 'BACKUP_NAME'];

beforeEach(function () {
    $this->originalEnv = [];

    foreach (PINNED_ENV_KEYS as $key) {
        $this->originalEnv[$key] = [
            'env' => $_ENV[$key] ?? null,
            'server' => $_SERVER[$key] ?? null,
            'putenv' => getenv($key),
        ];
    }

    Env::disablePutenv();
    Env::enablePutenv();

    foreach (['SESSION_COOKIE', 'CACHE_PREFIX', 'REDIS_PREFIX', 'BACKUP_NAME'] as $key) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }

    $_ENV['APP_NAME'] = $_SERVER['APP_NAME'] = 'Domowy Skarbnik';
    putenv('APP_NAME=Domowy Skarbnik');
});

afterEach(function () {
    foreach ($this->originalEnv as $key => $original) {
        foreach (['env' => '_ENV', 'server' => '_SERVER'] as $slot => $global) {
            if ($original[$slot] === null) {
                unset($GLOBALS[$global][$key]);
            } else {
                $GLOBALS[$global][$key] = $original[$slot];
            }
        }

        putenv($original['putenv'] === false ? $key : "{$key}={$original['putenv']}");
    }

    Env::disablePutenv();
    Env::enablePutenv();
});

function loadConfigFile(string $name): array
{
    return require config_path("{$name}.php");
}

it('keeps the session cookie name when APP_NAME changes', function () {
    expect(loadConfigFile('session')['cookie'])->toBe('household-treasurer-session');
});

it('keeps the cache prefix when APP_NAME changes', function () {
    expect(loadConfigFile('cache')['prefix'])->toBe('household-treasurer-cache-');
});

it('keeps the Redis prefix when APP_NAME changes', function () {
    expect(loadConfigFile('database')['redis']['options']['prefix'])->toBe('household-treasurer-database-');
});

it('keeps the backup name and its monitor name when APP_NAME changes', function () {
    $backup = loadConfigFile('backup');

    expect($backup['backup']['name'])->toBe('Household Treasurer')
        ->and($backup['monitor_backups'][0]['name'])->toBe('Household Treasurer');
});
