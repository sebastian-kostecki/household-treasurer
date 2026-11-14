<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, inject, ref, watch } from 'vue';
import CapitalMovementController from '@/actions/App/Http/Controllers/CapitalMovementController';
import AccountSelect from '@/components/AccountSelect.vue';
import FormField from '@/components/FormField.vue';
import FormPreview from '@/components/FormPreview.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
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
import { OPERATIONS_RETURN_QUERY } from '@/lib/operations-list-query';
import { useOperationAmountInput } from '@/composables/useOperationAmountInput';
import {
    termDepositPickerOptions,
    type AccountBank,
    type AccountPickerOption,
} from '@/lib/account-identity';
import { minorUnitsToMoneyInput } from '@/lib/format';
import { previewHandEntryWarnings } from '@/lib/hand-entry-warning-preview';
import {
    buildOperationPreview,
    monthNamesLocativeFrom,
} from '@/lib/operation-dialog-preview';

type FormBinding = InstanceType<typeof Form>['$props'];

type CapitalMovementDirection = 'to_term_deposit' | 'from_term_deposit';

export type CapitalMovementFormMovement = {
    id: number;
    movement_date: string;
    amount_minor_units: number;
    description: string;
    direction: CapitalMovementDirection;
    account_id: number;
    term_deposit_id: number;
};

export type CapitalMovementFormAccount = AccountPickerOption & {
    current_balance_minor_units: number;
};

