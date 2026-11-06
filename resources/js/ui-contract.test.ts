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
// PasskeyItem keeps its own variant (ADR-0049), the align confirmation is
// converted in Phase 2 of the cancel-button-ghost change.
const DIALOG_CANCEL_VARIANT_EXCEPTIONS: string[] = [
    'components/PasskeyItem.vue',
    'components/BudgetCategoryAlignPlanAction.vue',
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
