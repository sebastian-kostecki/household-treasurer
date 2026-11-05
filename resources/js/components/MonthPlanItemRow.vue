<script setup lang="ts">
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import InputError from '@/components/InputError.vue';
import MonthPlanKindCell from '@/components/MonthPlanKindCell.vue';
import MonthYearSummary from '@/components/MonthYearSummary.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import {
    formatMoney,
    formatMoneyAmount,
    formatSignedMoneyAmount,
} from '@/lib/format';
import { monthItemStatus } from '@/lib/month-plan-status';
import type { MonthItemKind, MonthPlanItem } from '@/types/month-plan';

const props = withDefaults(
    defineProps<{
        row: MonthPlanItem;
        editable: boolean;
        deviation: number | null;
        overPlan: boolean;
        yearStale?: boolean;
        kindPending?: boolean;
        kindError?: string;
        invalid?: boolean;
    }>(),
    { yearStale: false, kindPending: false, invalid: false },
);

const emit = defineEmits<{
    'change-kind': [kind: MonthItemKind];
    'amount-blur': [];
}>();

const amountErrorId = computed(
    () => `month-plan-amount-error-${props.row.category_id}`,
);

const amountInput = defineModel<string>();

const t = useTranslations();

const hasKindColumn = computed(() => props.row.type === 'expense');

const hasAmountField = computed(
    () => props.editable && (props.row.in_plan || !props.row.is_archived),
);

const status = computed(() => monthItemStatus(props.row, props.overPlan));

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
        <div class="month-plan-cell-category flex flex-col gap-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <CategoryChip
                    :name="row.name"
                    :icon="row.icon"
                    :color="row.color"
                />
                <Badge v-if="row.is_one_off" variant="neutral">
                    {{ t.month_plan.one_off_badge }}
                </Badge>
                <Badge v-if="row.is_archived" variant="neutral">
                    {{ t.month_plan.archived_badge }}
                </Badge>
                <Badge v-if="!row.in_plan" variant="neutral">
                    {{ t.month_plan.not_in_plan }}
                </Badge>
            </div>

            <p
                v-if="row.year_comparison !== null && !row.is_idle_one_off"
                class="meta text-muted-foreground"
            >
                <span :class="yearStale ? '' : 'text-foreground'">
                    {{ t.month_plan.year_line }}:
                    <MonthYearSummary
                        :comparison="row.year_comparison"
                        :type="row.type"
                    />
                </span>
                <span v-if="yearStale">
                    · {{ t.month_plan.year_stale_hint }}
                </span>
            </p>

            <p
                v-if="
                    row.available_limit_minor_units !== null &&
                    (row.year_comparison === null || row.is_idle_one_off)
                "
                class="meta text-muted-foreground"
            >
                {{ t.month_plan.available_limit }}:
                <span
                    class="tabular-nums"
                    :class="
                        row.available_limit_minor_units < 0
                            ? 'text-destructive'
                            : ''
                    "
                >
                    {{ formatMoney(row.available_limit_minor_units) }}
                </span>
            </p>
        </div>

        <div v-if="hasKindColumn" class="month-plan-cell-kind">
            <MonthPlanKindCell
                v-if="row.month_item_kind !== null"
                :kind="row.month_item_kind"
                :can-change="row.can_change_kind"
                :pending="kindPending"
                :error="kindError"
                @change="emit('change-kind', $event)"
            />
        </div>

        <div class="month-plan-cell-plan">
            <span class="meta text-muted-foreground block xl:sr-only">
                {{ t.month_plan.plan }}
            </span>
            <input
                v-if="hasAmountField"
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
            <p
                v-if="
                    row.approved_minor_units !== null &&
                    row.approved_minor_units !== row.plan_minor_units
                "
                class="meta text-muted-foreground"
            >
                {{ t.month_plan.approved_amount }}:
                {{ formatMoney(row.approved_minor_units) }}
            </p>
        </div>

        <div class="month-plan-cell-actual">
            <span class="meta text-muted-foreground block xl:sr-only">
                {{ t.month_plan.actual }}
            </span>
            <span class="amount">
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
