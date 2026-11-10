<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import InputError from '@/components/InputError.vue';
import MonthPlanMatchActual from '@/components/MonthPlanMatchActual.vue';
import MonthYearSummary from '@/components/MonthYearSummary.vue';
import PlanChangeArrow from '@/components/PlanChangeArrow.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoneyAmount, formatSignedMoneyAmount } from '@/lib/format';
import type { MatchActualState } from '@/lib/month-plan-match-actual';
import type { MonthItemStatus } from '@/lib/month-plan-status';
import type { MonthPlanItem } from '@/types/month-plan';

const props = withDefaults(
    defineProps<{
        row: MonthPlanItem;
        editable: boolean;
        /** The plan the row shows now (the typed value in change mode); null while the entry is unreadable. */
        planMinorUnits: number | null;
        deviation: number | null;
        overPlan: boolean;
        status: MonthItemStatus | null;
        yearStale?: boolean;
        invalid?: boolean;
        /** Button or marker "plan = actual" in change mode (ADR-0062). */
        matchActual?: MatchActualState;
    }>(),
    {
        yearStale: false,
        invalid: false,
        matchActual: null,
    },
);

const emit = defineEmits<{
    'amount-blur': [];
    'match-actual': [];
}>();

const planInput = ref<HTMLInputElement | null>(null);

/** The button turns into the marker, so focus moves to the plan field. */
function onMatchActual(): void {
    emit('match-actual');
    void nextTick(() => planInput.value?.focus());
}

const amountErrorId = computed(
    () => `month-plan-amount-error-${props.row.category_id}`,
);

const amountInput = defineModel<string>();

const t = useTranslations();

const hasKindColumn = computed(() => props.row.type === 'expense');

const hasAmountField = computed(
    () => props.editable && (props.row.in_plan || !props.row.is_archived),
);

const showsYearLine = computed(
    () => props.row.year_comparison !== null && !props.row.is_idle_even_annual,
);

const hasSubtitle = computed(
    () => showsYearLine.value || props.row.even_annual_remaining !== null,
);

const rhythmLabel = computed(() => {
    switch (props.row.rhythm) {
        case 'fixed_charge':
            return t.month_plan.rhythm_fixed_charge;
        case 'running':
            return t.month_plan.rhythm_running;
        case 'even_annual':
            return t.month_plan.rhythm_even_annual;
        default:
            return '';
    }
});

const evenAnnualRemainingText = computed(() => {
    const remaining = props.row.even_annual_remaining;

    if (remaining === null) {
        return '';
    }

    return t.month_plan.even_annual_remaining
        .replace(
            ':amount',
            formatMoneyAmount(remaining.available_limit_minor_units),
        )
        .replace(':months', String(remaining.months_left));
});

const deviationText = computed(() =>
    props.deviation === null ? '—' : formatSignedMoneyAmount(props.deviation),
);

const deviationClass = computed(() => {
    if (props.deviation === null) {
        return 'text-muted-foreground';
    }

    if (props.overPlan) {
        return 'text-destructive';
    }

    if (props.row.type === 'income' && props.deviation > 0) {
        return 'text-success';
    }

    return '';
});
</script>

