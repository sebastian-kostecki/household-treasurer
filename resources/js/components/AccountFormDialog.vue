<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/AccountController';
import AccountBankSelect from '@/components/AccountBankSelect.vue';
import InputError from '@/components/InputError.vue';
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
    type AccountBank,
    type AccountTypeValue,
} from '@/lib/account-identity';
import {
    minorUnitsToMoneyInput,
    parseMoneyInputToMinorUnits,
} from '@/lib/format';
import type { AccountFormAccount } from '@/types/accounts';

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    account?: AccountFormAccount | null;
    showArchived?: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const t = useTranslations();

const name = ref('');
const type = ref<AccountTypeValue>('bank');
const bank = ref<AccountBank>('other');
const openingDate = ref('');
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
        ? AccountController.store.form(options)
        : AccountController.update.form(props.account ?? { id: 0 }, options);
});

watch(
    () => [props.open, props.mode, props.account] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        if (props.mode === 'edit' && props.account) {
            name.value = props.account.name;
            type.value = props.account.type;
            bank.value = props.account.bank ?? 'other';
            openingDate.value = props.account.opening_date;
            openingBalanceMinorUnits.value =
                props.account.opening_balance_minor_units;
            openingBalanceInput.value = minorUnitsToMoneyInput(
                props.account.opening_balance_minor_units,
            );

            return;
        }

        name.value = '';
        type.value = 'bank';
        bank.value = 'other';
        openingDate.value = new Date().toISOString().slice(0, 10);
        openingBalanceMinorUnits.value = 0;
        openingBalanceInput.value = '0,00';
    },
    { immediate: true },
);

watch(type, (nextType, previousType) => {
    if (nextType === 'bank' && previousType === 'cash') {
        bank.value = 'other';
    }
});

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
                                ? t.accounts.create_title
                                : t.accounts.edit_title
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            mode === 'create'
                                ? t.accounts.create_description
                                : t.accounts.edit_description
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="account-name">{{ t.accounts.name }}</Label>
                    <Input
                        id="account-name"
                        name="name"
                        v-model="name"
                        required
                        autocomplete="off"
                        :aria-invalid="errors.name ? 'true' : undefined"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="account-type">{{ t.accounts.type }}</Label>
                    <select
                        id="account-type"
                        name="type"
                        v-model="type"
                        class="native-select"
                        :aria-invalid="errors.type ? 'true' : undefined"
                    >
                        <option value="bank">
                            {{ t.accounts.type_bank }}
                        </option>
                        <option value="cash">
                            {{ t.accounts.type_cash }}
                        </option>
                    </select>
                    <InputError :message="errors.type" />
                </div>

                <div v-if="type === 'bank'" class="grid gap-2">
                    <Label for="account-bank">{{ t.accounts.bank }}</Label>
                    <AccountBankSelect
                        v-model="bank"
                        :invalid="Boolean(errors.bank)"
                    />
                    <InputError :message="errors.bank" />
                </div>

                <div v-if="mode === 'create'" class="grid gap-2">
                    <Label for="account-opening-date">
                        {{ t.accounts.opening_date }}
                    </Label>
                    <Input
                        id="account-opening-date"
                        name="opening_date"
                        v-model="openingDate"
                        type="date"
                        required
                        :aria-invalid="errors.opening_date ? 'true' : undefined"
                    />
                    <InputError :message="errors.opening_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="account-opening-balance">
                        {{ t.accounts.opening_balance }}
                    </Label>
                    <Input
                        id="account-opening-balance"
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
                                ? t.accounts.opening_balance_invalid
                                : errors.opening_balance_minor_units
                        "
                    />
                </div>

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
                    <Button
                        type="submit"
                        :disabled="processing || openingBalanceInvalid"
                    >
                        {{ t.accounts.save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
