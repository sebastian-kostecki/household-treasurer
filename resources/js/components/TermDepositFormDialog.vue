<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import TermDepositController from '@/actions/App/Http/Controllers/TermDepositController';
import AccountBankSelect from '@/components/AccountBankSelect.vue';
import FormField from '@/components/FormField.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import type { AccountBank } from '@/lib/account-identity';
import {
    minorUnitsToMoneyInput,
    parseMoneyInputToMinorUnits,
} from '@/lib/format';
import type { TermDepositFormTermDeposit } from '@/types/term-deposits';

type FormBinding = InstanceType<typeof Form>['$props'];

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    termDeposit?: TermDepositFormTermDeposit | null;
    showArchived?: boolean;
    /** Overrides the Wayfinder binding; used only by the dialogs kitchen sink. */
    form?: Pick<FormBinding, 'action' | 'method'>;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const name = ref('');
const bank = ref<AccountBank>('other');
const openingDate = ref('');
const endDate = ref('');
const openingBalanceInput = ref('0,00');
const openingBalanceMinorUnits = computed(() =>
    parseMoneyInputToMinorUnits(openingBalanceInput.value),
);
const openingBalanceInvalid = computed(
    () => openingBalanceMinorUnits.value === null,
);

const formBinding = computed(() => {
    if (props.form) {
        return props.form;
    }

    const options = props.showArchived
        ? { query: { show_archived: 1 } }
        : undefined;

    return props.mode === 'create'
        ? TermDepositController.store.form(options)
        : TermDepositController.update.form(
              props.termDeposit ?? { id: 0 },
              options,
          );
});

const projectedCurrentBalanceMinorUnits = computed(() => {
    const openingBalance = openingBalanceMinorUnits.value ?? 0;

    if (props.mode === 'create') {
        return openingBalance;
    }

    if (!props.termDeposit) {
        return 0;
    }

    const openingDelta =
        openingBalance - props.termDeposit.opening_balance_minor_units;

    return props.termDeposit.current_balance_minor_units + openingDelta;
});

const showNegativeBalanceWarning = computed(
    () =>
        !openingBalanceInvalid.value &&
        projectedCurrentBalanceMinorUnits.value < 0,
);

watch(
    () => [props.open, props.mode, props.termDeposit] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        if (props.mode === 'edit' && props.termDeposit) {
            name.value = props.termDeposit.name;
            bank.value = props.termDeposit.bank;
            openingDate.value = props.termDeposit.opening_date;
            endDate.value = props.termDeposit.end_date ?? '';
            openingBalanceInput.value = minorUnitsToMoneyInput(
                props.termDeposit.opening_balance_minor_units,
            );

            return;
        }

        name.value = '';
        bank.value = 'other';
        openingDate.value = new Date().toISOString().slice(0, 10);
        endDate.value = '';
        openingBalanceInput.value = '0,00';
    },
    { immediate: true },
);

const busy = ref(false);

function closeDialog(): void {
    emit('update:open', false);
}

function onOpenChange(next: boolean): void {
    if (!next && busy.value) {
        return;
    }

    emit('update:open', next);
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
                                ? t.term_deposits.create_title
                                : t.term_deposits.edit_title
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            mode === 'create'
                                ? t.term_deposits.create_description
                                : t.term_deposits.edit_description
                        }}
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    id="term-deposit-name"
                    :label="t.term_deposits.name"
                    :error="errors.name"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <Input
                        :id="id"
                        name="name"
                        v-model="name"
                        required
                        autocomplete="off"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    id="term-deposit-bank"
                    :label="t.term_deposits.bank"
                    :error="errors.bank"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <AccountBankSelect
                        :id="id"
                        v-model="bank"
                        :aria-describedby="describedBy"
                        :invalid="Boolean(invalid)"
                    />
                </FormField>

                <FormField
                    v-if="mode === 'create'"
                    id="term-deposit-opening-date"
                    :label="t.term_deposits.opening_date"
                    :error="errors.opening_date"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <Input
                        :id="id"
                        name="opening_date"
                        v-model="openingDate"
                        type="date"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    id="term-deposit-end-date"
                    :label="t.term_deposits.end_date"
                    :hint="t.term_deposits.end_date_hint"
                    :error="errors.end_date"
                    v-slot="{ id, describedBy, invalid }"
                >
                    <Input
                        :id="id"
                        name="end_date"
                        v-model="endDate"
                        type="date"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>

                <FormField
                    id="term-deposit-opening-balance"
                    :label="t.term_deposits.opening_balance"
                    money
                    :error="
                        openingBalanceInvalid
                            ? t.term_deposits.opening_balance_invalid
                            : errors.opening_balance_minor_units
                    "
                    v-slot="{ id, describedBy, invalid, controlClass }"
                >
                    <Input
                        :id="id"
                        v-model="openingBalanceInput"
                        :class="controlClass"
                        inputmode="decimal"
                        autocomplete="off"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                    <input
                        type="hidden"
                        name="opening_balance_minor_units"
                        :value="openingBalanceMinorUnits ?? ''"
                    />
                </FormField>

                <Alert v-if="showNegativeBalanceWarning" variant="warning">
                    <AlertDescription>
                        {{ t.term_deposits.negative_balance_warning }}
                    </AlertDescription>
                </Alert>

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
                            {{ t.term_deposits.cancel }}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing || openingBalanceInvalid"
                    >
                        <Spinner v-if="processing" />
                        {{ t.term_deposits.save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
