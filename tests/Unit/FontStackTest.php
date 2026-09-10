<?php

test('vite loads Geist with latin-ext so Polish diacritics stay in the UI face', function () {
    $vite = file_get_contents(__DIR__.'/../../vite.config.ts');

    expect($vite)->toContain("bunny('Geist'");
    expect($vite)->toMatch("/subsets:\s*\[[^\]]*['\"]latin-ext['\"]/");
    expect($vite)->not->toContain('Instrument Sans');
});

test('app.css maps --font-sans to Geist and not Instrument Sans', function () {
    $css = file_get_contents(__DIR__.'/../../resources/css/app.css');

    expect($css)->toContain('Geist');
    expect($css)->not->toContain('Instrument');
});
