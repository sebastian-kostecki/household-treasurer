<script setup lang="ts">
import AccountMarkCompact from '@/components/AccountMarkCompact.vue';
import type { AccountIdentityFields } from '@/lib/account-identity';

withDefaults(
    defineProps<
        AccountIdentityFields & {
            markSize?: 'sm' | 'md';
        }
    >(),
    {
        markSize: 'sm',
    },
);
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <AccountMarkCompact :type="type" :bank="bank" :size="markSize" />

        <span class="truncate text-sm">{{ name }}</span>
    </div>
</template>
