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
        allOptionLabel?: string;
        archivedSuffix?: string;
    }>(),
    {
        required: false,
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | ''];
}>();

const ALL_ACCOUNTS_VALUE = '__all__';

const selectedAccountId = computed({
    get: () => {
        if (props.modelValue === '') {
            return props.allOptionLabel === undefined
                ? undefined
                : ALL_ACCOUNTS_VALUE;
        }

        return String(props.modelValue);
    },
    set: (value: string | undefined) => {
        emit(
            'update:modelValue',
            value && value !== ALL_ACCOUNTS_VALUE ? Number(value) : '',
        );
    },
});

const selectedAccount = computed(() =>
    props.accounts.find((account) => account.id === props.modelValue),
);

function accountLabel(account: AccountPickerOption): string {
    if (!account.is_archived || props.archivedSuffix === undefined) {
        return account.name;
    }

    return `${account.name} (${props.archivedSuffix})`;
}
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
                        <span>{{ accountLabel(selectedAccount) }}</span>
                    </span>
                    <span v-else-if="allOptionLabel !== undefined">
                        {{ allOptionLabel }}
                    </span>
                </SelectValue>
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-if="allOptionLabel !== undefined"
                    :value="ALL_ACCOUNTS_VALUE"
                >
                    {{ allOptionLabel }}
                </SelectItem>
                <SelectItem
                    v-for="account in accounts"
                    :key="account.id"
                    :value="String(account.id)"
                    :text-value="accountLabel(account)"
                >
                    <span class="flex items-center gap-2">
                        <AccountAvatar
                            :type="account.type"
                            :bank="account.bank"
                            :mark_url="account.mark_url"
                        />
                        <span>{{ accountLabel(account) }}</span>
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
