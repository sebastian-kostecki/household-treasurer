<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CsvImportController from '@/actions/App/Http/Controllers/CsvImportController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { operations as operationsRoute } from '@/routes';

type AccountRow = {
    id: number;
    name: string;
    opening_date: string;
};

type ImportFormat = 'mbank' | 'bnp_paribas' | 'other';

const { accounts } = defineProps<{
    accounts: AccountRow[];
}>();

const t = useTranslations();

const selectedFile = ref<File | null>(null);
const importFormat = ref<ImportFormat>('other');
const hasHeader = ref(true);
const accountId = ref<number | ''>(accounts[0]?.id ?? '');
const amountLayout = ref<'signed' | 'split'>('signed');
const dateColumn = ref<number | ''>('');
const descriptionColumn = ref<number | ''>('');
const amountColumn = ref<number | ''>('');
const inflowColumn = ref<number | ''>('');
const outflowColumn = ref<number | ''>('');
const columnLabels = ref<string[]>([]);

const isMbankFormat = computed(() => importFormat.value === 'mbank');
const isBnpParibasFormat = computed(() => importFormat.value === 'bnp_paribas');
const isNamedFormat = computed(
    () => isMbankFormat.value || isBnpParibasFormat.value,
);

const canSubmit = computed(() => {
    if (selectedFile.value === null || accountId.value === '') {
        return false;
    }

    if (isNamedFormat.value) {
        return true;
    }

    return (
        dateColumn.value !== '' &&
        descriptionColumn.value !== '' &&
        (amountLayout.value === 'signed'
            ? amountColumn.value !== ''
            : inflowColumn.value !== '' && outflowColumn.value !== '')
    );
});

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
    ],
});

watch(selectedFile, async (file) => {
    columnLabels.value = [];

    if (file === null) {
        dateColumn.value = '';
        descriptionColumn.value = '';
        amountColumn.value = '';
        inflowColumn.value = '';
        outflowColumn.value = '';

        return;
    }

    if (file.name.toLowerCase().endsWith('.csv')) {
        importFormat.value = 'mbank';
    } else if (file.name.toLowerCase().endsWith('.xlsx')) {
        importFormat.value = 'bnp_paribas';
    }

    if (isNamedFormat.value) {
        return;
    }

    const text = await file.text();
    const firstLine = text.split(/\r?\n/).find((line) => line.trim() !== '');

    if (firstLine === undefined) {
        return;
    }

    const delimiter = firstLine.includes(';') && !firstLine.includes(',')
        ? ';'
        : firstLine.split(';').length > firstLine.split(',').length
          ? ';'
          : ',';

    const cells = parseCsvLine(firstLine, delimiter);

    columnLabels.value = hasHeader.value
        ? cells.map((cell, index) =>
              cell.trim() !== ''
                  ? cell.trim()
                  : `${t.operations.import_column} ${index + 1}`,
          )
        : cells.map(
              (_, index) => `${t.operations.import_column} ${index + 1}`,
          );

    dateColumn.value = 0;
    descriptionColumn.value = Math.min(1, columnLabels.value.length - 1);
    amountColumn.value = Math.min(2, columnLabels.value.length - 1);
    inflowColumn.value = Math.min(2, columnLabels.value.length - 1);
    outflowColumn.value = Math.min(3, columnLabels.value.length - 1);
});

watch(importFormat, () => {
    if (selectedFile.value === null || isNamedFormat.value) {
        columnLabels.value = [];

        return;
    }

    const file = selectedFile.value;
    selectedFile.value = null;
    selectedFile.value = file;
});

watch(hasHeader, () => {
    if (selectedFile.value !== null && !isNamedFormat.value) {
        const file = selectedFile.value;
        selectedFile.value = null;
        selectedFile.value = file;
    }
});

function parseCsvLine(line: string, delimiter: string): string[] {
    const cells: string[] = [];
    let current = '';
    let inQuotes = false;

    for (let index = 0; index < line.length; index++) {
        const character = line[index];

        if (character === '"') {
            inQuotes = !inQuotes;
            continue;
        }

        if (character === delimiter && !inQuotes) {
            cells.push(current);
            current = '';
            continue;
        }

        current += character;
    }

    cells.push(current);

    return cells;
}

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    selectedFile.value = input.files?.[0] ?? null;
}
</script>

