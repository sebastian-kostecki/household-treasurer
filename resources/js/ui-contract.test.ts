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
    'components/BudgetCategoryAlignPlanAction.vue',
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

// Dead reka-ui Checkbox bindings tracked in GitHub issue #186.
// Fix the file, then remove it from this list (the test fails until you do).
const DEAD_CHECKBOX_BINDING_EXCEPTIONS = ['pages/OperationsImport.vue'];

const DEAD_CHECKBOX_BINDING = /:checked=|@update:checked|v-model:checked/;

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
});
