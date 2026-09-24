<script setup lang="ts">
import { Building2, Coins } from '@lucide/vue';
import { computed, useSlots } from 'vue';
import type { AccountIdentityFields } from '@/lib/account-identity';

const props = withDefaults(
    defineProps<AccountIdentityFields & { size?: 'sm' | 'md' }>(),
    {
        size: 'md',
    },
);

const showBankMark = computed(
    () => props.type === 'bank' && props.mark_url !== null,
);

const showCashIcon = computed(() => props.type === 'cash');

const showOtherBankIcon = computed(
    () => props.type === 'bank' && props.mark_url === null,
);

const markSizeClass = computed(() =>
    props.size === 'sm' ? 'size-6 rounded-md' : 'size-10 rounded-lg',
);

const iconSizeClass = computed(() =>
    props.size === 'sm' ? 'size-3.5' : 'size-5',
);

const slots = useSlots();
const hasDetails = computed(() => !!slots.default);
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <div
            class="border-border/60 bg-muted flex shrink-0 items-center justify-center overflow-hidden border"
            :class="markSizeClass"
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
        </div>

        <div v-if="hasDetails" class="min-w-0">
            <slot />
        </div>
    </div>
</template>