<template>
    <Head :title="t.operations.import_title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold">
                    {{ t.operations.import_title }}
                </h1>
                <p class="text-muted-foreground text-sm">
                    {{ t.operations.import_step_mapping }}
                </p>
            </div>
            <Button as-child variant="outline">
                <Link :href="operationsRoute()">
                    {{ t.operations.import_back }}
                </Link>
            </Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">
                    {{
                        isMbankFormat
                            ? t.operations.import_format_title
                            : isBnpParibasFormat
                              ? t.operations.import_bnp_paribas_title
                              : t.operations.import_mapping_title
                    }}
                </CardTitle>
                <CardDescription>
                    {{
                        isMbankFormat
                            ? t.operations.import_mbank_description
                            : isBnpParibasFormat
                              ? t.operations.import_bnp_paribas_description
                              : t.operations.import_mapping_description
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="CsvImportController.preview.form()"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <div class="grid gap-2">
                        <Label for="import-format">
                            {{ t.operations.import_format }}
                        </Label>
                        <select
                            id="import-format"
                            name="import_format"
                            v-model="importFormat"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                            required
                        >
                            <option value="mbank">
                                {{ t.operations.import_format_mbank }}
                            </option>
                            <option value="bnp_paribas">
                                {{ t.operations.import_format_bnp_paribas }}
                            </option>
                            <option value="other">
                                {{ t.operations.import_format_other }}
                            </option>
                        </select>
                        <InputError :message="errors.import_format" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="import-file">
                            {{ t.operations.import_file }}
                        </Label>
                        <Input
                            id="import-file"
                            name="file"
                            type="file"
                            accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            required
                            @change="onFileChange"
                        />
                        <InputError :message="errors.file" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="import-account">
                            {{ t.operations.account }}
                        </Label>
                        <select
                            id="import-account"
                            name="account_id"
                            v-model="accountId"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                            required
                        >
                            <option value="" disabled>
                                {{ t.operations.select_account }}
                            </option>
                            <option
                                v-for="account in accounts"
                                :key="account.id"
                                :value="account.id"
                            >
                                {{ account.name }}
                            </option>
                        </select>
                        <InputError :message="errors.account_id" />
                    </div>

                    <div
                        v-if="isMbankFormat"
                        class="rounded-lg border p-4 text-sm"
                    >
                        <p class="font-medium">
                            {{ t.operations.import_mbank_fields_title }}
                        </p>
                        <ul class="text-muted-foreground mt-2 list-inside list-disc space-y-1">
                            <li>{{ t.operations.import_mbank_field_date }}</li>
                            <li>
                                {{ t.operations.import_mbank_field_description }}
                            </li>
                            <li>{{ t.operations.import_mbank_field_amount }}</li>
                        </ul>
                    </div>

                    <div
                        v-else-if="isBnpParibasFormat"
                        class="rounded-lg border p-4 text-sm"
                    >
                        <p class="font-medium">
                            {{ t.operations.import_bnp_paribas_fields_title }}
                        </p>
                        <ul class="text-muted-foreground mt-2 list-inside list-disc space-y-1">
                            <li>{{ t.operations.import_bnp_paribas_field_date }}</li>
                            <li>
                                {{ t.operations.import_bnp_paribas_field_description }}
                            </li>
                            <li>{{ t.operations.import_bnp_paribas_field_amount }}</li>
                        </ul>
                    </div>

                    <template v-else>
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="import-has-header"
                                name="has_header"
                                :checked="hasHeader"
                                value="1"
                                @update:checked="hasHeader = $event === true"
                            />
                            <Label for="import-has-header">
                                {{ t.operations.import_has_header }}
                            </Label>
                        </div>
                        <input
                            v-if="!hasHeader"
                            type="hidden"
                            name="has_header"
                            value="0"
                        />

                        <fieldset
                            v-if="columnLabels.length > 0"
                            class="grid gap-4 rounded-lg border p-4"
                        >
                            <legend class="px-1 text-sm font-medium">
                                {{ t.operations.import_columns }}
                            </legend>

                            <div class="grid gap-2">
                                <Label for="import-date-column">
                                    {{ t.operations.date }}
                                </Label>
                                <select
                                    id="import-date-column"
                                    name="date_column"
                                    v-model="dateColumn"
                                    class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                    required
                                >
                                    <option
                                        v-for="(label, index) in columnLabels"
                                        :key="`date-${index}`"
                                        :value="index"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                                <InputError :message="errors.date_column" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="import-description-column">
                                    {{ t.operations.description }}
                                </Label>
                                <select
                                    id="import-description-column"
                                    name="description_column"
                                    v-model="descriptionColumn"
                                    class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                    required
                                >
                                    <option
                                        v-for="(label, index) in columnLabels"
                                        :key="`description-${index}`"
                                        :value="index"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.description_column"
                                />
                            </div>

                            <div class="grid gap-3">
                                <Label>{{
                                    t.operations.import_amount_layout
                                }}</Label>
                                <div class="flex flex-wrap gap-4">
                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            v-model="amountLayout"
                                            type="radio"
                                            name="amount_layout"
                                            value="signed"
                                        />
                                        {{ t.operations.import_amount_signed }}
                                    </label>
                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            v-model="amountLayout"
                                            type="radio"
                                            name="amount_layout"
                                            value="split"
                                        />
                                        {{ t.operations.import_amount_split }}
                                    </label>
                                </div>
                            </div>

                            <div
                                v-if="amountLayout === 'signed'"
                                class="grid gap-2"
                            >
                                <Label for="import-amount-column">
                                    {{ t.operations.amount }}
                                </Label>
                                <select
                                    id="import-amount-column"
                                    name="amount_column"
                                    v-model="amountColumn"
                                    class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                    required
                                >
                                    <option
                                        v-for="(label, index) in columnLabels"
                                        :key="`amount-${index}`"
                                        :value="index"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                                <InputError :message="errors.amount_column" />
                            </div>

                            <template v-else>
                                <div class="grid gap-2">
                                    <Label for="import-inflow-column">
                                        {{ t.operations.import_inflow_column }}
                                    </Label>
                                    <select
                                        id="import-inflow-column"
                                        name="inflow_column"
                                        v-model="inflowColumn"
                                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                        required
                                    >
                                        <option
                                            v-for="(label, index) in columnLabels"
                                            :key="`inflow-${index}`"
                                            :value="index"
                                        >
                                            {{ label }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="errors.inflow_column"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="import-outflow-column">
                                        {{ t.operations.import_outflow_column }}
                                    </Label>
                                    <select
                                        id="import-outflow-column"
                                        name="outflow_column"
                                        v-model="outflowColumn"
                                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                        required
                                    >
                                        <option
                                            v-for="(label, index) in columnLabels"
                                            :key="`outflow-${index}`"
                                            :value="index"
                                        >
                                            {{ label }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="errors.outflow_column"
                                    />
                                </div>
                            </template>
                        </fieldset>
                    </template>

                    <div class="flex justify-end gap-2">
                        <Button
                            type="submit"
                            :disabled="processing || !canSubmit"
                        >
                            {{ t.operations.import_preview }}
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
