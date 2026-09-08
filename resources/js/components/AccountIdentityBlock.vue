<script setup lang="ts">
import { Building2, Coins } from '@lucide/vue';
import { computed } from 'vue';
import type { AccountIdentityFields } from '@/lib/account-identity';

const props = defineProps<AccountIdentityFields>();

const showBankMark = computed(
    () => props.type === 'bank' && props.mark_url !== null,
);

const showCashIcon = computed(() => props.type === 'cash');

const showOtherBankIcon = computed(
    () => props.type === 'bank' && props.mark_url === null,
);
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <div
            class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border/60 bg-muted"
        >
            <img
                v-if="showBankMark"
                :src="mark_url!"
                :alt="name"
                class="size-full object-cover"
            />
            <Coins
                v-else-if="showCashIcon"
                class="text-muted-foreground size-5"
                aria-hidden="true"
            />
            <Building2
                v-else-if="showOtherBankIcon"
                class="text-muted-foreground size-5"
                aria-hidden="true"
            />
        </div>

        <div class="min-w-0">
            <slot />
        </div>
    </div>
</template>
