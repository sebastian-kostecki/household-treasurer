<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed, ref } from 'vue';
import CsvImportController from '@/actions/App/Http/Controllers/CsvImportController';
import AccountSelect from '@/components/AccountSelect.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import ListSurface from '@/components/ListSurface.vue';
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
import { formatDate, formatMoney } from '@/lib/format';
import type { AccountPickerOption } from '@/lib/account-identity';
import { operations as operationsRoute } from '@/routes';

type ImportRow = {
    index: number;
    operation_date: string | null;
    description: string | null;
    counterparty: string | null;
    raw_statement_description: string | null;
    amount_grosze: number | null;
    valid: boolean;
    invalid_reason: string | null;
};

type CategoryRow = {
    id: number;
    name: string;
    budget_year: number;
};

type OtherAccountRow = AccountPickerOption;

type RowSelection = {
    selected: boolean;
    type: 'income' | 'expense' | 'refund' | 'own_transfer';
    category_id: number | '';
    other_account_id: number | '';
};

const {
    account,
    rows,
    incomeCategories,
    expenseCategories,
    otherAccounts,
} = defineProps<{
    account: AccountPickerOption;
    rows: ImportRow[];
    incomeCategories: CategoryRow[];
    expenseCategories: CategoryRow[];
    otherAccounts: OtherAccountRow[];
}>();

const t = useTranslations();
const duplicateWarningAcknowledged = ref(false);
const selections = ref<Record<number, RowSelection>>(
    Object.fromEntries(
        rows.map((row) => [
            row.index,
            {
                selected: false,
                type: defaultType(row),
                category_id: '',
                other_account_id: '',
            },
        ]),
    ),
);

