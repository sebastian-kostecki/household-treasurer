<script setup lang="ts">
import { Building2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    type AccountBank,
    type AccountTypeValue,
    accountAvatarContent,
} from '@/lib/account-identity';

const props = withDefaults(
    defineProps<{
        type: AccountTypeValue;
        bank: AccountBank | null;
        mark_url: string | null;
        size?: 'xs' | 'sm' | 'md';
    }>(),
    {
        size: 'sm',
    },
);

const imageFailed = ref(false);

watch(
    () => props.mark_url,
    () => {
        imageFailed.value = false;
    },
);

const content = computed(() =>
    accountAvatarContent(
        { type: props.type, bank: props.bank, mark_url: props.mark_url },
        imageFailed.value,
    ),
);

const tileSizeClass = computed(
    () => ({ xs: 'size-4', sm: 'size-5', md: 'size-7' })[props.size],
);

const iconSizeClass = computed(
    () => ({ xs: 'size-2.5', sm: 'size-3', md: 'size-3.5' })[props.size],
);
</script>

<template>
    <span
        class="bg-muted flex shrink-0 items-center justify-center overflow-hidden rounded-sm"
        :class="tileSizeClass"
        aria-hidden="true"
    >
        <img
            v-if="content.kind === 'logo'"
            :src="content.src"
            alt=""
            class="size-full object-contain"
            @error="imageFailed = true"
        />
        <span
            v-else-if="content.kind === 'mark'"
            class="mark-label text-foreground"
            :class="{ 'tracking-tighter': size === 'xs' }"
        >
            {{ content.text }}
        </span>
        <Building2
            v-else
            class="text-muted-foreground"
            :class="iconSizeClass"
        />
    </span>
</template>
