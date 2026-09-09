<script setup lang="ts">
import { computed } from 'vue';
import AccountMarkCompact from '@/components/AccountMarkCompact.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AccountPickerOption } from '@/lib/account-identity';

const props = withDefaults(
    defineProps<{
        modelValue: number | '';
        accounts: AccountPickerOption[];
        id?: string;
        name?: string;
        placeholder?: string;
        required?: boolean;
        disabled?: boolean;
        class?: string;
    }>(),
    {
        required: false,
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | ''];
}>();

const selectedAccountId = computed({
    get: () => (props.modelValue === '' ? undefined : String(props.modelValue)),
    set: (value: string | undefined) => {
        emit('update:modelValue', value ? Number(value) : '');
    },
});

const selectedAccount = computed(() =>
    props.accounts.find((account) => account.id === props.modelValue),
);
</script>

<template>
    <div>
        <Select v-model="selectedAccountId" :disabled="disabled">
            <SelectTrigger :id="id" :class="['w-full', props.class]">
                <SelectValue :placeholder="placeholder">
                    <span
                        v-if="selectedAccount"
                        class="flex items-center gap-2"
                    >
                        <AccountMarkCompact v-bind="selectedAccount" />
                        <span>{{ selectedAccount.name }}</span>
                    </span>
                </SelectValue>
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="account in accounts"
                    :key="account.id"
                    :value="String(account.id)"
                >
                    <span class="flex items-center gap-2">
                        <AccountMarkCompact v-bind="account" />
                        <span>{{ account.name }}</span>
                    </span>
                </SelectItem>
            </SelectContent>
        </Select>
        <input
            v-if="name"
            type="hidden"
            :name="name"
            :value="modelValue"
            :required="required"
        />
    </div>
</template>