export type CapitalMovementFormTermDeposit = {
    id: number;
    name: string;
    bank: AccountBank;
    mark_url: string | null;
    opening_date: string;
    current_balance_minor_units: number;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    movement?: CapitalMovementFormMovement | null;
    accounts: CapitalMovementFormAccount[];
    termDeposits: CapitalMovementFormTermDeposit[];
    /** Overrides the Wayfinder binding; used only by the dialogs kitchen sink. */
    form?: Pick<FormBinding, 'action' | 'method'>;
    /** Pre-fills the amount input; used only by the dialogs kitchen sink. */
    initialAmountInput?: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const movementDate = ref('');
const description = ref('');
const direction = ref<CapitalMovementDirection>('to_term_deposit');
const accountId = ref<number | ''>('');
const termDepositId = ref<number | ''>('');
const termDepositOptions = computed(() =>
    termDepositPickerOptions(props.termDeposits),
);
const amountInput = ref('0,00');
const originalAmountMinorUnits = ref(0);
const originalAccountId = ref<number | null>(null);
const originalTermDepositId = ref<number | null>(null);
const originalDirection = ref<CapitalMovementDirection>('to_term_deposit');
const busy = ref(false);

const operationsReturnQuery = inject(OPERATIONS_RETURN_QUERY, undefined);
const writeOptions = computed(() =>
    operationsReturnQuery ? { query: operationsReturnQuery.value } : undefined,
);

const formBinding = computed(() => {
    if (props.form) {
        return props.form;
    }

    return props.mode === 'create'
        ? CapitalMovementController.store.form(writeOptions.value)
        : CapitalMovementController.update.form(
              props.movement ?? { id: 0 },
              writeOptions.value,
          );
});

const directionOptions = computed(() => [
    {
        value: 'to_term_deposit' as const,
        label: t.operations.capital_movement_to_term_deposit,
    },
    {
        value: 'from_term_deposit' as const,
        label: t.operations.capital_movement_from_term_deposit,
    },
]);

const { amountInvalid, amountMinorUnits, showPreview } =
    useOperationAmountInput(amountInput);

const warningPreview = computed(() => {
    if (accountId.value === '' || termDepositId.value === '') {
        return previewHandEntryWarnings({
            mode: props.mode,
            accounts: props.accounts,
            termDeposits: props.termDeposits,
            proposal: {
                kind: 'capital_movement',
                account_id: 0,
                term_deposit_id: 0,
                amount_minor_units: 0,
                direction: 'to_term_deposit',
            },
        });
    }

    return previewHandEntryWarnings({
        mode: props.mode,
        accounts: props.accounts,
        termDeposits: props.termDeposits,
        editedRecord:
            props.mode === 'edit' &&
            originalAccountId.value !== null &&
            originalTermDepositId.value !== null
                ? {
                      account_id: originalAccountId.value,
                      term_deposit_id: originalTermDepositId.value,
                      signed_amount_minor_units: originalAmountMinorUnits.value,
                      direction: originalDirection.value,
                  }
                : null,
        proposal: {
            kind: 'capital_movement',
            account_id: accountId.value,
            term_deposit_id: termDepositId.value,
            amount_minor_units: amountMinorUnits.value,
            direction: direction.value,
        },
    });
});

const preview = computed(() => {
    const result = warningPreview.value;

    return buildOperationPreview({
        toSpendReadings: [],
        accountBalance: {
            label: t.operations.preview_account_balance,
            beforeMinorUnits: result.accountBalanceBeforeMinorUnits,
            afterMinorUnits: result.accountBalanceAfterMinorUnits,
            warning: result.showNegativeBalanceWarning
                ? t.operations.negative_balance_warning
                : null,
        },
        termDepositBalance: {
            label: t.operations.preview_term_deposit_balance,
            beforeMinorUnits: result.termDepositBalanceBeforeMinorUnits,
            afterMinorUnits: result.termDepositBalanceAfterMinorUnits,
            warning: result.showTermDepositNegativeBalanceWarning
                ? t.term_deposits.negative_balance_warning
                : null,
        },
        texts: {
            toSpendLabel: t.operations.preview_to_spend,
            toSpendWarning: t.operations.to_spend_negative_preview_warning,
            monthNamesLocative: monthNamesLocativeFrom(t.budget),
        },
    });
});

watch(
    () => [props.open, props.mode, props.movement] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        if (props.mode === 'edit' && props.movement) {
            movementDate.value = props.movement.movement_date;
            description.value = props.movement.description;
            direction.value = props.movement.direction;
            accountId.value = props.movement.account_id;
            termDepositId.value = props.movement.term_deposit_id;
            amountInput.value =
                props.initialAmountInput ??
                minorUnitsToMoneyInput(props.movement.amount_minor_units);
            originalAmountMinorUnits.value = props.movement.amount_minor_units;
            originalAccountId.value = props.movement.account_id;
            originalTermDepositId.value = props.movement.term_deposit_id;
            originalDirection.value = props.movement.direction;

            return;
        }

        movementDate.value = new Date().toISOString().slice(0, 10);
        description.value = '';
        direction.value = 'to_term_deposit';
        accountId.value = props.accounts[0]?.id ?? '';
        termDepositId.value = props.termDeposits[0]?.id ?? '';
        amountInput.value = props.initialAmountInput ?? '0,00';
        originalAmountMinorUnits.value = 0;
        originalAccountId.value = null;
        originalTermDepositId.value = null;
        originalDirection.value = 'to_term_deposit';
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
                                ? t.operations.create_capital_movement_title
                                : t.operations.edit_capital_movement_title
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            mode === 'create'
                                ? t.operations
                                      .create_capital_movement_description
                                : t.operations.edit_capital_movement_description
                        }}
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    id="movement-direction"
                    :label="t.operations.capital_movement_direction"
                    group
                    :error="errors.direction"
                    v-slot="{ labelId, describedBy, invalid }"
                >
                    <SegmentedControl
                        v-model="direction"
                        :options="directionOptions"
                        :ariaLabelledby="labelId"
                        :ariaDescribedby="describedBy"
                        :ariaInvalid="invalid"
                        name="direction"
                        block
                    />
                </FormField>

                <div class="form-field-row">
                    <FormField
                        id="movement-amount"
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
                        id="movement-date"
                        :label="t.operations.date"
                        :error="errors.movement_date"
                        v-slot="{ id, describedBy, invalid }"
                    >
                        <Input
                            :id="id"
                            name="movement_date"
                            v-model="movementDate"
                            type="date"
                            required
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                </div>

                <FormField
                    id="movement-account"
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
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    id="movement-term-deposit"
                    :label="t.operations.term_deposit"
                    :error="errors.term_deposit_id"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <AccountSelect
                        :id="id"
                        name="term_deposit_id"
                        v-model="termDepositId"
                        :accounts="termDepositOptions"
                        :placeholder="t.operations.term_deposit"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    id="movement-description"
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

                <FormPreview
                    v-if="showPreview"
                    :rows="preview.rows"
                    :warnings="preview.warnings"
                />

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="ghost"
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
