<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProductionPostgresClientVersionTest extends TestCase
{
    public function test_production_image_installs_pg_dump_matching_the_postgres_server_major_version(): void
    {
        $compose = (string) file_get_contents(base_path('docker-compose.prod.yml'));
        $dockerfile = (string) file_get_contents(base_path('docker/8.5/Dockerfile.prod'));

        $this->assertSame(1, preg_match('/image:\\s+[\'"]?postgres:(\\d+)/', $compose, $matches));

        $majorVersion = $matches[1];

        $this->assertStringContainsString('noble-pgdg', $dockerfile);
        $this->assertStringContainsString('postgresql-client-$POSTGRES_VERSION', $dockerfile);
        $this->assertStringContainsString('POSTGRES_VERSION: '.$majorVersion, $compose);
        $this->assertStringContainsString('ARG POSTGRES_VERSION='.$majorVersion, $dockerfile);
    }
}
