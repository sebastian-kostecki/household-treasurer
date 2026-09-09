<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CsvImportController from '@/actions/App/Http/Controllers/CsvImportController';
import ImportPreviewRow from '@/components/ImportPreviewRow.vue';
import InputError from '@/components/InputError.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import type { AccountPickerOption } from '@/lib/account-identity';
import {
    consumedFirstLegKeys,
    showsPairCandidateBadge,
    syncPairSelection,
} from '@/lib/import-pair-selection';
import {
    emptySelection,
    initialSelection,
    rowSelectionValid,
    type CategoryRow,
    type ImportRow,
    type RowSelection,
    type TermDepositRow,
} from '@/lib/import-preview-rows';
import { operations as operationsRoute } from '@/routes';

type OtherAccountRow = AccountPickerOption & {
    current_balance_minor_units: number;
};

const {
    account,
    rows,
    incomeCategories,
    expenseCategories,
    otherAccounts,
    termDeposits,
} = defineProps<{
    account: AccountPickerOption & { current_balance_minor_units: number };
    rows: ImportRow[];
    incomeCategories: CategoryRow[];
    expenseCategories: CategoryRow[];
    otherAccounts: OtherAccountRow[];
    termDeposits: TermDepositRow[];
}>();

const t = useTranslations();
const duplicateWarningAcknowledged = ref(false);
const selections = ref<Record<number, RowSelection>>(
    Object.fromEntries(rows.map((row) => [row.index, initialSelection(row)])),
);

const validRowCount = computed(() => rows.filter((row) => row.valid).length);
const invalidRowCount = computed(() => rows.filter((row) => !row.valid).length);
const duplicateRowCount = computed(
    () => rows.filter((row) => row.valid && row.import_duplicate).length,
);
const pairRowCount = computed(
    () =>
        rows.filter(
            (row) =>
                row.valid &&
                showsPairCandidateBadge(
                    row.import_pair_candidate,
                    row.pair_candidates,
                    consumedFirstLegKeysSet.value,
                    selections.value[row.index] ?? emptySelection(row),
                ),
        ).length,
);
const consumedFirstLegKeysSet = computed(() =>
    consumedFirstLegKeys(selections.value),
);
const selectedRowCount = computed(
    () =>
        Object.values(selections.value).filter(
            (selection) => selection.selected,
        ).length,
);
const selectedImportDuplicateCount = computed(
    () =>
        rows.filter(
            (row) =>
                row.import_duplicate &&
                selections.value[row.index]?.selected === true,
        ).length,
);
const requiresDuplicateAcknowledgement = computed(
    () => selectedImportDuplicateCount.value > 0,
);
const canSubmit = computed(
    () =>
        (!requiresDuplicateAcknowledgement.value ||
            duplicateWarningAcknowledged.value) &&
        selectedRowCount.value > 0 &&
        Object.entries(selections.value).every(([index, selection]) => {
            if (!selection.selected) {
                return true;
            }

            const row = rows.find(
                (candidate) => candidate.index === Number(index),
            );

            if (row === undefined) {
                return false;
            }

            return rowSelectionValid(row, selection, termDeposits);
        }),
);

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.operations,
            href: operationsRoute(),
        },
        {
            title: t.operations.import_title,
            href: CsvImportController.create.url(),
        },
        {
            title: t.operations.import_preview,
            href: CsvImportController.showPreview.url(),
        },
    ],
});

function refreshPairSelections(): void {
    for (const row of rows) {
        const selection = selections.value[row.index];

        if (selection === undefined) {
            continue;
        }

        syncPairSelection(
            row.pair_candidates,
            selection,
            consumedFirstLegKeysSet.value,
        );
    }
}

watch(consumedFirstLegKeysSet, () => {
    refreshPairSelections();
});
</script>

