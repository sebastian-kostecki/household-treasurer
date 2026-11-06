<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CsvImportController from '@/actions/App/Http/Controllers/CsvImportController';
import AccountSelect from '@/components/AccountSelect.vue';
import FormField from '@/components/FormField.vue';
import ImportColumnMapping from '@/components/ImportColumnMapping.vue';
import ImportRecognizedFields from '@/components/ImportRecognizedFields.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import {
    defaultColumnIndices,
    formatForChosenFile,
    importColumnLabels,
} from '@/lib/import-start';
import type { ImportFormat, ImportStartAccount } from '@/lib/import-start';
import { operations as operationsRoute } from '@/routes';
import { index as accountsIndex } from '@/routes/accounts';

const { accounts } = defineProps<{
    accounts: ImportStartAccount[];
}>();

const t = useTranslations();

const selectedFile = ref<File | null>(null);
const fileText = ref<string | null>(null);
const fileReadError = ref<string | null>(null);
const importFormat = ref<ImportFormat>('other');
const formatChosenByUser = ref(false);
const hasHeader = ref(true);
const accountId = ref<number | ''>(accounts[0]?.id ?? '');
const amountLayout = ref<'signed' | 'split'>('signed');
const dateColumn = ref<number | ''>('');
const descriptionColumn = ref<number | ''>('');
const counterpartyColumn = ref<number | ''>('');
const amountColumn = ref<number | ''>('');
const inflowColumn = ref<number | ''>('');
const outflowColumn = ref<number | ''>('');

const isMbankFormat = computed(() => importFormat.value === 'mbank');
const isBnpParibasFormat = computed(() => importFormat.value === 'bnp_paribas');
const isNamedFormat = computed(
    () => isMbankFormat.value || isBnpParibasFormat.value,
);

const isXlsxFile = computed(
    () => selectedFile.value?.name.toLowerCase().endsWith('.xlsx') ?? false,
);

// "Inny" maps columns from text, and the server takes only CSV for it.
const fileError = computed<string | null>(
    () =>
        fileReadError.value ??
        (importFormat.value === 'other' && isXlsxFile.value
            ? t.operations.import_other_needs_csv
            : null),
);

const columnLabels = computed<string[]>(() =>
    fileText.value === null || isNamedFormat.value
        ? []
        : importColumnLabels(
              fileText.value,
              hasHeader.value,
              t.operations.import_column,
          ),
);

