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

    it('does not collapse to an icon strip on desktop', () => {
        expect(read('components/AppSidebar.vue')).not.toContain(
            'collapsible="icon"',
        );
        expect(read('components/AppShell.vue')).toContain(':open="true"');
        expect(read('layouts/app/AppSidebarLayout.vue')).not.toContain(
            'AppSidebarHeader',
        );
    });

    it.each([
        'components/AppSidebar.vue',
        'components/NavMain.vue',
        'components/NavUser.vue',
        'components/AppLogo.vue',
        'components/UserInfo.vue',
        'components/BudgetYearSwitcher.vue',
    ])('has no icon-mode classes in %s', (file) => {
        expect(read(file)).not.toContain('group-data-[collapsible=icon]');
    });

    it('renders the budget year switcher as a labelled group of pressed-state segments', () => {
        const switcher = read('components/BudgetYearSwitcher.vue');

        expect(switcher).toContain('role="group"');
        expect(switcher).toContain(':aria-labelledby="labelId"');
        expect(switcher).toContain(':aria-pressed=');
        expect(switcher).toContain('t.shell.add_budget_for_year');
        expect(switcher).not.toContain('DropdownMenu');
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
        'components/LandingAppPreview.vue',
    ])('uses BrandMark in %s', (file) => {
        expect(read(file)).toContain('BrandMark');
    });

    it.each([
        'pages/Welcome.vue',
        'components/LandingAppPreview.vue',
        'components/LandingFeatureCard.vue',
    ])(
        'keeps %s free of literal routes except /register and of text-success (ADR-0057)',
        (file) => {
            const source = read(file);

            expect(source).not.toMatch(/href="\/(?!register")/);
            expect(source).not.toContain('text-success');
        },
    );

    it('keeps the landing preview free of anything focusable (ADR-0057)', () => {
        const source = read('components/LandingAppPreview.vue');

        expect(source).toContain('aria-hidden="true"');
        expect(source).not.toContain('<Link');
        expect(source).not.toContain('<a ');
        expect(source).not.toContain('<button');
        expect(source).not.toContain('<Button');
        expect(source).not.toContain('tabindex');
        expect(source).not.toContain('<input');
    });

    it.each([
        'layouts/auth/AuthCardLayout.vue',
        'layouts/AuthLayout.vue',
        'components/AuthCardFooter.vue',
        'components/PasskeyVerify.vue',
        'pages/auth/ConfirmPassword.vue',
        'pages/auth/ForgotPassword.vue',
        'pages/auth/Login.vue',
        'pages/auth/Register.vue',
        'pages/auth/ResetPassword.vue',
        'pages/auth/TwoFactorChallenge.vue',
        'pages/auth/VerifyEmail.vue',
    ])(
        'keeps %s free of tabindex, text-success and literal routes (ADR-0056)',
        (file) => {
            const source = read(file);

            expect(source).not.toMatch(/tabindex/i);
            expect(source).not.toContain('text-success');
            expect(source).not.toContain('href="/');
            expect(source).not.toContain("'/dashboard'");
        },
    );

    it.each([
        'layouts/auth/AuthCardLayout.vue',
        'layouts/AuthLayout.vue',
        'components/AuthCardFooter.vue',
        'components/PasskeyVerify.vue',
        'pages/auth/ConfirmPassword.vue',
        'pages/auth/ForgotPassword.vue',
        'pages/auth/Login.vue',
        'pages/auth/ResetPassword.vue',
        'pages/auth/TwoFactorChallenge.vue',
        'pages/auth/VerifyEmail.vue',
    ])('keeps %s free of a literal form action (ADR-0056)', (file) => {
        expect(read(file)).not.toContain('action="/');
    });

    it('does not render a slot in AppSidebar', () => {
        expect(read('components/AppSidebar.vue')).not.toContain('<slot');
    });

    it('marks the shortcut in NavMain for sighted users only', () => {
        const navMain = read('components/NavMain.vue');

        expect(navMain).toMatch(/<kbd[^>]*aria-hidden="true"/);
        expect(navMain).toContain('aria-keyshortcuts');
    });

    it('gives the palette input the combobox role and a list to control', () => {
        const palette = read('components/CommandPalette.vue');

        expect(palette).toContain('role="combobox"');
        expect(palette).toContain(':aria-controls="listId"');
        expect(palette).toContain('aria-autocomplete="list"');
    });

    it('keeps ui/dialog free of the palette', () => {
        const dialogDir = join(JS_ROOT, 'components/ui/dialog');

        for (const file of readdirSync(dialogDir)) {
            expect(
                readFileSync(join(dialogDir, file), 'utf8'),
                `components/ui/dialog/${file}`,
            ).not.toMatch(/command/i);
        }
    });

    it('takes the views from useAppViews, not from a local list', () => {
        const sidebar = read('components/AppSidebar.vue');

        expect(sidebar).toContain('useAppViews()');
        expect(sidebar).not.toContain('NavItem[]');
    });

    it('mounts the new budget dialog in the layout, not inside the sidebar', () => {
        // On a phone the sidebar content lives in a sheet and is unmounted while
        // it is closed, so a dialog inside it cannot be opened from the palette.
        expect(read('components/AppSidebar.vue')).not.toContain(
            'CreateBudgetDialog',
        );
        expect(read('layouts/app/AppSidebarLayout.vue')).toContain(
            '<CreateBudgetDialog',
        );
    });
});

