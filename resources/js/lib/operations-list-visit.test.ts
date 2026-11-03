import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';

const on = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({ router: { on } }));

import {
    isOperationsListVisit,
    listenForOperationsListVisits,
} from './operations-list-visit';

const visit = (method: string, path: string) => ({
    method,
    url: new URL(path, 'http://localhost'),
});

describe('isOperationsListVisit', () => {
    it('accepts a GET to the operations path, with a query', () => {
        expect(
            isOperationsListVisit(
                visit('get', '/operations?page=2'),
                '/operations',
            ),
        ).toBe(true);
        expect(
            isOperationsListVisit(visit('GET', '/operations'), '/operations'),
        ).toBe(true);
    });

    it('rejects other methods and other paths', () => {
        expect(
            isOperationsListVisit(visit('delete', '/incomes/1'), '/operations'),
        ).toBe(false);
        expect(
            isOperationsListVisit(visit('post', '/operations'), '/operations'),
        ).toBe(false);
        expect(
            isOperationsListVisit(visit('get', '/dashboard'), '/operations'),
        ).toBe(false);
    });
});

describe('listenForOperationsListVisits', () => {
    const handlers: Record<string, (event: unknown) => void> = {};
    const off = { start: vi.fn(), finish: vi.fn() };

    beforeEach(() => {
        off.start.mockClear();
        off.finish.mockClear();
        on.mockReset();
        on.mockImplementation(
            (event: 'start' | 'finish', handler: (event: unknown) => void) => {
                handlers[event] = handler;

                return off[event];
            },
        );
    });

    const start = (method: string, path: string) =>
        handlers.start({ detail: { visit: visit(method, path) } });

    it('reports busy for list visits and clears it on finish', () => {
        const onBusyChange = vi.fn();
        listenForOperationsListVisits('/operations', onBusyChange);

        start('get', '/operations?page=2');
        handlers.finish({});

        expect(onBusyChange.mock.calls).toEqual([[true], [false]]);
    });

    it('ignores visits that are not the list', () => {
        const onBusyChange = vi.fn();
        listenForOperationsListVisits('/operations', onBusyChange);

        start('delete', '/incomes/1');
        handlers.finish({});

        expect(onBusyChange).not.toHaveBeenCalled();
    });

    it('removes both listeners', () => {
        const stop = listenForOperationsListVisits('/operations', vi.fn());

        stop();

        expect(off.start).toHaveBeenCalledOnce();
        expect(off.finish).toHaveBeenCalledOnce();
    });
});