<template>
    <Head :title="t.operations.import_preview" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold">
                    {{ t.operations.import_preview }}
                </h1>
                <p class="text-muted-foreground text-sm">
                    {{ t.operations.import_preview_description }}
                </p>
            </div>
            <Button as-child variant="outline">
                <Link :href="CsvImportController.create.url()">
                    {{ t.operations.import_back }}
                </Link>
            </Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">
                    {{ t.operations.import_summary_title }}
                </CardTitle>
                <CardDescription>
                    {{
                        t.operations.import_summary_description.replace(
                            ':account',
                            account.name,
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-2">
                <Badge variant="secondary">
                    {{
                        t.operations.import_valid_rows.replace(
                            ':count',
                            String(validRowCount),
                        )
                    }}
                </Badge>
                <Badge v-if="invalidRowCount > 0" variant="destructive">
                    {{
                        t.operations.import_invalid_rows.replace(
                            ':count',
                            String(invalidRowCount),
                        )
                    }}
                </Badge>
                <Badge v-if="duplicateRowCount > 0" variant="outline">
                    {{
                        t.operations.import_duplicate_rows.replace(
                            ':count',
                            String(duplicateRowCount),
                        )
                    }}
                </Badge>
                <Badge v-if="pairRowCount > 0" variant="outline">
                    {{
                        t.operations.import_pair_rows.replace(
                            ':count',
                            String(pairRowCount),
                        )
                    }}
                </Badge>
            </CardContent>
        </Card>

        <Alert>
            <AlertTriangle class="size-4" />
            <AlertTitle>{{
                t.operations.import_duplicate_warning_title
            }}</AlertTitle>
            <AlertDescription>
                {{ t.operations.import_duplicate_warning_body }}
            </AlertDescription>
        </Alert>

        <Alert v-if="pairRowCount > 0">
            <AlertTriangle class="size-4" />
            <AlertTitle>{{
                t.operations.import_pair_warning_title
            }}</AlertTitle>
            <AlertDescription>
                {{ t.operations.import_pair_warning_body }}
            </AlertDescription>
        </Alert>

        <Form
            v-bind="CsvImportController.store.form()"
            v-slot="{ errors, processing }"
            class="space-y-4"
        >
            <ListSurface>
                <div class="overflow-x-auto">
                    <div
                        class="import-preview-grid border-border/60 text-muted-foreground hidden border-b pb-2.5 text-[11px] font-medium tracking-wider uppercase xl:grid"
                    >
                        <span class="import-preview-cell-select">
                            {{ t.operations.import_select }}
                        </span>
                        <span class="import-preview-cell-date">
                            {{ t.operations.date }}
                        </span>
                        <span class="import-preview-cell-description">
                            {{ t.operations.description }}
                        </span>
                        <span class="import-preview-cell-amount">
                            {{ t.operations.amount }}
                        </span>
                        <span class="import-preview-cell-type">
                            {{ t.operations.type }}
                        </span>
                        <span class="import-preview-cell-classification">
                            {{ t.operations.import_classification }}
                        </span>
                        <span class="import-preview-cell-status">
                            {{ t.operations.import_status }}
                        </span>
                    </div>

                    <p
                        v-if="rows.length === 0"
                        class="text-muted-foreground py-6 text-center text-sm"
                    >
                        {{ t.operations.import_preview_empty }}
                    </p>

                    <ImportPreviewRow
                        v-for="row in rows"
                        :key="row.index"
                        :row="row"
                        v-model:selection="selections[row.index]"
                        :account-balance-minor-units="
                            account.current_balance_minor_units
                        "
                        :income-categories="incomeCategories"
                        :expense-categories="expenseCategories"
                        :other-accounts="otherAccounts"
                        :term-deposits="termDeposits"
                        :consumed-first-leg-keys="consumedFirstLegKeysSet"
                    />
                </div>
            </ListSurface>

            <div
                v-if="requiresDuplicateAcknowledgement"
                class="flex items-center gap-2"
            >
                <Checkbox
                    id="duplicate-warning"
                    name="duplicate_warning_acknowledged"
                    v-model="duplicateWarningAcknowledged"
                    value="1"
                />
                <Label for="duplicate-warning">
                    {{ t.operations.import_duplicate_acknowledge }}
                </Label>
            </div>
            <InputError :message="errors.duplicate_warning_acknowledged" />
            <InputError :message="errors.rows" />

            <div class="flex justify-end gap-2">
                <Button type="submit" :disabled="processing || !canSubmit">
                    {{ t.operations.import_confirm }}
                </Button>
            </div>
        </Form>
    </div>
</template>
