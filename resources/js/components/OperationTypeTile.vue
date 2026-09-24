<script setup lang="ts">
import {
    ArrowDownLeft,
    ArrowLeftRight,
    ArrowUpRight,
    RotateCcw,
} from '@lucide/vue';
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';

const { variant } = defineProps<{
    variant: 'income' | 'expense' | 'refund' | 'transfer';
}>();

const t = useTranslations();

const config = computed(() => {
    switch (variant) {
        case 'income':
            return {
                icon: ArrowDownLeft,
                label: t.operations.type_income,
                className: 'bg-success/10 text-success',
            };
        case 'expense':
            return {
                icon: ArrowUpRight,
                label: t.operations.type_expense,
                className: 'bg-destructive/10 text-destructive',
            };
        case 'refund':
            return {
                icon: RotateCcw,
                label: t.operations.type_refund,
                className: 'bg-refund/10 text-refund',
            };
        case 'transfer':
            return {
                icon: ArrowLeftRight,
                label: t.operations.type_own_transfer,
                className: 'bg-transfer/10 text-transfer',
            };
    }
});
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex min-h-10 w-10 shrink-0 items-center justify-center self-stretch rounded-lg',
                config.className,
            )
        "
        :title="config.label"
        :aria-label="config.label"
    >
        <component :is="config.icon" class="size-5" aria-hidden="true" />
    </span>
</template>
