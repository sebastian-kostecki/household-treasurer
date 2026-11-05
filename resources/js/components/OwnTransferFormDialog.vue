<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import OwnTransferController from '@/actions/App/Http/Controllers/OwnTransferController';
import AccountSelect from '@/components/AccountSelect.vue';
import FormField from '@/components/FormField.vue';
import FormPreview from '@/components/FormPreview.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import {
    minorUnitsToMoneyInput,
    parseMoneyInputToMinorUnits,
} from '@/lib/format';
import { previewHandEntryWarnings } from '@/lib/hand-entry-warning-preview';
import {
    buildOperationPreview,
    monthNamesLocativeFrom,
} from '@/lib/operation-dialog-preview';
import type { AccountPickerOption } from '@/lib/account-identity';

type FormBinding = InstanceType<typeof Form>['$props'];

export type OwnTransferFormTransfer = {
    id: number;
    transfer_date: string;
    amount_minor_units: number;
    description: string;
    raw_statement_descriptions?: string[];
    source_account_id: number;
    destination_account_id: number;
};

export type OwnTransferFormAccount = AccountPickerOption & {
    current_balance_minor_units: number;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    transfer?: OwnTransferFormTransfer | null;
    accounts: OwnTransferFormAccount[];
    /** Overrides the Wayfinder binding; used only by the dialogs kitchen sink. */
    form?: Pick<FormBinding, 'action' | 'method'>;
    /** Pre-fills the amount input; used only by the dialogs kitchen sink. */
    initialAmountInput?: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const transferDate = ref('');
const description = ref('');
const sourceAccountId = ref<number | ''>('');
const destinationAccountId = ref<number | ''>('');
const amountInput = ref('0,00');
const originalAmountMinorUnits = ref(0);
const originalSourceAccountId = ref<number | null>(null);
const busy = ref(false);

const formBinding = computed(() => {
    if (props.form) {
        return props.form;
    }

    return props.mode === 'create'
        ? OwnTransferController.store.form()
        : OwnTransferController.update.form(props.transfer ?? { id: 0 });
});

const parsedAmountMinorUnits = computed(() =>
    parseMoneyInputToMinorUnits(amountInput.value),
);
const amountInvalid = computed(
    () =>
        parsedAmountMinorUnits.value === null ||
        parsedAmountMinorUnits.value < 0,
);
const amountMinorUnits = computed(() =>
    amountInvalid.value ? 0 : (parsedAmountMinorUnits.value ?? 0),
);

const bankAccounts = computed(() =>
    props.accounts.filter((account) => account.type === 'bank'),
);

const cashAccounts = computed(() =>
    props.accounts.filter((account) => account.type === 'cash'),
);

const rawStatementDescription = computed(() =>
    (props.transfer?.raw_statement_descriptions ?? []).join('\n\n'),
);

const warningPreview = computed(() => {
    if (sourceAccountId.value === '') {
        return previewHandEntryWarnings({
            mode: props.mode,
            accounts: props.accounts,
            proposal: {
                kind: 'own_transfer',
                account_id: 0,
                amount_minor_units: 0,
                source_account_id: 0,
            },
        });
    }

    return previewHandEntryWarnings({
        mode: props.mode,
        accounts: props.accounts,
        editedRecord:
            props.mode === 'edit' && originalSourceAccountId.value !== null
                ? {
                      account_id: originalSourceAccountId.value,
                      signed_amount_minor_units: originalAmountMinorUnits.value,
                      source_account_id: originalSourceAccountId.value,
                  }
                : null,
        proposal: {
            kind: 'own_transfer',
            account_id: sourceAccountId.value,
            amount_minor_units: amountMinorUnits.value,
            source_account_id: sourceAccountId.value,
        },
    });
});

const preview = computed(() => {
    const result = warningPreview.value;

    return buildOperationPreview({
        toSpendReadings: [],
        accountBalance: {
            label: t.operations.preview_source_account_balance,
            beforeMinorUnits: result.accountBalanceBeforeMinorUnits,
            afterMinorUnits: result.accountBalanceAfterMinorUnits,
            warning: result.showNegativeBalanceWarning
                ? t.operations.negative_balance_warning
                : null,
        },
        texts: {
            toSpendLabel: t.operations.preview_to_spend,
            toSpendWarning: t.operations.to_spend_negative_preview_warning,
            monthNamesLocative: monthNamesLocativeFrom(t.budget),
        },
    });
});

const showPreview = computed(
    () => !amountInvalid.value && amountMinorUnits.value > 0,
);

watch(
    () => [props.open, props.mode, props.transfer] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        if (props.mode === 'edit' && props.transfer) {
            transferDate.value = props.transfer.transfer_date;
            description.value = props.transfer.description;
            sourceAccountId.value = props.transfer.source_account_id;
            destinationAccountId.value = props.transfer.destination_account_id;
            amountInput.value =
                props.initialAmountInput ??
                minorUnitsToMoneyInput(props.transfer.amount_minor_units);
            originalAmountMinorUnits.value = props.transfer.amount_minor_units;
            originalSourceAccountId.value = props.transfer.source_account_id;

            return;
        }

        transferDate.value = new Date().toISOString().slice(0, 10);
        description.value = '';
        sourceAccountId.value = bankAccounts.value[0]?.id ?? '';
        destinationAccountId.value = cashAccounts.value[0]?.id ?? '';
        amountInput.value = props.initialAmountInput ?? '0,00';
        originalAmountMinorUnits.value = 0;
        originalSourceAccountId.value = null;
    },
    { immediate: true },
);

