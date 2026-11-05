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
