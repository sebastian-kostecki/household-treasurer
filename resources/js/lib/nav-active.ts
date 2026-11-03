function pathOf(href: string): string {
    if (/^[a-z][a-z0-9+.-]*:\/\//i.test(href)) {
        return new URL(href).pathname;
    }

    return href.split(/[?#]/)[0];
}

function normalizePath(path: string): string {
    const trimmed = path.replace(/\/+$/, '');

    return trimmed === '' ? '/' : trimmed;
}

export function isCurrentSection(href: string, currentPath: string): boolean {
    const path = normalizePath(pathOf(href));
    const current = normalizePath(pathOf(currentPath));

    if (current === path) {
        return true;
    }

    if (path === '/') {
        return false;
    }

    return current.startsWith(`${path}/`);
}