function onOpenChange(next: boolean): void {
    if (!next && busy.value) {
        return;
    }

    emit('update:open', next);
}

function closeDialog(): void {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent :show-close-button="!busy">
            <Form
                v-bind="formBinding"
                class="grid gap-4"
                reset-on-success
                @start="busy = true"
                @finish="busy = false"
                @success="closeDialog"
                v-slot="{ errors, processing, reset, clearErrors }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{
                            mode === 'create'
                                ? t.operations.create_transfer_title
                                : t.operations.edit_transfer_title
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            mode === 'create'
                                ? t.operations.create_transfer_description
                                : t.operations.edit_transfer_description
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="form-field-row">
                    <FormField
                        id="transfer-amount"
                        :label="t.operations.amount"
                        money
                        :error="
                            amountInvalid
                                ? t.operations.amount_invalid
                                : errors.amount_minor_units
                        "
                        v-slot="{ id, describedBy, invalid, controlClass }"
                    >
                        <Input
                            :id="id"
                            v-model="amountInput"
                            :class="controlClass"
                            inputmode="decimal"
                            autocomplete="off"
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid"
                        />
                        <input
                            type="hidden"
                            name="amount_minor_units"
                            :value="amountMinorUnits"
                        />
                    </FormField>

                    <FormField
                        id="transfer-date"
                        :label="t.operations.date"
                        :error="errors.transfer_date"
                        v-slot="{ id, describedBy, invalid }"
                    >
                        <Input
                            :id="id"
                            name="transfer_date"
                            v-model="transferDate"
                            type="date"
                            required
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                </div>

                <FormField
                    id="source-account"
                    :label="t.operations.source_account"
                    :error="errors.source_account_id"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <AccountSelect
                        :id="id"
                        name="source_account_id"
                        v-model="sourceAccountId"
                        :accounts="accounts"
                        :placeholder="t.operations.select_account"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    id="destination-account"
                    :label="t.operations.destination_account"
                    :error="errors.destination_account_id"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <AccountSelect
                        :id="id"
                        name="destination_account_id"
                        v-model="destinationAccountId"
                        :accounts="accounts"
                        :placeholder="t.operations.select_account"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    id="transfer-description"
                    :label="t.operations.description"
                    :error="errors.description"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <Input
                        :id="id"
                        name="description"
                        v-model="description"
                        required
                        autocomplete="off"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    v-if="mode === 'edit' && rawStatementDescription !== ''"
                    id="transfer-raw-statement-description"
                    :label="t.operations.raw_statement_description"
                    group
                    v-slot="{ labelId }"
                >
                    <p class="form-readonly-text" :aria-labelledby="labelId">
                        {{ rawStatementDescription }}
                    </p>
                </FormField>

                <FormPreview
                    v-if="showPreview"
                    :rows="preview.rows"
                    :warnings="preview.warnings"
                />

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="processing"
                            @click="
                                () => {
                                    clearErrors();
                                    reset();
                                    closeDialog();
                                }
                            "
                        >
                            {{ t.operations.cancel }}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing || amountInvalid"
                    >
                        <Spinner v-if="processing" />
                        {{ t.operations.save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
