/// <reference types="node" />
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vite-plus/test';

const JS_ROOT = dirname(fileURLToPath(import.meta.url));

// Same regex as the /10x-ui hardcoded-values scan.
const HARDCODED =
    /#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(|oklch\(|-\[[0-9.]+(px|rem)\]|\b(bg|text|border|ring|outline|from|via|to|fill|stroke|shadow|divide)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black)\b/;

const BUDGET_VIEW_FILES = [
    'pages/Budget.vue',
    'pages/dev/BudgetKitchenSink.vue',
    'components/BudgetCategoryList.vue',
    'components/BudgetCategoryHeaderRow.vue',
    'components/BudgetCategoryRow.vue',
    'components/BudgetAmountCell.vue',
    'components/BudgetEmptyState.vue',
    'components/BudgetCategoryRowActions.vue',
    'components/BudgetMonthPicker.vue',
    'components/BudgetPlanSummaryStrip.vue',
    'components/StatPanel.vue',
    'components/ExecutionMeter.vue',
    'components/BudgetYearSwitcher.vue',
    'components/CreateBudgetDialog.vue',
    'components/CategoryFormDialog.vue',
    'components/CategoryColorPicker.vue',
    'components/CategoryIconPicker.vue',
];

// Budget confirmations go through ConfirmActionDialog (ADR-0040).
const BUDGET_CONFIRMATION_FILES = [
    'pages/Budget.vue',
    'components/BudgetCategoryRowActions.vue',
];

// Operation dialogs follow ADR-0040 and ADR-0041.
const OPERATION_DIALOG_FILES = [
    'components/ExpenseFormDialog.vue',
    'components/IncomeFormDialog.vue',
    'components/OwnTransferFormDialog.vue',
    'components/CapitalMovementFormDialog.vue',
];

// Dead reka-ui Checkbox bindings (GitHub issue #186 was the last exception).
// This list must stay empty: fix the file instead of adding it here.
const DEAD_CHECKBOX_BINDING_EXCEPTIONS: string[] = [];

const DEAD_CHECKBOX_BINDING = /:checked=|@update:checked|v-model:checked/;

// A Button that closes a dialog is ghost (ADR-0045). The list only shrinks:
// PasskeyItem keeps its own variant (ADR-0049).
const DIALOG_CANCEL_VARIANT_EXCEPTIONS: string[] = [
    'components/PasskeyItem.vue',
];

const DIALOG_CLOSE_BUTTON = /<DialogClose\b[^>]*>\s*<Button\b([^>]*)>/g;

function vueFilesOutsideUi(dir: string): string[] {
    return readdirSync(dir).flatMap((entry) => {
        const path = join(dir, entry);

        if (statSync(path).isDirectory()) {
            return relative(JS_ROOT, path) === join('components', 'ui')
                ? []
                : vueFilesOutsideUi(path);
        }

        return path.endsWith('.vue') ? [path] : [];
    });
}

describe('ui contract', () => {
    it.each(BUDGET_VIEW_FILES)(
        'has no hardcoded colours or arbitrary sizes in %s',
        (file) => {
            const source = readFileSync(join(JS_ROOT, file), 'utf8');

            expect(source.match(HARDCODED)?.[0] ?? null).toBeNull();
        },
    );

    it.each(BUDGET_CONFIRMATION_FILES)(
        'does not build a hand-written confirmation dialog in %s',
        (file) => {
            const source = readFileSync(join(JS_ROOT, file), 'utf8');

            expect(
                /import[^;]*\bDialogContent\b/.test(source),
                'use ConfirmActionDialog instead of Dialog + DialogContent',
            ).toBe(false);
        },
    );

    it.each(OPERATION_DIALOG_FILES)(
        'has no hardcoded colours or arbitrary sizes in %s',
        (file) => {
            const source = readFileSync(join(JS_ROOT, file), 'utf8');

            expect(source.match(HARDCODED)?.[0] ?? null).toBeNull();
        },
    );

    it.each(OPERATION_DIALOG_FILES)(
        'does not use an Alert or a checkbox in %s',
        (file) => {
            const source = readFileSync(join(JS_ROOT, file), 'utf8');

            expect(
                /@\/components\/ui\/alert/.test(source),
                'warnings go into FormPreview (ADR-0041), not an Alert',
            ).toBe(false);
            expect(
                source.includes('type="checkbox"'),
                'a choice is a SegmentedControl (ADR-0040), not a checkbox',
            ).toBe(false);
        },
    );

    it('has no dead reka-ui Checkbox bindings outside the exception list', () => {
        const offenders: string[] = [];
        const unusedExceptions = new Set(DEAD_CHECKBOX_BINDING_EXCEPTIONS);

        for (const path of vueFilesOutsideUi(JS_ROOT)) {
            const file = relative(JS_ROOT, path).split(sep).join('/');

            if (!DEAD_CHECKBOX_BINDING.test(readFileSync(path, 'utf8'))) {
                continue;
            }

            if (!unusedExceptions.delete(file)) {
                offenders.push(file);
            }
        }

        expect(
            offenders,
            'reka-ui Checkbox ignores :checked and @update:checked; use v-model',
        ).toEqual([]);

        expect(
            [...unusedExceptions],
            'usuń go z listy wyjątków (the binding is already fixed)',
        ).toEqual([]);
    });

    it('uses the ghost variant for every dialog cancel Button outside the exception list', () => {
        const offenders: string[] = [];
        const unusedExceptions = new Set(DIALOG_CANCEL_VARIANT_EXCEPTIONS);
        let found = 0;

        for (const path of vueFilesOutsideUi(JS_ROOT)) {
            const file = relative(JS_ROOT, path).split(sep).join('/');
            const source = readFileSync(path, 'utf8');
            let offends = false;

            for (const match of source.matchAll(DIALOG_CLOSE_BUTTON)) {
                found++;

                const variant = match[1].match(/\bvariant="([^"]*)"/)?.[1];

                if (variant !== 'ghost') {
                    offends = true;

                    if (!DIALOG_CANCEL_VARIANT_EXCEPTIONS.includes(file)) {
                        offenders.push(
                            `${file}: ${variant ? `variant="${variant}"` : 'no variant'}`,
                        );
                    }
                }
            }

            if (offends) {
                unusedExceptions.delete(file);
            }
        }

        expect(found, 'no <DialogClose> Button found').toBeGreaterThan(0);
        expect(
            offenders,
            'dialog cancel is variant="ghost" (ADR-0045)',
        ).toEqual([]);
        expect(
            [...unusedExceptions],
            'usuń go z listy wyjątków (the Button is already ghost)',
        ).toEqual([]);
    });
});