const validRowCount = computed(
    () => rows.filter((row) => row.valid).length,
);
const invalidRowCount = computed(
    () => rows.filter((row) => !row.valid).length,
);
const selectedRowCount = computed(
    () =>
        Object.values(selections.value).filter(
            (selection) => selection.selected,
        ).length,
);
const canSubmit = computed(
    () =>
        duplicateWarningAcknowledged.value &&
        selectedRowCount.value > 0 &&
        Object.entries(selections.value).every(([index, selection]) => {
            if (!selection.selected) {
                return true;
            }

            const row = rows.find(
                (candidate) => candidate.index === Number(index),
            );

            return (
                row?.valid === true &&
                selection.type !== undefined &&
                (selection.type === 'own_transfer'
                    ? selection.other_account_id !== ''
                    : selection.category_id !== '')
            );
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

function defaultType(row: ImportRow): 'income' | 'expense' | 'refund' {
    if ((row.amount_grosze ?? 0) < 0) {
        return 'expense';
    }

    return 'income';
}

function categoriesForRow(row: ImportRow, type: RowSelection['type']): CategoryRow[] {
    if (!row.operation_date) {
        return [];
    }

    const year = Number(row.operation_date.slice(0, 4));
    const source =
        type === 'income' ? incomeCategories : expenseCategories;

    return source.filter((category) => category.budget_year === year);
}

function rowAmountClass(row: ImportRow): string {
    if ((row.amount_grosze ?? 0) < 0) {
        return 'text-destructive';
    }

    return 'text-success';
}

function rowCategoriesEmpty(row: ImportRow, type: RowSelection['type']): boolean {
    return categoriesForRow(row, type).length === 0;
}

function emptyCategoryLabel(type: RowSelection['type']): string {
    return type === 'income'
        ? t.operations.no_income_category_for_year
        : t.operations.no_expense_category_for_year;
}

function onTypeChange(row: ImportRow): void {
    const selection = selections.value[row.index];

    if (selection === undefined) {
        return;
    }

    selection.category_id = '';
    selection.other_account_id = '';
}
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
            </CardContent>
        </Card>

        <Alert>
            <AlertTriangle class="size-4" />
            <AlertTitle>{{ t.operations.import_duplicate_warning_title }}</AlertTitle>
            <AlertDescription>
                {{ t.operations.import_duplicate_warning_body }}
            </AlertDescription>
        </Alert>

        <Form
            v-bind="CsvImportController.store.form()"
            v-slot="{ errors, processing }"
            class="space-y-4"
        >
            <ListSurface>
                <div class="overflow-x-auto">
                    <table class="list-surface-table">
                        <thead>
                            <tr>
                                <th class="pr-3">
                                    {{ t.operations.import_select }}
                                </th>
                                <th>{{ t.operations.date }}</th>
                                <th>{{ t.operations.description }}</th>
                                <th>{{ t.operations.counterparty }}</th>
                                <th>{{ t.operations.raw_statement_description }}</th>
                                <th class="text-right">
                                    {{ t.operations.amount }}
                                </th>
                                <th>{{ t.operations.type }}</th>
                                <th>{{ t.operations.import_classification }}</th>
                                <th>{{ t.operations.import_status }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="9"
                                    class="text-muted-foreground py-6 text-center"
                                >
                                    {{ t.operations.import_preview_empty }}
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.index"
                                class="align-top"
                            >
                                <td class="pr-3">
                                    <Checkbox
                                        :id="`row-selected-${row.index}`"
                                        v-model="selections[row.index].selected"
                                        :disabled="!row.valid"
                                    />
                                    <input
                                        type="hidden"
                                        :name="`rows[${row.index}][index]`"
                                        :value="row.index"
                                    />
                                    <input
                                        type="hidden"
                                        :name="`rows[${row.index}][selected]`"
                                        :value="
                                            selections[row.index]?.selected
                                                ? '1'
                                                : '0'
                                        "
                                    />
                                </td>
                                <td>
                                    {{
                                        row.operation_date
                                            ? formatDate(row.operation_date)
                                            : '—'
                                    }}
                                </td>
                                <td>{{ row.description ?? '—' }}</td>
                                <td>{{ row.counterparty ?? '—' }}</td>
                                <td class="max-w-xs whitespace-pre-wrap">
                                    {{ row.raw_statement_description ?? '—' }}
                                </td>
                                <td
                                    class="tabular-amount"
                                    :class="rowAmountClass(row)"
                                >
                                    {{
                                        row.amount_grosze !== null
                                            ? formatMoney(row.amount_grosze)
                                            : '—'
                                    }}
                                </td>
                                <td>
                                    <select
                                        v-if="row.valid"
                                        :id="`row-type-${row.index}`"
                                        v-model="selections[row.index].type"
                                        :name="`rows[${row.index}][type]`"
                                        class="native-select min-w-36"
                                        @change="onTypeChange(row)"
                                    >
                                        <option value="income">
                                            {{ t.operations.type_income }}
                                        </option>
                                        <option value="expense">
                                            {{ t.operations.type_expense }}
                                        </option>
                                        <option value="refund">
                                            {{ t.operations.type_refund }}
                                        </option>
                                        <option value="own_transfer">
                                            {{ t.operations.type_own_transfer }}
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <AccountSelect
                                        v-if="
                                            row.valid &&
                                            selections[row.index].type ===
                                                'own_transfer'
                                        "
                                        :id="`row-other-account-${row.index}`"
                                        :name="`rows[${row.index}][other_account_id]`"
                                        v-model="
                                            selections[row.index].other_account_id
                                        "
                                        :accounts="otherAccounts"
                                        :placeholder="t.operations.import_other_account"
                                        class="min-w-44"
                                    />
                                    <select
                                        v-else-if="row.valid"
                                        :id="`row-category-${row.index}`"
                                        v-model="selections[row.index].category_id"
                                        :name="`rows[${row.index}][category_id]`"
                                        class="native-select min-w-44"
                                        :disabled="
                                            rowCategoriesEmpty(
                                                row,
                                                selections[row.index].type,
                                            )
                                        "
                                    >
                                        <option value="" disabled>
                                            {{
                                                rowCategoriesEmpty(
                                                    row,
                                                    selections[row.index].type,
                                                )
                                                    ? emptyCategoryLabel(
                                                          selections[row.index]
                                                              .type,
                                                      )
                                                    : t.operations.select_category
                                            }}
                                        </option>
                                        <option
                                            v-for="category in categoriesForRow(
                                                row,
                                                selections[row.index].type,
                                            )"
                                            :key="category.id"
                                            :value="category.id"
                                        >
                                            {{ category.name }}
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <Badge
                                        v-if="row.valid"
                                        variant="secondary"
                                    >
                                        {{ t.operations.import_row_valid }}
                                    </Badge>
                                    <div v-else class="space-y-1">
                                        <Badge variant="destructive">
                                            {{ t.operations.import_row_invalid }}
                                        </Badge>
                                        <p class="text-muted-foreground text-xs">
                                            {{ row.invalid_reason }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </ListSurface>

            <div class="flex items-center gap-2">
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