const canSubmit = computed(() => {
    if (selectedFile.value === null || accountId.value === '') {
        return false;
    }

    if (isNamedFormat.value) {
        return true;
    }

    return (
        columnLabels.value.length > 0 &&
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

function resetColumns(): void {
    dateColumn.value = '';
    descriptionColumn.value = '';
    counterpartyColumn.value = '';
    amountColumn.value = '';
    inflowColumn.value = '';
    outflowColumn.value = '';
}

function applyDefaultColumns(count: number): void {
    const indices = defaultColumnIndices(count);

    dateColumn.value = indices.date;
    descriptionColumn.value = indices.description;
    counterpartyColumn.value = '';
    amountColumn.value = indices.amount;
    inflowColumn.value = indices.inflow;
    outflowColumn.value = indices.outflow;
}

// Defaults are set only when a new file is loaded or the format becomes
// "Inny"; toggling the header keeps the column count, so choices stay.
watch(importFormat, (format) => {
    if (format === 'other' && columnLabels.value.length > 0) {
        applyDefaultColumns(columnLabels.value.length);
    }
});

// The column labels come from the first non-empty line, so the head of the
// file is enough; the server reads and limits the whole file.
const FILE_HEAD_BYTES = 64 * 1024;

async function loadFileText(file: File): Promise<void> {
    const text = await file.slice(0, FILE_HEAD_BYTES).text();

    // A later file was chosen while this one was loading.
    if (selectedFile.value !== file) {
        return;
    }

    fileText.value = text;

    if (importFormat.value === 'other') {
        applyDefaultColumns(importColumnLabels(text, true, '').length);
    }
}

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    selectedFile.value = file;
    fileText.value = null;
    fileReadError.value = null;
    resetColumns();

    if (file === null) {
        return;
    }

    importFormat.value = formatForChosenFile(
        file.name,
        importFormat.value,
        formatChosenByUser.value,
    );

    if (!isXlsxFile.value) {
        loadFileText(file).catch((error: unknown) => {
            console.error(
                new Error('Import: reading the chosen file failed', {
                    cause: error,
                }),
            );

            // A later file was chosen while this one was loading.
            if (selectedFile.value === file) {
                fileReadError.value = t.operations.import_file_read_failed;
            }
        });
    }
}
</script>

<template>
    <Head :title="t.operations.import_title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="page-title">
                    {{ t.operations.import_title }}
                </h1>
                <p class="meta text-muted-foreground">
                    {{ t.operations.import_step_mapping }}
                </p>
            </div>
            <Button as-child variant="outline">
                <Link :href="operationsRoute()">
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    {{ t.operations.import_back }}
                </Link>
            </Button>
        </div>

        <section v-if="accounts.length === 0" class="form-panel">
            <IndexEmptyState
                :title="t.operations.import_no_accounts"
                :description="t.operations.import_no_accounts_description"
            >
                <template #actions>
                    <Button as-child>
                        <Link :href="accountsIndex()">
                            {{ t.operations.import_open_accounts }}
                        </Link>
                    </Button>
                </template>
            </IndexEmptyState>
        </section>

        <Form
            v-else
            v-bind="CsvImportController.preview.form()"
            v-slot="{ errors, processing }"
            class="form-panel"
        >
            <div class="form-panel-header">
                <h2 class="section-title">
                    {{
                        isMbankFormat
                            ? t.operations.import_format_title
                            : isBnpParibasFormat
                              ? t.operations.import_bnp_paribas_title
                              : t.operations.import_mapping_title
                    }}
                </h2>
                <p class="meta text-muted-foreground">
                    {{
                        isMbankFormat
                            ? t.operations.import_mbank_description
                            : isBnpParibasFormat
                              ? t.operations.import_bnp_paribas_description
                              : t.operations.import_mapping_description
                    }}
                </p>
            </div>

            <div class="form-panel-body">
                <FormField
                    :label="t.operations.import_format"
                    :error="errors.import_format"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <select
                        :id="id"
                        name="import_format"
                        v-model="importFormat"
                        class="native-select"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                        @change="formatChosenByUser = true"
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
                </FormField>

                <FormField
                    :label="t.operations.import_file"
                    :error="fileError ?? errors.file"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <Input
                        :id="id"
                        name="file"
                        type="file"
                        accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                        @change="onFileChange"
                    />
                </FormField>

                <FormField
                    :label="t.operations.account"
                    :error="errors.account_id"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <AccountSelect
                        :id="id"
                        name="account_id"
                        v-model="accountId"
                        :accounts="accounts"
                        :placeholder="t.operations.select_account"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <ImportRecognizedFields v-if="isMbankFormat" format="mbank" />
                <ImportRecognizedFields
                    v-else-if="isBnpParibasFormat"
                    format="bnp_paribas"
                />
                <ImportColumnMapping
                    v-else
                    v-model:has-header="hasHeader"
                    v-model:amount-layout="amountLayout"
                    v-model:date-column="dateColumn"
                    v-model:description-column="descriptionColumn"
                    v-model:counterparty-column="counterpartyColumn"
                    v-model:amount-column="amountColumn"
                    v-model:inflow-column="inflowColumn"
                    v-model:outflow-column="outflowColumn"
                    :column-labels="columnLabels"
                    :errors="errors"
                />
            </div>

            <div class="form-panel-footer">
                <Button type="submit" :disabled="processing || !canSubmit">
                    <Spinner v-if="processing" />
                    {{ t.operations.import_preview }}
                </Button>
            </div>
        </Form>
    </div>
</template>
