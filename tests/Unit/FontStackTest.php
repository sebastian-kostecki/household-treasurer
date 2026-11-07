<?php

test('vite loads Inter and JetBrains Mono with latin-ext so Polish diacritics stay in the UI face', function () {
    $vite = file_get_contents(__DIR__.'/../../vite.config.ts');

    expect($vite)->toContain("bunny('Inter'");
    expect($vite)->toContain("bunny('JetBrains Mono'");
    expect(preg_match_all("/subsets:\s*\[[^\]]*['\"]latin-ext['\"]/", $vite))->toBe(2);
    expect($vite)->not->toContain('Geist');
    expect($vite)->not->toContain('Instrument Sans');
});

test('app.css maps --font-sans to Inter and --font-mono to JetBrains Mono', function () {
    $css = file_get_contents(__DIR__.'/../../resources/css/app.css');

    expect($css)->toContain('Inter');
    expect($css)->toContain('JetBrains Mono');
    expect($css)->not->toContain('Geist');
    expect($css)->not->toContain('Instrument');
});
