<?php

/**
 * @return array{theme: string, root: string, dark: string}
 */
function designTokenBlocks(): array
{
    $css = file_get_contents(__DIR__.'/../../resources/css/app.css');

    preg_match('/@theme inline \{(.*?)\n\}/s', $css, $theme);
    preg_match('/\n:root \{(.*?)\n\}/s', $css, $root);
    preg_match('/\n\.dark \{(.*?)\n\}/s', $css, $dark);

    return ['theme' => $theme[1] ?? '', 'root' => $root[1] ?? '', 'dark' => $dark[1] ?? ''];
}

test('every colour published in @theme inline has a value in :root and .dark', function () {
    $blocks = designTokenBlocks();

    preg_match_all('/--color-([\w-]+):\s*var\(--([\w-]+)\)/', $blocks['theme'], $matches, PREG_SET_ORDER);

    expect($matches)->not->toBeEmpty();

    foreach ($matches as [, $utility, $token]) {
        foreach (['root' => ':root', 'dark' => '.dark'] as $key => $selector) {
            expect(preg_match('/^\s*--'.preg_quote($token, '/').':/m', $blocks[$key]))
                ->toBe(1, "--color-{$utility} points at --{$token}, which is missing in {$selector}");
        }
    }
});

test('the nine Atrament roles are published and defined in both themes', function () {
    $blocks = designTokenBlocks();

    $roles = [
        'brand', 'brand-foreground', 'brand-text', 'warning-soft', 'destructive-soft',
        'track', 'border-soft', 'scrim', 'sidebar-strong',
    ];

    foreach ($roles as $role) {
        expect($blocks['theme'])->toContain("--color-{$role}: var(--{$role});");
        expect($blocks['root'])->toContain("--{$role}:");
        expect($blocks['dark'])->toContain("--{$role}:");
    }
});

test('radius is 10px', function () {
    expect(designTokenBlocks()['root'])->toContain('--radius: 10px;');
});


test('the dialog shadow has a value in both themes and is published as shadow-dialog', function () {
    $blocks = designTokenBlocks();

    expect($blocks['root'])->toContain('--dialog-shadow: 0 24px 60px rgba(15, 27, 45, 0.25);');
    expect($blocks['dark'])->toContain('--dialog-shadow: 0 24px 60px rgba(0, 0, 0, 0.5);');
    expect($blocks['theme'])->toContain('--shadow-dialog: var(--dialog-shadow);');
});

test('dialog and field dimensions are in :root only and radius-xl follows --radius', function () {
    $blocks = designTokenBlocks();

    expect($blocks['root'])->toContain('--field-height: 34px;');
    expect($blocks['root'])->toContain('--dialog-width: 460px;');
    expect($blocks['root'])->toContain('--control-h: var(--control-height);');
    expect($blocks['dark'])->not->toContain('--field-height');
    expect($blocks['dark'])->not->toContain('--dialog-width');
    expect($blocks['theme'])->toContain('--radius-xl: calc(var(--radius) + 2px);');
});
