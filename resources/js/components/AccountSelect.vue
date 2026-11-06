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
        ariaLabel?: string;
        ariaDescribedby?: string;
        ariaInvalid?: 'true';
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
            <SelectTrigger
                :id="id"
                :aria-label="ariaLabel"
                :aria-describedby="ariaDescribedby"
                :aria-invalid="ariaInvalid"
                :class="['w-full', props.class]"
            >
                <SelectValue :placeholder="placeholder">
                    <span
                        v-if="selectedAccount"
                        class="flex items-center gap-2"
                    >
                        <AccountAvatar
                            :type="selectedAccount.type"
                            :bank="selectedAccount.bank"
                            :mark_url="selectedAccount.mark_url"
                        />
                        <span>{{ selectedAccount.name }}</span>
                    </span>
                </SelectValue>
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="account in accounts"
                    :key="account.id"
                    :value="String(account.id)"
                    :text-value="account.name"
                >
                    <span class="flex items-center gap-2">
                        <AccountAvatar
                            :type="account.type"
                            :bank="account.bank"
                            :mark_url="account.mark_url"
                        />
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
