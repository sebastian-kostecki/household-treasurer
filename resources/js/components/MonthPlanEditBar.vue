<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';

withDefaults(
    defineProps<{
        toSpendMinorUnits: number | null;
        mode: 'approve' | 'change';
        processing: boolean;
        canSubmit: boolean;
        error?: string;
        invalidCount?: number;
    }>(),
    { invalidCount: 0 },
);

const emit = defineEmits<{
    cancel: [];
}>();

const t = useTranslations();
</script>

<template>
    <div
        role="region"
        :aria-label="t.month_plan.edit_bar_label"
        class="bg-card border-border/60 sticky bottom-0 z-10 grid gap-2 border-t px-4 py-3 md:rounded-2xl md:border"
    >
        <InputError :message="error" />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="flex items-baseline gap-2">
                <span class="section-label">{{ t.month_plan.to_spend }}</span>
                <span
                    class="text-lg font-semibold tabular-nums"
                    :class="
                        (toSpendMinorUnits ?? 0) < 0 ? 'text-destructive' : ''
                    "
                >
                    {{ formatMoney(toSpendMinorUnits ?? 0) }}
                </span>
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <p v-if="invalidCount > 0" class="text-destructive text-sm">
                    {{
                        t.month_plan.invalid_amounts.replace(
                            ':count',
                            String(invalidCount),
                        )
                    }}
                </p>
                <template v-if="mode === 'change'">
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('cancel')"
                    >
                        {{ t.month_plan.cancel }}
                    </Button>
                    <Button type="submit" :disabled="processing || !canSubmit">
                        <Spinner v-if="processing" />
                        {{ t.month_plan.save_changes }}
                    </Button>
                </template>
                <Button
                    v-else
                    type="submit"
                    :disabled="processing || !canSubmit"
                >
                    <Spinner v-if="processing" />
                    {{ t.month_plan.approve }}
                </Button>
            </div>
        </div>
    </div>
</template>
