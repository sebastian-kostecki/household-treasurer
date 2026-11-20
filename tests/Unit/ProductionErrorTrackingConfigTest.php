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

    private function read(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }
}
