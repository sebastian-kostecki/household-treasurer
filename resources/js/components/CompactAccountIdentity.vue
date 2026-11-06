<script setup lang="ts">
import AccountAvatar from '@/components/AccountAvatar.vue';
import type { AccountIdentityFields } from '@/lib/account-identity';

withDefaults(
    defineProps<
        AccountIdentityFields & {
            markSize?: 'xs' | 'sm' | 'md';
        }
    >(),
    {
        markSize: 'sm',
    },
);
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <AccountAvatar
            :type="type"
            :bank="bank"
            :mark_url="mark_url"
            :size="markSize"
        />

        <span class="truncate text-sm">{{ name }}</span>
    </div>
</template>
