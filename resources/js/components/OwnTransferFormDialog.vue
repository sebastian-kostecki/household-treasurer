<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import OwnTransferController from '@/actions/App/Http/Controllers/OwnTransferController';
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
import { useTranslations } from '@/composables/useTranslations';
import {
    groszeToMoneyInput,
    parseMoneyInputToGrosze,
} from '@/lib/format';
import { previewHandEntryWarnings } from '@/lib/hand-entry-warning-preview';
import type { AccountPickerOption } from '@/lib/account-identity';

export type OwnTransferFormTransfer = {
    id: number;
    transfer_date: string;
    amount_grosze: number;
    description: string;
    raw_statement_description?: string | null;
    source_account_id: number;
    destination_account_id: number;
};

export type OwnTransferFormAccount = AccountPickerOption & {
    current_balance_grosze: number;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    transfer?: OwnTransferFormTransfer | null;
    accounts: OwnTransferFormAccount[];
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
const amountGrosze = ref(0);
const originalAmountGrosze = ref(0);
const originalSourceAccountId = ref<number | null>(null);

const formBinding = computed(() =>
    props.mode === 'create'
        ? OwnTransferController.store.form()
        : OwnTransferController.update.form(props.transfer ?? { id: 0 }),
);

const bankAccounts = computed(() =>
    props.accounts.filter((account) => account.type === 'bank'),
);

const cashAccounts = computed(() =>
    props.accounts.filter((account) => account.type === 'cash'),
);

const warningPreview = computed(() => {
    if (sourceAccountId.value === '') {
        return previewHandEntryWarnings({
            mode: props.mode,
            accounts: props.accounts,
            proposal: {
                kind: 'own_transfer',
                account_id: 0,
                amount_grosze: 0,
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
                      signed_amount_grosze: originalAmountGrosze.value,
                      source_account_id: originalSourceAccountId.value,
                  }
                : null,
        proposal: {
            kind: 'own_transfer',
            account_id: sourceAccountId.value,
            amount_grosze: amountGrosze.value,
            source_account_id: sourceAccountId.value,
        },
    });
});

const showNegativeBalanceWarning = computed(
    () => warningPreview.value.showNegativeBalanceWarning,
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
            amountGrosze.value = props.transfer.amount_grosze;
            amountInput.value = groszeToMoneyInput(props.transfer.amount_grosze);
            originalAmountGrosze.value = props.transfer.amount_grosze;
            originalSourceAccountId.value = props.transfer.source_account_id;

            return;
        }

        transferDate.value = new Date().toISOString().slice(0, 10);
        description.value = '';
        sourceAccountId.value = bankAccounts.value[0]?.id ?? '';
        destinationAccountId.value = cashAccounts.value[0]?.id ?? '';
        amountGrosze.value = 0;
        amountInput.value = '0,00';
        originalAmountGrosze.value = 0;
        originalSourceAccountId.value = null;
    },
    { immediate: true },
);

function syncAmount(): void {
    const parsed = parseMoneyInputToGrosze(amountInput.value);

    if (parsed !== null && parsed > 0) {
        amountGrosze.value = parsed;
    } else {
        amountGrosze.value = 0;
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

                <div class="grid gap-2">
                    <Label for="transfer-date">{{ t.operations.date }}</Label>
                    <Input
                        id="transfer-date"
                        name="transfer_date"
                        v-model="transferDate"
                        type="date"
                        required
                    />
                    <InputError :message="errors.transfer_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="transfer-description">
                        {{ t.operations.description }}
                    </Label>
                    <Input
                        id="transfer-description"
                        name="description"
                        v-model="description"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div
                    v-if="mode === 'edit' && transfer?.raw_statement_description"
                    class="grid gap-2"
                >
                    <Label for="transfer-raw-statement-description">
                        {{ t.operations.raw_statement_description }}
                    </Label>
                    <textarea
                        id="transfer-raw-statement-description"
                        :value="transfer.raw_statement_description"
                        readonly
                        rows="3"
                        class="border-input w-full rounded-xl border bg-transparent px-3 py-2 text-base md:text-sm"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="source-account">
                        {{ t.operations.source_account }}
                    </Label>
                    <AccountSelect
                        id="source-account"
                        name="source_account_id"
                        v-model="sourceAccountId"
                        :accounts="accounts"
                        required
                    />
                    <InputError :message="errors.source_account_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="destination-account">
                        {{ t.operations.destination_account }}
                    </Label>
                    <AccountSelect
                        id="destination-account"
                        name="destination_account_id"
                        v-model="destinationAccountId"
                        :accounts="accounts"
                        required
                    />
                    <InputError :message="errors.destination_account_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="transfer-amount">{{ t.operations.amount }}</Label>
                    <Input
                        id="transfer-amount"
                        v-model="amountInput"
                        inputmode="decimal"
                        autocomplete="off"
                        @input="syncAmount"
                        @blur="syncAmount"
                    />
                    <input
                        type="hidden"
                        name="amount_grosze"
                        :value="amountGrosze"
                    />
                    <InputError :message="errors.amount_grosze" />
                </div>

                <Alert v-if="showNegativeBalanceWarning" variant="destructive">
                    <AlertTriangle class="size-4" />
                    <AlertTitle>{{ t.operations.expense_warnings_title }}</AlertTitle>
                    <AlertDescription>
                        {{ t.operations.negative_balance_warning }}
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
