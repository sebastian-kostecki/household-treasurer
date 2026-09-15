<script setup lang="ts">
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';
import {
    type AccountBank,
    bankMarkPath,
    bankOptions,
} from '@/lib/account-identity';

const props = defineProps<{
    modelValue: AccountBank;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: AccountBank];
}>();

const t = useTranslations();

const selectedBank = computed({
    get: () => props.modelValue,
    set: (value: AccountBank) => emit('update:modelValue', value),
});

function bankLabel(bank: AccountBank): string {
    const labels: Record<AccountBank, string> = {
        alior: t.accounts.bank_alior,
        bnp_paribas: t.accounts.bank_bnp_paribas,
        ing: t.accounts.bank_ing,
        lokaty_facto: t.accounts.bank_lokaty_facto,
        mbank: t.accounts.bank_mbank,
        pekao: t.accounts.bank_pekao,
        pko_bp: t.accounts.bank_pko_bp,
        revolut: t.accounts.bank_revolut,
        santander: t.accounts.bank_santander,
        other: t.accounts.bank_other,
    };

    return labels[bank];
}
</script>

<template>
    <Select v-model="selectedBank">
        <SelectTrigger id="account-bank" class="w-full">
            <SelectValue>
                <span class="flex items-center gap-2">
                    <span
                        class="border-border/60 bg-muted flex size-5 shrink-0 items-center justify-center overflow-hidden rounded-lg border"
                    >
                        <img
                            v-if="bankMarkPath(selectedBank)"
                            :src="bankMarkPath(selectedBank)!"
                            :alt="bankLabel(selectedBank)"
                            class="size-full object-cover"
                        />
                        <span
                            v-else
                            class="text-muted-foreground text-[10px] font-medium"
                        >
                            •
                        </span>
                    </span>
                    <span>{{ bankLabel(selectedBank) }}</span>
                </span>
            </SelectValue>
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-for="bank in bankOptions" :key="bank" :value="bank">
                <span class="flex items-center gap-2">
                    <span
                        class="border-border/60 bg-muted flex size-5 shrink-0 items-center justify-center overflow-hidden rounded-lg border"
                    >
                        <img
                            v-if="bankMarkPath(bank)"
                            :src="bankMarkPath(bank)!"
                            :alt="bankLabel(bank)"
                            class="size-full object-cover"
                        />
                        <span
                            v-else
                            class="text-muted-foreground text-[10px] font-medium"
                        >
                            •
                        </span>
                    </span>
                    <span>{{ bankLabel(bank) }}</span>
                </span>
            </SelectItem>
        </SelectContent>
    </Select>
    <input type="hidden" name="bank" :value="selectedBank" />
</template>
