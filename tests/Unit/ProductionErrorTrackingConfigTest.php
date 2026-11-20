<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProductionErrorTrackingConfigTest extends TestCase
{
    public function test_php_ini_drops_argument_values_from_exception_frames(): void
    {
        $this->assertStringContainsString('zend.exception_ignore_args = On', $this->read('docker/8.5/php.ini'));
    }

    public function test_production_env_example_logs_to_a_rotating_file_and_stderr_from_warning(): void
    {
        $env = $this->read('.env.prod.example');

        $this->assertMatchesRegularExpression('/^LOG_STACK=daily,stderr$/m', $env);
        $this->assertMatchesRegularExpression('/^LOG_LEVEL=warning$/m', $env);
    }

    public function test_production_image_exposes_the_git_revision_as_the_app_release_after_the_arg(): void
    {
        $dockerfile = $this->read('docker/8.5/Dockerfile.prod');

        $arg = strrpos($dockerfile, 'ARG GIT_REVISION=');
        $env = strpos($dockerfile, 'ENV APP_RELEASE=$GIT_REVISION');

        $this->assertNotFalse($arg);
        $this->assertNotFalse($env);
        $this->assertGreaterThan($arg, $env);
    }

    public function test_production_compose_joins_the_external_errors_network_next_to_the_others(): void
    {
        $compose = $this->read('docker-compose.prod.yml');

        $this->assertMatchesRegularExpression('/^\s{12}- errors-treasurer$/m', $compose);
        $this->assertMatchesRegularExpression('/^\s{12}- treasurer-internal$/m', $compose);
        $this->assertMatchesRegularExpression('/^\s{12}- proxy-treasurer$/m', $compose);
        $this->assertMatchesRegularExpression('/^    errors-treasurer:\n        external: true$/m', $compose);
    }

    public function test_production_env_example_lists_an_empty_dsn_and_the_deploy_checks_the_tracker(): void
    {
        $this->assertMatchesRegularExpression('/^SENTRY_LARAVEL_DSN=$/m', $this->read('.env.prod.example'));
        $this->assertStringContainsString('php artisan sentry:test', $this->read('scripts/deploy.sh'));
    }

    public function test_nginx_caps_the_error_tunnel_body_at_the_app_limit_in_its_own_php_location(): void
    {
        $nginx = $this->read('docker/8.5/nginx.conf');

        $this->assertSame(1, preg_match('/location = \/error-tracking\/tunnel \{(?<block>[^}]*)\}/', $nginx, $match));
        $this->assertStringContainsString('client_max_body_size 256k;', $match['block']);
        $this->assertStringContainsString('fastcgi_pass unix:/run/php/php8.5-fpm.sock;', $match['block']);
        $this->assertStringNotContainsString('try_files', $match['block']);
        $this->assertSame(256 * 1024, config('error-tracking.tunnel.max_body_bytes'));
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }
}
