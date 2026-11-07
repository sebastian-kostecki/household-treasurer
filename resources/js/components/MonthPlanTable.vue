<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import ColumnHeaderHint from '@/components/ColumnHeaderHint.vue';
import InputError from '@/components/InputError.vue';
import ListSurface from '@/components/ListSurface.vue';
import MonthPlanItemRow from '@/components/MonthPlanItemRow.vue';
import MonthYearSummary from '@/components/MonthYearSummary.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoneyAmount } from '@/lib/format';
import type { MonthItemStatus } from '@/lib/month-plan-status';
import type {
    MonthItemKind,
    MonthPlanItem,
    MonthYearComparison,
} from '@/types/month-plan';

export type MonthPlanRowState = {
    deviation: number | null;
    overPlan: boolean;
    status: MonthItemStatus | null;
    yearStale: boolean;
    invalid: boolean;
    kindError?: string;
};

export type SetAsideDirection = 'set_aside' | 'draw';

const props = defineProps<{
    incomeRows: MonthPlanItem[];
    /** Expense rows without the idle one-offs, which sit in the collapsible. */
    expenseRows: MonthPlanItem[];
    idleRows: MonthPlanItem[];
    editable: boolean;
    /** Typed amounts by category id; the table never writes to it. */
    amountInputs: Record<number, string>;
    rowState: (row: MonthPlanItem) => MonthPlanRowState;
    kindPendingCategoryId: number | null;
    yearTotals: {
        income: MonthYearComparison;
        expense: MonthYearComparison;
    } | null;
    yearTotalsStale: { income: boolean; expense: boolean };
    /** Null while the typed set-aside amount is unreadable. */
    setAsideMinorUnits: number | null;
    /** No set-aside row for a past month with a summary. */
    showSetAside: boolean;
    setAsideFlagged: boolean;
    setAsideError?: string;
}>();

const emit = defineEmits<{
    'update-amount': [categoryId: number, value: string];
    'amount-blur': [categoryId: number];
    'change-kind': [row: MonthPlanItem, kind: MonthItemKind];
    'set-aside-blur': [];
}>();

const idleOpen = defineModel<boolean>('idleOpen', { default: false });
const setAsideDirection = defineModel<SetAsideDirection>('setAsideDirection', {
    default: 'set_aside',
});
const setAsideInput = defineModel<string>('setAsideInput', { default: '' });

const t = useTranslations();

const showsKindScopeNote = computed(() =>
    [...props.expenseRows, ...props.idleRows].some(
        (row) => row.can_change_kind,
    ),
);

const setAsideDirectionOptions = computed(() =>
    (['set_aside', 'draw'] as SetAsideDirection[]).map((direction) => ({
        value: direction,
        label: directionLabel(direction),
    })),
);

function directionLabel(direction: SetAsideDirection): string {
    return direction === 'draw'
        ? t.month_plan.set_aside_draw
        : t.month_plan.set_aside_save;
}

const shownDirection = computed<SetAsideDirection>(() =>
    props.editable
        ? setAsideDirection.value
        : (props.setAsideMinorUnits ?? 0) < 0
          ? 'draw'
          : 'set_aside',
);

const setAsideEffect = computed(() => {
    const value = props.setAsideMinorUnits ?? 0;

    if (value === 0) {
        return null;
    }

    return value < 0
        ? t.month_plan.set_aside_raises
        : t.month_plan.set_aside_lowers;
});

const incomeRowsEmpty = computed(() => props.incomeRows.length === 0);
const expenseRowsEmpty = computed(
    () => props.expenseRows.length === 0 && props.idleRows.length === 0,
);
</script>

