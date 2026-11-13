<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AccountReconciliationController from '@/actions/App/Http/Controllers/AccountReconciliationController';
import BalanceReconciliationReading from '@/components/BalanceReconciliationReading.vue';
import FormField from '@/components/FormField.vue';
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
import { useBalanceReconciliationPreview } from '@/composables/useBalanceReconciliationPreview';
import type { ReconciliationPreviewState } from '@/composables/useBalanceReconciliationPreview';
import { useTranslations } from '@/composables/useTranslations';
import { reconciliationDayIsAllowed } from '@/lib/balance-reconciliation';
import { parseMoneyInputToMinorUnits } from '@/lib/format';

type FormBinding = InstanceType<typeof Form>['$props'];

const props = defineProps<{
    open: boolean;
    account: { id: number; name: string; opening_date: string } | null;
    /** Overrides the Wayfinder binding; used only by the dialogs kitchen sink. */
    form?: Pick<FormBinding, 'action' | 'method'>;
    /** Replaces the preview read from the server; used only by the Accounts kitchen sink. */
    previewState?: ReconciliationPreviewState;
    /** Pre-fills the bank balance on open; used only by the Accounts kitchen sink. */
    initialBankBalance?: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const day = ref('');
const bankBalanceInput = ref('');
const busy = ref(false);

const today = computed(() => new Date().toISOString().slice(0, 10));

const dayAllowed = computed(
    () =>
        props.account !== null &&
        reconciliationDayIsAllowed(
            day.value,
            props.account.opening_date,
            today.value,
        ),
);

const bankBalanceMinorUnits = computed(() =>
    parseMoneyInputToMinorUnits(bankBalanceInput.value),
);

const bankBalanceInvalid = computed(() => bankBalanceMinorUnits.value === null);

watch(
    () => [props.open, props.account?.id] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        day.value = today.value;
        bankBalanceInput.value = props.initialBankBalance ?? '';
    },
    { immediate: true },
);

const { state: fetchedState } = useBalanceReconciliationPreview(
    computed(() => props.open),
    computed(() => props.account?.id ?? null),
    computed(() => (dayAllowed.value ? day.value : '')),
    computed(() => props.previewState === undefined),
);

const state = computed(() => props.previewState ?? fetchedState.value);

const formBinding = computed(
    () =>
        props.form ??
        AccountReconciliationController.store.form(props.account?.id ?? 0),
);

const title = computed(() =>
    t.accounts.reconcile_title.replace(':name', props.account?.name ?? ''),
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
                @start="busy = true"
                @finish="busy = false"
                @success="closeDialog"
                v-slot="{ errors, processing, clearErrors }"
            >
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription>
                        {{ t.accounts.reconcile_description }}
                    </DialogDescription>
                </DialogHeader>

                <div class="form-field-row">
                    <FormField
                        id="reconcile-date"
                        :label="t.accounts.reconcile_date"
                        :error="
                            dayAllowed
                                ? errors.reconciled_on
                                : t.accounts.reconcile_date_invalid
                        "
                        v-slot="{ id, describedBy, invalid }"
                    >
                        <Input
                            :id="id"
                            name="reconciled_on"
                            v-model="day"
                            type="date"
                            required
                            :min="account?.opening_date"
                            :max="today"
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid"
                        />
                    </FormField>

                    <FormField
                        id="reconcile-bank-balance"
                        :label="t.accounts.reconcile_bank_balance"
                        money
                        :error="
                            bankBalanceInput !== '' && bankBalanceInvalid
                                ? t.accounts.reconcile_bank_balance_invalid
                                : errors.bank_balance_minor_units
                        "
                        v-slot="{ id, describedBy, invalid, controlClass }"
                    >
                        <Input
                            :id="id"
                            v-model="bankBalanceInput"
                            :class="controlClass"
                            inputmode="decimal"
                            autocomplete="off"
                            required
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid"
                        />
                        <input
                            type="hidden"
                            name="bank_balance_minor_units"
                            :value="bankBalanceMinorUnits ?? ''"
                        />
                    </FormField>
                </div>

                <BalanceReconciliationReading
                    :state="state"
                    :bank-balance-minor-units="bankBalanceMinorUnits"
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
                                    closeDialog();
                                }
                            "
                        >
                            {{ t.accounts.cancel }}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="
                            processing || bankBalanceInvalid || !dayAllowed
                        "
                    >
                        <Spinner v-if="processing" />
                        {{ t.accounts.reconcile_save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
