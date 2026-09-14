<?php

namespace Tests;

use App\Support\CategoryAppearance;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! is_dir(public_path('build'))) {
            mkdir(public_path('build'), 0777, true);
        }

        $entry = ['file' => 'assets/app.js', 'src' => 'resources/js/app.ts', 'isEntry' => true];
        $manifest = [
            'resources/css/app.css' => ['file' => 'assets/app.css', 'src' => 'resources/css/app.css', 'isEntry' => true],
            'resources/js/app.ts' => $entry,
        ];

        $pagesRoot = base_path('resources/js/pages');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pagesRoot, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'vue') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($pagesRoot) + 1));
            $manifest['resources/js/pages/'.$relative] = $entry;
        }

        file_put_contents(public_path('build/manifest.json'), json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function categoryFields(array $overrides = []): array
    {
        return array_merge([
            'icon' => CategoryAppearance::DEFAULT_ICON,
            'color' => CategoryAppearance::DEFAULT_COLOR,
        ], $overrides);
    }
}
