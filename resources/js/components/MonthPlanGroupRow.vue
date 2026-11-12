<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoneyAmount } from '@/lib/format';

const props = defineProps<{
    name: string;
    /** How many shown Categories stand in the group. */
    count: number;
    /** Sum of the shown plan; null while a row's plan is unreadable. */
    planMinorUnits: number | null;
    actualMinorUnits: number;
    overPlan: boolean;
    open: boolean;
    /** The group cannot collapse: a plan field in it is unreadable and blocks approval. */
    locked?: boolean;
    /** Id of the element the toggle controls (the group's body). */
    controls: string;
}>();

const emit = defineEmits<{
    toggle: [];
}>();

const t = useTranslations();

const toggleLabel = computed(() =>
    (props.open
        ? t.month_plan.group_collapse
        : t.month_plan.group_expand
    ).replace(':name', props.name),
);
</script>

<template>
    <div
        class="month-plan-grid month-plan-grid-no-kind month-plan-category-group-row"
        data-group-row
    >
        <div class="month-plan-cell-category budget-category-group-name">
            <button
                type="button"
                class="budget-category-group-toggle"
                :aria-expanded="open"
                :aria-controls="controls"
                :disabled="locked"
                :aria-label="toggleLabel"
                @click="emit('toggle')"
            >
                <ChevronDown
                    class="size-4 shrink-0 transition-transform"
                    :class="open ? '' : '-rotate-90'"
                    aria-hidden="true"
                />
                <span class="truncate">{{ name }}</span>
            </button>
            <Badge
                variant="neutral"
                :aria-label="t.month_plan.group_category_count"
                :title="t.month_plan.group_category_count"
            >
                {{ count }}
            </Badge>
        </div>

        <div class="month-plan-cell-plan">
            <span class="meta text-muted-foreground block xl:sr-only">
                {{ t.month_plan.plan }}
            </span>
            <span class="amount-sum" data-group-plan>
                {{
                    planMinorUnits === null
                        ? '—'
                        : formatMoneyAmount(planMinorUnits)
                }}
            </span>
        </div>

        <div class="month-plan-cell-actual">
            <span class="meta text-muted-foreground xl:sr-only">
                {{ t.month_plan.actual }}
            </span>
            <span class="amount-sum w-full xl:w-auto" data-group-actual>
                {{ formatMoneyAmount(actualMinorUnits) }}
            </span>
        </div>

        <div class="month-plan-cell-status">
            <Badge
                v-if="overPlan"
                variant="destructive-soft"
                :title="t.month_plan.group_over_plan_hint"
                data-group-over-plan
            >
                {{ t.month_plan.status_over_plan }}
            </Badge>
        </div>
    </div>
</template>
