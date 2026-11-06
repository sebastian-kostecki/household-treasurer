<script setup lang="ts">
import { computed } from 'vue';
import AccountAvatar from '@/components/AccountAvatar.vue';
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
    bankLabel,
    bankOptions,
} from '@/lib/account-identity';

const props = withDefaults(
    defineProps<{
        modelValue: AccountBank;
        invalid?: boolean;
        /** Trigger id; FormField passes its field id so the label points at it. */
        id?: string;
        /** Hint and error ids from FormField. */
        ariaDescribedby?: string;
    }>(),
    { id: 'account-bank', ariaDescribedby: undefined },
);

const emit = defineEmits<{
    'update:modelValue': [value: AccountBank];
}>();

const t = useTranslations();

const selectedBank = computed({
    get: () => props.modelValue,
    set: (value: AccountBank) => emit('update:modelValue', value),
});

function bankName(bank: AccountBank): string {
    return bankLabel(t.accounts, bank);
}
</script>

<template>
    <Select v-model="selectedBank">
        <SelectTrigger
            :id="id"
            class="w-full"
            :aria-invalid="invalid ? 'true' : undefined"
            :aria-describedby="ariaDescribedby"
        >
            <SelectValue>
                <span class="flex items-center gap-2">
                    <AccountAvatar
                        type="bank"
                        :bank="selectedBank"
                        :mark_url="null"
                    />
                    <span>{{ bankName(selectedBank) }}</span>
                </span>
            </SelectValue>
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                v-for="bank in bankOptions"
                :key="bank"
                :value="bank"
                :text-value="bankName(bank)"
            >
                <span class="flex items-center gap-2">
                    <AccountAvatar type="bank" :bank="bank" :mark_url="null" />
                    <span>{{ bankName(bank) }}</span>
                </span>
            </SelectItem>
        </SelectContent>
    </Select>
    <input type="hidden" name="bank" :value="selectedBank" />
</template>
