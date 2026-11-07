import { describe, expect, it } from 'vite-plus/test';
import { resolveGlobalShortcut, type ShortcutInput } from './global-shortcuts';

function press(
    key: string,
    overrides: Partial<ShortcutInput> = {},
    viewCount = 6,
) {
    return resolveGlobalShortcut(
        {
            key,
            ctrlKey: false,
            metaKey: false,
            altKey: false,
            shiftKey: false,
            isComposing: false,
            repeat: false,
            targetIsEditable: false,
            hasOpenLayer: false,
            ...overrides,
        },
        viewCount,
    );
}

describe('resolveGlobalShortcut', () => {
    it('maps a digit to the 0-based index of the view', () => {
        expect(press('1')).toEqual({ kind: 'view', index: 0 });
        expect(press('6')).toEqual({ kind: 'view', index: 5 });
    });

    it('ignores digits outside the view range', () => {
        expect(press('7')).toBeNull();
        expect(press('9')).toBeNull();
        expect(press('0')).toBeNull();
    });

    it('follows the number of views', () => {
        expect(press('7', {}, 7)).toEqual({ kind: 'view', index: 6 });
    });

    it('maps n and N to add-operation', () => {
        expect(press('n')).toEqual({ kind: 'add-operation' });
        expect(press('N')).toEqual({ kind: 'add-operation' });
        expect(press('N', { shiftKey: true })).toEqual({
            kind: 'add-operation',
        });
    });

    it('maps / to focus-search, also with Shift (Polish keyboard)', () => {
        expect(press('/')).toEqual({ kind: 'focus-search' });
        expect(press('/', { shiftKey: true })).toEqual({
            kind: 'focus-search',
        });
    });

    it('ignores other keys', () => {
        expect(press('a')).toBeNull();
        expect(press('Escape')).toBeNull();
    });

    it('stays silent with Shift on a digit', () => {
        expect(press('2', { shiftKey: true })).toBeNull();
    });

    it.each(['ctrlKey', 'metaKey', 'altKey'] as const)(
        'stays silent with %s held',
        (modifier) => {
            expect(press('2', { [modifier]: true })).toBeNull();
            expect(press('n', { [modifier]: true })).toBeNull();
            expect(press('/', { [modifier]: true })).toBeNull();
        },
    );

    it('stays silent in an editable target', () => {
        expect(press('2', { targetIsEditable: true })).toBeNull();
        expect(press('/', { targetIsEditable: true })).toBeNull();
    });

    it('stays silent under an open layer', () => {
        expect(press('2', { hasOpenLayer: true })).toBeNull();
        expect(press('n', { hasOpenLayer: true })).toBeNull();
    });

    it('stays silent during composition and on a repeated key', () => {
        expect(press('2', { isComposing: true })).toBeNull();
        expect(press('2', { repeat: true })).toBeNull();
    });

    it('maps Ctrl+K and ⌘K to the palette, in a field too', () => {
        expect(press('k', { ctrlKey: true })).toEqual({ kind: 'palette' });
        expect(press('K', { metaKey: true })).toEqual({ kind: 'palette' });
        expect(press('k', { ctrlKey: true, targetIsEditable: true })).toEqual({
            kind: 'palette',
        });
    });

    it('keeps the palette shortcut silent under an open layer', () => {
        expect(press('k', { ctrlKey: true, hasOpenLayer: true })).toBeNull();
    });

    it('keeps the palette shortcut silent on repeat, composition and with Alt', () => {
        expect(press('k', { ctrlKey: true, repeat: true })).toBeNull();
        expect(press('k', { ctrlKey: true, isComposing: true })).toBeNull();
        expect(press('k', { ctrlKey: true, altKey: true })).toBeNull();
    });

    it('does not treat a bare k as the palette', () => {
        expect(press('k')).toBeNull();
    });
});
