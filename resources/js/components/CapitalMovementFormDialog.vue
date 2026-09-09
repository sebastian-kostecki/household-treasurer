<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CapitalMovementController from '@/actions/App/Http/Controllers/CapitalMovementController';
import AccountSelect from '@/components/AccountSelect.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';
import type { AccountPickerOption } from '@/lib/account-identity';
import {
    minorUnitsToMoneyInput,
    parseMoneyInputToMinorUnits,
} from '@/lib/format';
import { previewHandEntryWarnings } from '@/lib/hand-entry-warning-preview';

export type CapitalMovementFormMovement = {
    id: number;
    movement_date: string;
    amount_minor_units: number;
    description: string;
    direction: 'to_term_deposit' | 'from_term_deposit';
    account_id: number;
    term_deposit_id: number;
};

export type CapitalMovementFormAccount = AccountPickerOption & {
    current_balance_minor_units: number;
};

export type CapitalMovementFormTermDeposit = {
    id: number;
    name: string;
    opening_date: string;
    current_balance_minor_units: number;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    movement?: CapitalMovementFormMovement | null;
    accounts: CapitalMovementFormAccount[];
    termDeposits: CapitalMovementFormTermDeposit[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const movementDate = ref('');
const description = ref('');
const direction = ref<'to_term_deposit' | 'from_term_deposit'>('to_term_deposit');
const accountId = ref<number | ''>('');
const termDepositId = ref<number | ''>('');
const amountInput = ref('0,00');
const amountMinorUnits = ref(0);
const originalAmountMinorUnits = ref(0);
const originalAccountId = ref<number | null>(null);
const originalTermDepositId = ref<number | null>(null);
const originalDirection = ref<'to_term_deposit' | 'from_term_deposit'>('to_term_deposit');

const formBinding = computed(() =>
    props.mode === 'create'
        ? CapitalMovementController.store.form()
        : CapitalMovementController.update.form(props.movement ?? { id: 0 }),
);

const selectedDirection = computed({
    get: () => direction.value,
    set: (value: string | undefined) => {
        if (value === 'to_term_deposit' || value === 'from_term_deposit') {
            direction.value = value;
        }
    },
});

const selectedTermDepositId = computed({
    get: () =>
        termDepositId.value === '' ? undefined : String(termDepositId.value),
    set: (value: string | undefined) => {
        termDepositId.value = value ? Number(value) : '';
    },
});

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

    const originalSignedAccountEffect =
        originalDirection.value === 'from_term_deposit'
            ? originalAmountMinorUnits.value
            : -originalAmountMinorUnits.value;

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
                      signed_amount_minor_units: originalSignedAccountEffect,
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

const showNegativeBalanceWarning = computed(
    () => warningPreview.value.showNegativeBalanceWarning,
);

const showTermDepositNegativeBalanceWarning = computed(
    () => warningPreview.value.showTermDepositNegativeBalanceWarning,
);

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
            amountMinorUnits.value = props.movement.amount_minor_units;
            amountInput.value = minorUnitsToMoneyInput(props.movement.amount_minor_units);
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
        amountMinorUnits.value = 0;
        amountInput.value = '0,00';
        originalAmountMinorUnits.value = 0;
        originalAccountId.value = null;
        originalTermDepositId.value = null;
        originalDirection.value = 'to_term_deposit';
    },
    { immediate: true },
);

function syncAmount(): void {
    const parsed = parseMoneyInputToMinorUnits(amountInput.value);

    if (parsed !== null && parsed > 0) {
        amountMinorUnits.value = parsed;
    } else {
        amountMinorUnits.value = 0;
    }
}

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
                                ? t.operations.create_capital_movement_title
                                : t.operations.edit_capital_movement_title
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            mode === 'create'
                                ? t.operations.create_capital_movement_description
                                : t.operations.edit_capital_movement_description
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="movement-date">{{ t.operations.date }}</Label>
                    <Input
                        id="movement-date"
                        name="movement_date"
                        v-model="movementDate"
                        type="date"
                        required
                    />
                    <InputError :message="errors.movement_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="movement-description">
                        {{ t.operations.description }}
                    </Label>
                    <Input
                        id="movement-description"
                        name="description"
                        v-model="description"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label for="movement-direction">
                        {{ t.operations.capital_movement_direction }}
                    </Label>
                    <Select v-model="selectedDirection">
                        <SelectTrigger id="movement-direction" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="to_term_deposit">
                                {{ t.operations.capital_movement_to_term_deposit }}
                            </SelectItem>
                            <SelectItem value="from_term_deposit">
                                {{ t.operations.capital_movement_from_term_deposit }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <input type="hidden" name="direction" :value="direction" />
                    <InputError :message="errors.direction" />
                </div>

                <div class="grid gap-2">
                    <Label for="movement-account">{{ t.operations.account }}</Label>
                    <AccountSelect
                        id="movement-account"
                        name="account_id"
                        v-model="accountId"
                        :accounts="accounts"
                        required
                    />
                    <InputError :message="errors.account_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="movement-term-deposit">
                        {{ t.operations.term_deposit }}
                    </Label>
                    <Select v-model="selectedTermDepositId">
                        <SelectTrigger id="movement-term-deposit" class="w-full">
                            <SelectValue
                                :placeholder="t.operations.term_deposit"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="termDeposit in termDeposits"
                                :key="termDeposit.id"
                                :value="String(termDeposit.id)"
                            >
                                {{ termDeposit.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <input
                        type="hidden"
                        name="term_deposit_id"
                        :value="termDepositId"
                    />
                    <InputError :message="errors.term_deposit_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="movement-amount">{{ t.operations.amount }}</Label>
                    <Input
                        id="movement-amount"
                        v-model="amountInput"
                        inputmode="decimal"
                        autocomplete="off"
                        @input="syncAmount"
                        @blur="syncAmount"
                    />
                    <input
                        type="hidden"
                        name="amount_minor_units"
                        :value="amountMinorUnits"
                    />
                    <InputError :message="errors.amount_minor_units" />
                </div>

                <Alert v-if="showNegativeBalanceWarning" variant="destructive">
                    <AlertTriangle class="size-4" />
                    <AlertTitle>{{ t.operations.expense_warnings_title }}</AlertTitle>
                    <AlertDescription>
                        {{ t.operations.negative_balance_warning }}
                    </AlertDescription>
                </Alert>

                <Alert
                    v-if="showTermDepositNegativeBalanceWarning"
                    variant="destructive"
                >
                    <AlertTriangle class="size-4" />
                    <AlertTitle>{{ t.operations.expense_warnings_title }}</AlertTitle>
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
                            {{ t.accounts.cancel }}
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        {{ t.accounts.save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
