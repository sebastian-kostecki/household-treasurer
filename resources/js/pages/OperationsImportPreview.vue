<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed, ref } from 'vue';
import CsvImportController from '@/actions/App/Http/Controllers/CsvImportController';
import InputError from '@/components/InputError.vue';
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
import { formatDate, formatMoney } from '@/lib/format';
import { operations as operationsRoute } from '@/routes';

type ImportRow = {
    index: number;
    operation_date: string | null;
    description: string | null;
    amount_grosze: number | null;
    valid: boolean;
    invalid_reason: string | null;
};

type CategoryRow = {
    id: number;
    name: string;
    budget_year: number;
};

type OtherAccountRow = {
    id: number;
    name: string;
};

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
    account: {
        id: number;
        name: string;
    };
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
            title: t.operations.import_csv,
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
            <Card>
                <CardContent class="overflow-x-auto pt-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="pb-2 pr-3 font-medium">
                                    {{ t.operations.import_select }}
                                </th>
                                <th class="pb-2 font-medium">
                                    {{ t.operations.date }}
                                </th>
                                <th class="pb-2 font-medium">
                                    {{ t.operations.description }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ t.operations.amount }}
                                </th>
                                <th class="pb-2 font-medium">
                                    {{ t.operations.type }}
                                </th>
                                <th class="pb-2 font-medium">
                                    {{ t.operations.import_classification }}
                                </th>
                                <th class="pb-2 font-medium">
                                    {{ t.operations.import_status }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in rows"
                                :key="row.index"
                                class="border-b align-top"
                            >
                                <td class="py-3 pr-3">
                                    <Checkbox
                                        :id="`row-selected-${row.index}`"
                                        :checked="selections[row.index]?.selected"
                                        :disabled="!row.valid"
                                        @update:checked="
                                            selections[row.index].selected =
                                                $event === true
                                        "
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
                                <td class="py-3">
                                    {{
                                        row.operation_date
                                            ? formatDate(row.operation_date)
                                            : '—'
                                    }}
                                </td>
                                <td class="py-3">
                                    {{ row.description ?? '—' }}
                                </td>
                                <td
                                    class="py-3 text-right font-medium"
                                    :class="rowAmountClass(row)"
                                >
                                    {{
                                        row.amount_grosze !== null
                                            ? formatMoney(row.amount_grosze)
                                            : '—'
                                    }}
                                </td>
                                <td class="py-3">
                                    <select
                                        v-if="row.valid"
                                        :id="`row-type-${row.index}`"
                                        v-model="selections[row.index].type"
                                        :name="`rows[${row.index}][type]`"
                                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full min-w-36 rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                        :disabled="!selections[row.index]?.selected"
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
                                <td class="py-3">
                                    <select
                                        v-if="
                                            row.valid &&
                                            selections[row.index].type ===
                                                'own_transfer'
                                        "
                                        :id="`row-other-account-${row.index}`"
                                        v-model="
                                            selections[row.index].other_account_id
                                        "
                                        :name="`rows[${row.index}][other_account_id]`"
                                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full min-w-44 rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                        :disabled="!selections[row.index]?.selected"
                                    >
                                        <option value="" disabled>
                                            {{ t.operations.import_other_account }}
                                        </option>
                                        <option
                                            v-for="otherAccount in otherAccounts"
                                            :key="otherAccount.id"
                                            :value="otherAccount.id"
                                        >
                                            {{ otherAccount.name }}
                                        </option>
                                    </select>
                                    <select
                                        v-else-if="row.valid"
                                        :id="`row-category-${row.index}`"
                                        v-model="selections[row.index].category_id"
                                        :name="`rows[${row.index}][category_id]`"
                                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full min-w-44 rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                        :disabled="!selections[row.index]?.selected"
                                    >
                                        <option value="" disabled>
                                            {{ t.operations.select_category }}
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
                                <td class="py-3">
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
                </CardContent>
            </Card>

            <div class="flex items-center gap-2">
                <Checkbox
                    id="duplicate-warning"
                    name="duplicate_warning_acknowledged"
                    :checked="duplicateWarningAcknowledged"
                    value="1"
                    @update:checked="
                        duplicateWarningAcknowledged = $event === true
                    "
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