<template>
    <div
        class="month-plan-grid month-plan-row"
        :class="hasKindColumn ? '' : 'month-plan-grid-no-kind'"
    >
        <div class="month-plan-cell-category">
            <CategoryChip
                :name="row.name"
                :icon="row.icon"
                :color="row.color"
                size="lg"
            >
                <template #badges>
                    <Badge v-if="row.is_archived" variant="neutral">
                        {{ t.month_plan.archived_badge }}
                    </Badge>
                    <Badge v-if="!row.in_plan" variant="neutral">
                        {{ t.month_plan.not_in_plan }}
                    </Badge>
                </template>

                <template v-if="hasSubtitle" #subtitle>
                    <span class="meta text-muted-foreground">
                        <span
                            v-if="showsYearLine && row.year_comparison !== null"
                            :class="yearStale ? '' : 'text-foreground'"
                        >
                            {{ t.month_plan.year_line }}:
                            <MonthYearSummary
                                :comparison="row.year_comparison"
                                :type="row.type"
                            />
                        </span>
                        <span v-if="showsYearLine && yearStale">
                            · {{ t.month_plan.year_stale_hint }}
                        </span>
                        <span
                            v-if="row.even_annual_remaining !== null"
                            class="tabular-nums"
                            :class="
                                row.even_annual_remaining
                                    .available_limit_minor_units < 0
                                    ? 'text-destructive'
                                    : ''
                            "
                        >
                            <template v-if="showsYearLine">· </template
                            >{{ evenAnnualRemainingText }}
                        </span>
                    </span>
                </template>
            </CategoryChip>
        </div>

        <div v-if="hasKindColumn" class="month-plan-cell-kind">
            <span
                v-if="row.rhythm !== null"
                class="text-muted-foreground text-sm"
            >
                {{ rhythmLabel }}
            </span>
        </div>

        <div class="month-plan-cell-approved">
            <span class="meta text-muted-foreground xl:sr-only">
                {{ t.month_plan.approved_plan }}
            </span>
            <PlanChangeArrow
                v-if="
                    row.approved_minor_units !== null && planMinorUnits !== null
                "
                :reference="row.approved_minor_units"
                :current="planMinorUnits"
                :category-type="row.type"
                :muted="row.is_archived"
                :increased-label="t.month_plan.plan_increased"
                :decreased-label="t.month_plan.plan_decreased"
            />
            <span
                v-if="row.approved_minor_units !== null"
                class="amount text-muted-foreground w-full xl:w-auto"
            >
                {{ formatMoneyAmount(row.approved_minor_units) }}
            </span>
            <template v-else>
                <span
                    class="amount text-muted-foreground w-full xl:w-auto"
                    aria-hidden="true"
                >
                    —
                </span>
                <span class="sr-only">{{ t.month_plan.approved_none }}</span>
            </template>
        </div>

        <div class="month-plan-cell-plan">
            <span class="meta text-muted-foreground block xl:sr-only">
                {{ t.month_plan.plan }}
            </span>
            <input
                v-if="hasAmountField"
                ref="planInput"
                v-model="amountInput"
                class="cell-input"
                inputmode="decimal"
                autocomplete="off"
                :aria-label="`${t.month_plan.plan}: ${row.name}`"
                :aria-invalid="invalid ? 'true' : undefined"
                :aria-describedby="invalid ? amountErrorId : undefined"
                @blur="emit('amount-blur')"
            />
            <span v-else class="amount">
                {{ formatMoneyAmount(row.plan_minor_units) }}
            </span>
            <InputError
                v-if="editable && invalid"
                :id="amountErrorId"
                size="xs"
                class="text-right"
                :message="t.month_plan.amount_invalid"
            />
        </div>

        <div class="month-plan-cell-actual">
            <span class="meta text-muted-foreground xl:sr-only">
                {{ t.month_plan.actual }}
            </span>
            <MonthPlanMatchActual
                v-if="matchActual !== null"
                :state="matchActual"
                :offer-label="t.month_plan.match_actual"
                :raised-label="t.month_plan.raised_to_actual"
                @match="onMatchActual"
            />
            <span class="amount w-full xl:w-auto">
                {{ formatMoneyAmount(row.actual_minor_units) }}
            </span>
        </div>

        <div class="month-plan-cell-deviation">
            <span class="meta text-muted-foreground block xl:sr-only">
                {{ t.month_plan.deviation }}
            </span>
            <span class="amount" :class="deviationClass">
                {{ deviationText }}
            </span>
        </div>

        <div class="month-plan-cell-status">
            <Badge v-if="status" :variant="status.variant">
                {{ t.month_plan[status.key] }}
            </Badge>
        </div>
    </div>
</template>
