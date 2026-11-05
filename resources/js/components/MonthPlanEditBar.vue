<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';

withDefaults(
    defineProps<{
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
        class="month-plan-editbar"
    >
        <span class="text-sm font-semibold">
            {{ t.month_plan.edit_bar_label }}
        </span>
        <span class="meta">{{ t.month_plan.edit_bar_effect }}</span>
        <span v-if="invalidCount > 0" class="text-sm font-medium">
            {{
                t.month_plan.invalid_amounts.replace(
                    ':count',
                    String(invalidCount),
                )
            }}
        </span>
        <p v-if="error" role="alert" class="text-sm font-medium">
            {{ error }}
        </p>
        <div class="ml-auto flex items-center gap-2">
            <Button type="button" variant="ghost" @click="emit('cancel')">
                {{ t.month_plan.cancel }}
            </Button>
            <Button
                type="submit"
                variant="brand"
                :disabled="processing || !canSubmit"
            >
                <Spinner v-if="processing" />
                {{ t.month_plan.save_changes }}
            </Button>
        </div>
    </div>
</template>
