<script setup lang="ts">
import AccountMarkCompact from '@/components/AccountMarkCompact.vue';
import type { AccountIdentityFields } from '@/lib/account-identity';

defineProps<AccountIdentityFields>();
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <AccountMarkCompact
            :name="name"
            :type="type"
            :bank="bank"
            :mark_url="mark_url"
        />

        <span class="truncate text-sm">{{ name }}</span>
    </div>
</template>