// Components that are only table rows show amounts without the unit; the panel
// footer says "Kwoty w zł" (ADR-0037, ADR-0058). The list only grows.
const UNITLESS_TABLE_ROW_FILES = [
    'components/FinancialOperationRow.vue',
    'components/OwnTransferRow.vue',
    'components/CapitalMovementRow.vue',
    'components/OperationsDayRow.vue',
    'components/MonthYearSummary.vue',
    'components/BudgetCategoryRow.vue',
    'components/BudgetAmountCell.vue',
    'components/MonthPlanItemRow.vue',
];

describe('table rows carry no amount unit (ADR-0058)', () => {
    it.each(UNITLESS_TABLE_ROW_FILES)(
        'does not import formatMoney or formatSignedMoney in %s',
        (file) => {
            const source = readFileSync(join(JS_ROOT, file), 'utf8');

            expect(source).not.toMatch(/\bformat(Signed)?Money\b(?!Amount)/);
        },
    );
});

// The „Kategorie” view is read-only and shows no amounts (S-18), so none of
// its files formats money; it is built from the shared surface blocks and its
// values come from tokens.
const CATEGORIES_VIEW_FILES = [
    'pages/Categories.vue',
    'pages/dev/CategoriesKitchenSink.vue',
    'components/CategoryListRow.vue',
    'components/CategoryArchiveSection.vue',
];

describe('the Categories view follows the Atrament surface blocks', () => {
    it.each(CATEGORIES_VIEW_FILES)('has no hardcoded values in %s', (file) => {
        const source = readFileSync(join(JS_ROOT, file), 'utf8');

        expect(source).not.toMatch(HARDCODED);
    });

    it.each(CATEGORIES_VIEW_FILES)('shows no amount in %s', (file) => {
        const source = readFileSync(join(JS_ROOT, file), 'utf8');

        expect(source).not.toMatch(/\bformat(Signed)?Money(Amount)?\b/);
    });

    it('builds the page from ListSurface, CategoryListRow and IndexEmptyState', () => {
        const page = readFileSync(
            join(JS_ROOT, 'pages/Categories.vue'),
            'utf8',
        );

        expect(page).toContain('<ListSurface');
        expect(page).toContain('<CategoryListRow');
        expect(page).toContain('<IndexEmptyState');
    });

    it('shows each category with CategoryChip and a Wayfinder link to Operations', () => {
        const row = readFileSync(
            join(JS_ROOT, 'components/CategoryListRow.vue'),
            'utf8',
        );

        expect(row).toContain('<CategoryChip');
        expect(row).toContain("from '@/routes'");
        expect(row).not.toContain('href="/operations');
    });
});
