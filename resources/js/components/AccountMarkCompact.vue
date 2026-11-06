<script setup lang="ts">
import { Building2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    type AccountBank,
    type AccountTypeValue,
    accountShortMark,
} from '@/lib/account-identity';

const props = withDefaults(
    defineProps<{
        type: AccountTypeValue;
        bank: AccountBank | null;
        size?: 'sm' | 'md';
    }>(),
    {
        size: 'sm',
    },
);

const shortMark = computed(() => accountShortMark(props));

const tileSizeClass = computed(() =>
    props.size === 'sm' ? 'size-5' : 'size-7',
);

const iconSizeClass = computed(() =>
    props.size === 'sm' ? 'size-3' : 'size-3.5',
);
</script>

<template>
    <span
        class="bg-muted flex shrink-0 items-center justify-center overflow-hidden rounded-sm"
        :class="tileSizeClass"
        aria-hidden="true"
    >
        <span v-if="shortMark !== null" class="mark-label text-foreground">
            {{ shortMark }}
        </span>
        <Building2
            v-else
            class="text-muted-foreground"
            :class="iconSizeClass"
        />
    </span>
</template>