<template>
    <ListSurface flush>
        <p
            v-if="showsKindScopeNote"
            class="month-plan-footer meta text-muted-foreground"
        >
            {{ t.month_plan.kind_scope_note }}
        </p>

        <div class="month-plan-grid month-plan-header-row">
            <span class="month-plan-cell-category">
                {{ t.month_plan.category }}
            </span>
            <span class="month-plan-cell-kind">
                {{ t.month_plan.kind_column }}
            </span>
            <span class="month-plan-cell-plan">
                {{ t.month_plan.plan }}
            </span>
            <span class="month-plan-cell-actual">
                {{ t.month_plan.actual }}
            </span>
            <span class="month-plan-cell-deviation">
                {{ t.month_plan.deviation }}
            </span>
            <span class="month-plan-cell-status">
                {{ t.month_plan.status_column }}
            </span>
        </div>

        <div class="month-plan-group-row">
            {{ t.month_plan.income_table }}
        </div>
        <p
            v-if="incomeRowsEmpty"
            class="month-plan-row text-muted-foreground text-sm"
        >
            {{ t.month_plan.empty_list }}
        </p>
        <MonthPlanItemRow
            v-for="row in incomeRows"
            :key="row.category_id"
            :model-value="amountInputs[row.category_id]"
            :row="row"
            :editable="editable"
            :deviation="rowState(row).deviation"
            :over-plan="rowState(row).overPlan"
            :status="rowState(row).status"
            :year-stale="rowState(row).yearStale"
            :kind-pending="kindPendingCategoryId === row.category_id"
            :kind-error="rowState(row).kindError"
            :invalid="rowState(row).invalid"
            @update:model-value="
                emit('update-amount', row.category_id, $event ?? '')
            "
            @change-kind="emit('change-kind', row, $event)"
            @amount-blur="emit('amount-blur', row.category_id)"
        />
        <div
            v-if="yearTotals !== null && !incomeRowsEmpty"
            class="month-plan-total-row"
        >
            <ColumnHeaderHint
                :label="t.month_plan.year_total"
                :hint="t.month_plan.year_total_hint"
            />
            <span
                :class="yearTotalsStale.income ? 'text-muted-foreground' : ''"
            >
                <MonthYearSummary
                    :comparison="yearTotals.income"
                    type="income"
                />
            </span>
            <span
                v-if="yearTotalsStale.income"
                class="text-muted-foreground basis-full text-right"
            >
                {{ t.month_plan.year_stale_hint }}
            </span>
        </div>

        <div class="month-plan-group-row">
            {{ t.month_plan.expense_table }}
        </div>
        <p
            v-if="expenseRowsEmpty"
            class="month-plan-row text-muted-foreground text-sm"
        >
            {{ t.month_plan.empty_list }}
        </p>
        <MonthPlanItemRow
            v-for="row in expenseRows"
            :key="row.category_id"
            :model-value="amountInputs[row.category_id]"
            :row="row"
            :editable="editable"
            :deviation="rowState(row).deviation"
            :over-plan="rowState(row).overPlan"
            :status="rowState(row).status"
            :year-stale="rowState(row).yearStale"
            :kind-pending="kindPendingCategoryId === row.category_id"
            :kind-error="rowState(row).kindError"
            :invalid="rowState(row).invalid"
            @update:model-value="
                emit('update-amount', row.category_id, $event ?? '')
            "
            @change-kind="emit('change-kind', row, $event)"
            @amount-blur="emit('amount-blur', row.category_id)"
        />
        <Collapsible v-if="idleRows.length > 0" v-model:open="idleOpen">
            <div class="month-plan-idle-row">
                <CollapsibleTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="-ml-3 gap-2"
                    >
                        <span class="table-header">
                            {{ t.month_plan.idle_one_offs }}
                        </span>
                        <Badge variant="neutral">
                            {{ idleRows.length }}
                        </Badge>
                        <ChevronDown
                            class="size-4 transition-transform"
                            :class="idleOpen ? 'rotate-180' : ''"
                            aria-hidden="true"
                        />
                    </Button>
                </CollapsibleTrigger>
            </div>
            <CollapsibleContent>
                <MonthPlanItemRow
                    v-for="row in idleRows"
                    :key="row.category_id"
                    :model-value="amountInputs[row.category_id]"
                    :row="row"
                    :editable="editable"
                    :deviation="rowState(row).deviation"
                    :over-plan="rowState(row).overPlan"
                    :status="rowState(row).status"
                    :year-stale="rowState(row).yearStale"
                    :kind-pending="kindPendingCategoryId === row.category_id"
                    :kind-error="rowState(row).kindError"
                    :invalid="rowState(row).invalid"
                    @update:model-value="
                        emit('update-amount', row.category_id, $event ?? '')
                    "
                    @change-kind="emit('change-kind', row, $event)"
                    @amount-blur="emit('amount-blur', row.category_id)"
                />
            </CollapsibleContent>
        </Collapsible>
        <div
            v-if="yearTotals !== null && !expenseRowsEmpty"
            class="month-plan-total-row"
        >
            <ColumnHeaderHint
                :label="t.month_plan.year_total"
                :hint="t.month_plan.year_total_hint"
            />
            <span
                :class="yearTotalsStale.expense ? 'text-muted-foreground' : ''"
            >
                <MonthYearSummary
                    :comparison="yearTotals.expense"
                    type="expense"
                />
            </span>
            <span
                v-if="yearTotalsStale.expense"
                class="text-muted-foreground basis-full text-right"
            >
                {{ t.month_plan.year_stale_hint }}
            </span>
        </div>

        <template v-if="showSetAside">
            <div class="month-plan-group-row">
                <ColumnHeaderHint
                    :label="t.month_plan.set_aside"
                    :hint="t.month_plan.set_aside_hint"
                />
            </div>
            <div
                class="month-plan-grid month-plan-row"
                :class="editable ? '' : 'month-plan-grid-no-kind'"
            >
                <div class="month-plan-cell-category text-sm">
                    {{ directionLabel(shownDirection) }}
                </div>
                <div v-if="editable" class="month-plan-cell-kind">
                    <SegmentedControl
                        v-model="setAsideDirection"
                        :options="setAsideDirectionOptions"
                        :ariaLabel="t.month_plan.set_aside_direction"
                    />
                </div>
                <div class="month-plan-cell-plan">
                    <input
                        v-if="editable"
                        v-model="setAsideInput"
                        class="cell-input"
                        inputmode="decimal"
                        autocomplete="off"
                        :aria-label="t.month_plan.set_aside_amount"
                        :aria-invalid="setAsideFlagged ? 'true' : undefined"
                        :aria-describedby="
                            setAsideFlagged
                                ? 'month-plan-set-aside-error'
                                : undefined
                        "
                        @blur="emit('set-aside-blur')"
                    />
                    <span v-else class="amount">
                        {{ formatMoneyAmount(setAsideMinorUnits ?? 0) }}
                    </span>
                    <InputError
                        v-if="setAsideFlagged"
                        id="month-plan-set-aside-error"
                        size="xs"
                        class="text-right"
                        :message="t.month_plan.amount_invalid"
                    />
                    <InputError
                        size="xs"
                        class="text-right"
                        :message="setAsideError"
                    />
                </div>
                <div class="month-plan-cell-status">
                    <span
                        v-if="setAsideEffect"
                        class="meta text-muted-foreground"
                    >
                        {{ setAsideEffect }}
                    </span>
                </div>
            </div>
        </template>

        <p class="month-plan-footer meta text-muted-foreground">
            {{ t.budget.amounts_in_zl }}
        </p>
    </ListSurface>
</template>
