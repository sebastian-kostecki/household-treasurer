<script setup lang="ts">
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Spinner } from '@/components/ui/spinner';
import type { CategoryMergePreviewState } from '@/composables/useCategoryMergePreview';
import { useTranslations } from '@/composables/useTranslations';
import { monthPlanItemsLine } from '@/lib/category-merge';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    state: CategoryMergePreviewState;
}>();

const t = useTranslations();

const preview = computed(() =>
    props.state.status === 'loaded' ? props.state.preview : null,
);

const title = computed(() =>
    preview.value === null
        ? ''
        : t.categories.merge_preview_title.replace(
              ':name',
              preview.value.target.name,
          ),
);

const planItemsLine = computed(() =>
    preview.value === null
        ? null
        : monthPlanItemsLine(preview.value.month_plan_items, {
              merge_plan_items_one: t.categories.merge_plan_items_one,
              merge_plan_items_few: t.categories.merge_plan_items_few,
              merge_plan_items_many: t.categories.merge_plan_items_many,
          }),
);

/** „Zebrane: cel + źródło → suma, od <miesiąc>”, null when the merge keeps no Collected. */
const collectedLine = computed(() => {
    const collected = preview.value?.collected ?? null;

    if (
        collected === null ||
        collected.target_minor_units === null ||
        collected.after_minor_units === null
    ) {
        return null;
    }

    return t.categories.merge_collected
        .replace(':target', formatMoney(collected.target_minor_units))
        .replace(':source', formatMoney(collected.source_minor_units))
        .replace(':after', formatMoney(collected.after_minor_units))
        .replace(
            ':month',
            t.categories[`month_genitive_${collected.since.month}`],
        )
        .replace(':year', String(collected.since.year));
});

const collectedLostLine = computed(() => {
    const data = preview.value;

    if (
        data === null ||
        data.collected === null ||
        data.collected.after_minor_units !== null
    ) {
        return null;
    }

    return t.categories.merge_collected_lost
        .replace(':amount', formatMoney(data.collected.source_minor_units))
        .replace(':name', data.target.name);
});

const restoredNote = computed(() =>
    preview.value?.restored_budget_year == null
        ? null
        : t.categories.merge_restored_note.replace(
              ':year',
              String(preview.value.restored_budget_year),
          ),
);
</script>

<template>
    <div class="grid gap-3" aria-live="polite">
        <p
            v-if="state.status === 'loading'"
            class="text-muted-foreground flex items-center gap-2 text-sm"
        >
            <Spinner />
            {{ t.categories.merge_loading }}
        </p>

        <InputError
            v-else-if="state.status === 'error'"
            :message="state.message"
        />

        <template v-else-if="preview !== null">
            <h3 class="section-title">{{ title }}</h3>

            <div class="category-merge-grid" role="table">
                <div role="row" class="contents">
                    <div
                        role="columnheader"
                        class="table-header border-border-soft border-b pb-1"
                    >
                        {{ t.categories.merge_column_budget }}
                    </div>
                    <div
                        role="columnheader"
                        class="table-header border-border-soft border-b pb-1 text-right"
                    >
                        {{ t.categories.merge_column_operations }}
                    </div>
                    <div
                        role="columnheader"
                        class="table-header border-border-soft border-b pb-1 text-right"
                    >
                        {{ t.categories.merge_column_plan }}
                    </div>
                </div>
                <div
                    v-for="budget in preview.budgets"
                    :key="budget.year"
                    role="row"
                    class="contents"
                >
                    <div role="cell">
                        {{ budget.year }}
                        <span
                            v-if="budget.phase === 'draft'"
                            class="text-muted-foreground"
                        >
                            ({{ t.categories.merge_draft }})
                        </span>
                    </div>
                    <div role="cell" class="text-right tabular-nums">
                        {{ budget.operations_count }}
                    </div>
                    <div role="cell" class="text-right tabular-nums">
                        {{ formatMoney(budget.target_plan_before_minor_units) }}
                        →
                        {{ formatMoney(budget.target_plan_after_minor_units) }}
                    </div>
                </div>
                <div role="row" class="contents font-medium">
                    <div role="cell" class="border-border-soft border-t pt-1">
                        {{ t.categories.merge_total }}
                    </div>
                    <div
                        role="cell"
                        class="border-border-soft border-t pt-1 text-right tabular-nums"
                    >
                        {{ preview.operations_count }}
                    </div>
                    <div
                        role="cell"
                        class="border-border-soft border-t pt-1"
                    ></div>
                </div>
            </div>

            <p v-if="planItemsLine !== null" class="text-sm">
                {{ planItemsLine }}
            </p>
            <p v-if="collectedLine !== null" class="text-sm tabular-nums">
                {{ collectedLine }}
            </p>

            <Alert v-if="collectedLostLine !== null" variant="warning">
                <AlertDescription>{{ collectedLostLine }}</AlertDescription>
            </Alert>
            <Alert v-if="restoredNote !== null" variant="note">
                <AlertDescription>{{ restoredNote }}</AlertDescription>
            </Alert>
            <Alert variant="danger">
                <AlertDescription>
                    {{ t.categories.merge_irreversible }}
                </AlertDescription>
            </Alert>
        </template>
    </div>
</template>