const CSS_ROOT = join(JS_ROOT, '..', 'css');

function read(file: string): string {
    return readFileSync(join(JS_ROOT, file), 'utf8');
}

function sourceFiles(dir: string): string[] {
    return readdirSync(dir).flatMap((entry) => {
        const path = join(dir, entry);

        if (entry === 'node_modules') {
            return [];
        }

        if (statSync(path).isDirectory()) {
            return sourceFiles(path);
        }

        return /\.(vue|ts)$/.test(path) ? [path] : [];
    });
}

describe('sidebar follows the Atrament Sidebar component (ADR-0053)', () => {
    it('takes the sidebar width from --sidebar-panel-width', () => {
        const utils = read('components/ui/sidebar/utils.ts');

        expect(utils).toMatch(
            /SIDEBAR_WIDTH\s*=\s*['"`]var\(--sidebar-panel-width\)['"`]/,
        );
        expect(utils).not.toContain('16rem');
    });

    it('defines --sidebar-panel-width as 224px in app.css', () => {
        const css = readFileSync(join(CSS_ROOT, 'app.css'), 'utf8');

        expect(css).toMatch(/--sidebar-panel-width:\s*224px/);
    });

    it('marks the current section with aria-current="page" from the same check as is-active', () => {
        const navMain = read('components/NavMain.vue');

        expect(navMain).toMatch(/:is-active="isActive\(item\)"/);
        expect(navMain).toMatch(
            /:aria-current="isActive\(item\) \? 'page' : undefined"/,
        );
    });

    it.each(['components/AppLogo.vue', 'components/UserInfo.vue'])(
        'centres the mark or avatar in the collapsed icon rail in %s',
        (file) => {
            expect(read(file)).toContain(
                'group-data-[collapsible=icon]:mx-auto',
            );
        },
    );

    it('names the budget year button with the section label and the year', () => {
        expect(read('components/BudgetYearSwitcher.vue')).toMatch(
            /:aria-label="`\$\{t\.shell\.budget_year\}: \$\{budgetYear\}`"/,
        );
    });

    it('renders section labels with label-caps', () => {
        expect(read('components/ui/sidebar/SidebarGroupLabel.vue')).toContain(
            'label-caps',
        );
    });

    it.each([
        'components/AppSidebar.vue',
        'components/AppLogo.vue',
        'components/BudgetYearSwitcher.vue',
        'components/NavMain.vue',
        'components/NavUser.vue',
        'components/UserInfo.vue',
        'components/ui/sidebar/SidebarGroupLabel.vue',
    ])('does not dim text with text-sidebar-foreground/80 in %s', (file) => {
        expect(read(file)).not.toContain('text-sidebar-foreground/80');
    });

    it('uses rounded-md and a semibold active state in the menu button cva', () => {
        const index = read('components/ui/sidebar/index.ts');

        expect(index).toContain('rounded-md');
        expect(index).not.toContain('rounded-xl');
        expect(index).toContain('data-[active=true]:font-semibold');
    });

    it('imports neither AppLogoIcon nor Vault as the product mark in resources/js', () => {
        // lib/category-appearance.ts keeps the Lucide Vault icon as a
        // user-selectable category icon (stored data), not the product mark.
        const categoryIconCatalogue = 'lib/category-appearance.ts';
        const offenders: string[] = [];

        for (const path of sourceFiles(JS_ROOT)) {
            const file = relative(JS_ROOT, path).split(sep).join('/');

            if (
                path === fileURLToPath(import.meta.url) ||
                file === categoryIconCatalogue
            ) {
                continue;
            }

            const source = readFileSync(path, 'utf8');

            if (/\bAppLogoIcon\b|\bVault\b/.test(source)) {
                offenders.push(file);
            }
        }

        expect(offenders, 'the product mark is BrandMark').toEqual([]);
    });

    it.each([
        'components/AppLogo.vue',
        'layouts/auth/AuthCardLayout.vue',
        'pages/Welcome.vue',
    ])('uses BrandMark in %s', (file) => {
        expect(read(file)).toContain('BrandMark');
    });

    it('does not render a slot in AppSidebar', () => {
        expect(read('components/AppSidebar.vue')).not.toContain('<slot');
    });
});
