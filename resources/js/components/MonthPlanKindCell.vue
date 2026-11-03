<script setup lang="ts">
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import type { MonthItemKind } from '@/types/month-plan';

defineProps<{
    kind: MonthItemKind;
    canChange: boolean;
    pending: boolean;
    error?: string;
}>();

const emit = defineEmits<{
    change: [kind: MonthItemKind];
}>();

const t = useTranslations();

const options = computed<{ value: MonthItemKind; label: string }[]>(() => [
    { value: 'bill', label: t.month_plan.kind_bill },
    { value: 'running', label: t.month_plan.kind_running },
]);

function label(kind: MonthItemKind): string {
    return kind === 'bill' ? t.month_plan.kind_bill : t.month_plan.kind_running;
}
</script>

<template>
    <div v-if="canChange" class="grid gap-1">
        <div class="flex items-center gap-2">
            <SegmentedControl
                :model-value="kind"
                :options="options"
                :ariaLabel="t.month_plan.kind"
                tone="neutral"
                :disabled="pending"
                @update:model-value="emit('change', $event)"
            />
            <Spinner v-if="pending" />
        </div>
        <InputError :message="error" />
    </div>
    <span v-else class="text-muted-foreground text-sm">
        {{ label(kind) }}
    </span>
</template>
