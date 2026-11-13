import type { VisitOptions } from '@inertiajs/core';

function pathnameOf(url: string): string {
    return new URL(url, 'http://localhost').pathname;
}

/**
 * Default Inertia visit options: a write (anything but GET) that returns to the
 * page it was made on keeps the scroll position. Only paths are compared, so a
 * redirect to the same list with a different query keeps the window too.
 * An explicit `preserveScroll` from the caller always wins.
 */
export function defaultVisitOptions(
    options: VisitOptions,
    currentPathname: string,
): Partial<VisitOptions> {
    const method = (options.method ?? 'get').toLowerCase();

    if (method === 'get' || options.preserveScroll !== undefined) {
        return {};
    }

    return {
        preserveScroll: (page) => pathnameOf(page.url) === currentPathname,
    };
}
