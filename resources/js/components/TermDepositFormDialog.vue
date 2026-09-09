<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import TermDepositController from '@/actions/App/Http/Controllers/TermDepositController';
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
import { useTranslations } from '@/composables/useTranslations';
import {
    minorUnitsToMoneyInput,
    parseMoneyInputToMinorUnits,
} from '@/lib/format';

export type TermDepositFormTermDeposit = {
    id: number;
    name: string;
    opening_date: string;
    opening_balance_minor_units: number;
    current_balance_minor_units: number;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    termDeposit?: TermDepositFormTermDeposit | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const name = ref('');
const openingDate = ref('');
const openingBalanceInput = ref('0,00');
const openingBalanceMinorUnits = ref(0);

const formBinding = computed(() =>
    props.mode === 'create'
        ? TermDepositController.store.form()
        : TermDepositController.update.form(props.termDeposit ?? { id: 0 }),
);

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
            openingDate.value = props.termDeposit.opening_date;
            openingBalanceMinorUnits.value =
                props.termDeposit.opening_balance_minor_units;
            openingBalanceInput.value = minorUnitsToMoneyInput(
                props.termDeposit.opening_balance_minor_units,
            );

            return;
        }

        name.value = '';
        openingDate.value = new Date().toISOString().slice(0, 10);
        openingBalanceMinorUnits.value = 0;
        openingBalanceInput.value = '0,00';
    },
    { immediate: true },
);

function syncOpeningBalance(): void {
    const parsed = parseMoneyInputToMinorUnits(openingBalanceInput.value);

    if (parsed !== null) {
        openingBalanceMinorUnits.value = parsed;
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
                    />
                    <InputError :message="errors.name" />
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
                    />
                    <InputError :message="errors.opening_date" />
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
                        @blur="syncOpeningBalance"
                    />
                    <input
                        type="hidden"
                        name="opening_balance_minor_units"
                        :value="openingBalanceMinorUnits"
                    />
                    <InputError :message="errors.opening_balance_minor_units" />
                </div>

                <Alert v-if="showNegativeBalanceWarning" variant="destructive">
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
                    <Button type="submit" :disabled="processing">
                        {{ t.term_deposits.save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
