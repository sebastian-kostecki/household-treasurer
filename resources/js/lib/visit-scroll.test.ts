import type { Page } from '@inertiajs/core';
import { describe, expect, it } from 'vite-plus/test';
import { defaultVisitOptions } from './visit-scroll';

function decide(
    options: Parameters<typeof defaultVisitOptions>[0],
    path: string,
    landed: string,
): boolean | undefined {
    const result = defaultVisitOptions(options, path);
    const preserve = result.preserveScroll;

    return typeof preserve === 'function'
        ? Boolean(preserve({ url: landed } as Page))
        : undefined;
}

describe('defaultVisitOptions', () => {
    it('adds nothing to a GET visit or one without a method', () => {
        expect(defaultVisitOptions({ method: 'get' }, '/budget')).toEqual({});
        expect(defaultVisitOptions({}, '/budget')).toEqual({});
    });

    it.each(['post', 'put', 'patch', 'delete'] as const)(
        'keeps scroll on %s when the same path comes back, even with another query',
        (method) => {
            expect(
                decide({ method }, '/operations', '/operations?page=2'),
            ).toBe(true);
        },
    );

    it('does not keep scroll when the write lands on another path', () => {
        expect(decide({ method: 'post' }, '/budget', '/dashboard')).toBe(false);
    });

    it('defers to an explicit preserveScroll', () => {
        expect(
            defaultVisitOptions(
                { method: 'post', preserveScroll: true },
                '/budget',
            ),
        ).toEqual({});
        expect(
            defaultVisitOptions(
                { method: 'post', preserveScroll: false },
                '/budget',
            ),
        ).toEqual({});
    });
});
