<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/AccountController';
import AccountBankSelect from '@/components/AccountBankSelect.vue';
import FormField from '@/components/FormField.vue';
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
import {
    type AccountBank,
    type AccountTypeValue,
} from '@/lib/account-identity';
import {
    minorUnitsToMoneyInput,
    parseMoneyInputToMinorUnits,
} from '@/lib/format';
import type { AccountFormAccount } from '@/types/accounts';

type FormBinding = InstanceType<typeof Form>['$props'];

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    account?: AccountFormAccount | null;
    showArchived?: boolean;
    /** Account type preselected when creating; used only by the dialogs kitchen sink. */
    defaultType?: AccountTypeValue;
    /** Overrides the Wayfinder binding; used only by the dialogs kitchen sink. */
    form?: Pick<FormBinding, 'action' | 'method'>;
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
const busy = ref(false);

const typeOptions = computed(() => [
    { value: 'bank' as const, label: t.accounts.type_bank },
    { value: 'cash' as const, label: t.accounts.type_cash },
]);

const openingBalanceInvalid = computed(
    () => parseMoneyInputToMinorUnits(openingBalanceInput.value) === null,
);

const formBinding = computed(() => {
    if (props.form) {
        return props.form;
    }

    const options = props.showArchived
        ? { query: { show_archived: 1 } }
        : undefined;

    return props.mode === 'create'
        ? AccountController.store.form(options)
        : AccountController.update.form(props.account ?? { id: 0 }, options);
});

watch(
    () => [props.open, props.mode, props.account, props.defaultType] as const,
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
        type.value = props.defaultType ?? 'bank';
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

                <FormField
                    id="account-name"
                    :label="t.accounts.name"
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
                    id="account-type"
                    :label="t.accounts.type"
                    group
                    :error="errors.type"
                    v-slot="{ labelId, describedBy, invalid }"
                >
                    <SegmentedControl
                        v-model="type"
                        :options="typeOptions"
                        :ariaLabelledby="labelId"
                        :ariaDescribedby="describedBy"
                        :ariaInvalid="invalid"
                        name="type"
                        block
                    />
                </FormField>

                <FormField
                    v-if="type === 'bank'"
                    id="account-bank"
                    :label="t.accounts.bank"
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
                    id="account-opening-date"
                    :label="t.accounts.opening_date"
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
                    id="account-opening-balance"
                    :label="t.accounts.opening_balance"
                    money
                    :error="
                        openingBalanceInvalid
                            ? t.accounts.opening_balance_invalid
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
                        :value="openingBalanceMinorUnits"
                    />
                </FormField>

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
                            {{ t.accounts.cancel }}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing || openingBalanceInvalid"
                    >
                        <Spinner v-if="processing" />
                        {{ t.accounts.save }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
