<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import TermDepositController from '@/actions/App/Http/Controllers/TermDepositController';
import AccountBankSelect from '@/components/AccountBankSelect.vue';
import InputError from '@/components/InputError.vue';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import type { AccountBank } from '@/lib/account-identity';
import {
    minorUnitsToMoneyInput,
    parseMoneyInputToMinorUnits,
} from '@/lib/format';
import type { TermDepositFormTermDeposit } from '@/types/term-deposits';

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    termDeposit?: TermDepositFormTermDeposit | null;
    showArchived?: boolean;
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
const openingBalanceMinorUnits = ref(0);
const openingBalanceInvalid = computed(
    () => parseMoneyInputToMinorUnits(openingBalanceInput.value) === null,
);

const formBinding = computed(() => {
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
    if (props.mode === 'create') {
        return openingBalanceMinorUnits.value;
    }

    if (!props.termDeposit) {
        return 0;
    }

    const openingDelta =
        openingBalanceMinorUnits.value -
        props.termDeposit.opening_balance_minor_units;

    return props.termDeposit.current_balance_minor_units + openingDelta;
});

const showNegativeBalanceWarning = computed(
    () => projectedCurrentBalanceMinorUnits.value < 0,
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
            openingBalanceMinorUnits.value =
                props.termDeposit.opening_balance_minor_units;
            openingBalanceInput.value = minorUnitsToMoneyInput(
                props.termDeposit.opening_balance_minor_units,
            );

            return;
        }

        name.value = '';
        bank.value = 'other';
        openingDate.value = new Date().toISOString().slice(0, 10);
        endDate.value = '';
        openingBalanceMinorUnits.value = 0;
        openingBalanceInput.value = '0,00';
    },
    { immediate: true },
);

watch(openingBalanceInput, (value) => {
    const parsed = parseMoneyInputToMinorUnits(value);

    if (parsed !== null) {
        openingBalanceMinorUnits.value = parsed;
    }
});

function closeDialog(): void {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <Form
                v-bind="formBinding"
                class="space-y-4"
                reset-on-success
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

                <div class="grid gap-2">
                    <Label for="term-deposit-name">
                        {{ t.term_deposits.name }}
                    </Label>
                    <Input
                        id="term-deposit-name"
                        name="name"
                        v-model="name"
                        required
                        autocomplete="off"
                        :aria-invalid="errors.name ? 'true' : undefined"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="account-bank">{{ t.term_deposits.bank }}</Label>
                    <AccountBankSelect
                        v-model="bank"
                        :invalid="Boolean(errors.bank)"
                    />
                    <InputError :message="errors.bank" />
                </div>

                <div v-if="mode === 'create'" class="grid gap-2">
                    <Label for="term-deposit-opening-date">
                        {{ t.term_deposits.opening_date }}
                    </Label>
                    <Input
                        id="term-deposit-opening-date"
                        name="opening_date"
                        v-model="openingDate"
                        type="date"
                        required
                        :aria-invalid="errors.opening_date ? 'true' : undefined"
                    />
                    <InputError :message="errors.opening_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="term-deposit-end-date">
                        {{ t.term_deposits.end_date }}
                    </Label>
                    <Input
                        id="term-deposit-end-date"
                        name="end_date"
                        v-model="endDate"
                        type="date"
                        :aria-invalid="errors.end_date ? 'true' : undefined"
                    />
                    <p class="text-muted-foreground text-sm">
                        {{ t.term_deposits.end_date_hint }}
                    </p>
                    <InputError :message="errors.end_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="term-deposit-opening-balance">
                        {{ t.term_deposits.opening_balance }}
                    </Label>
                    <Input
                        id="term-deposit-opening-balance"
                        v-model="openingBalanceInput"
                        inputmode="decimal"
                        autocomplete="off"
                        :aria-invalid="
                            openingBalanceInvalid ||
                            errors.opening_balance_minor_units
                                ? 'true'
                                : undefined
                        "
                    />
                    <input
                        type="hidden"
                        name="opening_balance_minor_units"
                        :value="openingBalanceMinorUnits"
                    />
                    <InputError
                        :message="
                            openingBalanceInvalid
                                ? t.term_deposits.opening_balance_invalid
                                : errors.opening_balance_minor_units
                        "
                    />
                </div>

                <Alert v-if="showNegativeBalanceWarning" variant="warning">
                    <AlertDescription>
                        {{ t.term_deposits.negative_balance_warning }}
                    </AlertDescription>
                </Alert>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
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
