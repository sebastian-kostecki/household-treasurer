<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { amountToneClass } from '@/lib/amount-tone';
import { formatMoney } from '@/lib/format';
import type { FormPreviewRow } from '@/lib/operation-dialog-preview';

const props = defineProps<{
    rows: FormPreviewRow[];
    warnings?: string[];
}>();

const t = useTranslations();

const visible = computed(
    () => props.rows.length > 0 || (props.warnings ?? []).length > 0,
);
</script>

<template>
    <div
        v-if="visible"
        class="form-preview"
        role="group"
        :aria-label="t.operations.preview_label"
    >
        <div v-for="row in rows" :key="row.key" class="flex flex-col gap-0.5">
            <div class="flex items-baseline justify-between gap-3">
                <span class="form-preview-label">{{ row.label }}</span>
                <span class="form-preview-value">
                    <span :class="amountToneClass(row.beforeMinorUnits)">{{
                        formatMoney(row.beforeMinorUnits)
                    }}</span>
                    <span aria-hidden="true"> → </span>
                    <span class="sr-only">{{
                        t.operations.preview_after
                    }}</span>
                    <span :class="amountToneClass(row.afterMinorUnits)">{{
                        formatMoney(row.afterMinorUnits)
                    }}</span>
                </span>
            </div>
            <p v-if="row.hint" class="form-preview-hint">{{ row.hint }}</p>
            <p
                v-for="warning in row.warnings"
                :key="warning"
                class="form-preview-warning"
            >
                {{ warning }}
            </p>
        </div>
        <p
            v-for="warning in warnings ?? []"
            :key="warning"
            class="form-preview-warning"
        >
            {{ warning }}
        </p>
    </div>
</template>
