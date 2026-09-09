<script setup lang="ts">
import { Building2, Coins } from '@lucide/vue';
import { computed } from 'vue';
import type { AccountIdentityFields } from '@/lib/account-identity';

const props = withDefaults(
    defineProps<
        AccountIdentityFields & {
            size?: 'sm' | 'md';
        }
    >(),
    {
        size: 'sm',
    },
);

const tileSizeClass = computed(() =>
    props.size === 'sm' ? 'size-5' : 'size-7',
);

const tileRadiusClass = computed(() =>
    props.size === 'sm' ? 'rounded-md' : 'rounded-lg',
);

const iconSizeClass = computed(() =>
    props.size === 'sm' ? 'size-3' : 'size-3.5',
);

const showBankMark = computed(
    () => props.type === 'bank' && props.mark_url !== null,
);

const showCashIcon = computed(() => props.type === 'cash');

const showOtherBankIcon = computed(
    () => props.type === 'bank' && props.mark_url === null,
);
</script>

<template>
    <span
        class="border-border/60 bg-muted flex shrink-0 items-center justify-center overflow-hidden border"
        :class="[tileSizeClass, tileRadiusClass]"
    >
        <img
            v-if="showBankMark"
            :src="mark_url!"
            :alt="name"
            class="size-full object-cover"
        />
        <Coins
            v-else-if="showCashIcon"
            class="text-muted-foreground"
            :class="iconSizeClass"
            aria-hidden="true"
        />
        <Building2
            v-else-if="showOtherBankIcon"
            class="text-muted-foreground"
            :class="iconSizeClass"
            aria-hidden="true"
        />
    </span>
</template>
