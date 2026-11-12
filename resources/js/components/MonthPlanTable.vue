<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { createReusableTemplate } from '@vueuse/core';
import { computed } from 'vue';
import ColumnHeaderHint from '@/components/ColumnHeaderHint.vue';
import InputError from '@/components/InputError.vue';
import ListSurface from '@/components/ListSurface.vue';
import MonthPlanGroupRow from '@/components/MonthPlanGroupRow.vue';
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
import TextLink from '@/components/TextLink.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoneyAmount } from '@/lib/format';
import { edit as editMonthPlanSettings } from '@/routes/month-plan-settings';
import type { MatchActualState } from '@/lib/month-plan-match-actual';
import { monthPlanGroupTree } from '@/lib/month-plan-groups';
import type {
    MonthPlanGroupEntry,
    MonthPlanTreeEntry,
} from '@/lib/month-plan-groups';
import type { MonthItemStatus } from '@/lib/month-plan-status';
import type {
    MonthPlanGroup,
    MonthPlanItem,
    MonthYearComparison,
} from '@/types/month-plan';

export type MonthPlanRowState = {
    /** The plan the row shows now (typed value in change mode); null while the entry is unreadable. */
    planMinorUnits: number | null;
    deviation: number | null;
    overPlan: boolean;
    status: MonthItemStatus | null;
    /** The Collected at the end of the month of an irregular row (live while typing); null for any other row. */
    collectedMinorUnits: number | null;
    yearStale: boolean;
    invalid: boolean;
    /** Button or marker "plan = actual" in change mode (ADR-0062). */
    matchActual: MatchActualState;
};

export type SetAsideDirection = 'set_aside' | 'draw';

const props = defineProps<{
    incomeRows: MonthPlanItem[];
    incomeGroups: MonthPlanGroup[];
    expenseGroups: MonthPlanGroup[];
    /** Expense rows without the idle Even Annual items, which sit in the collapsible. */
    expenseRows: MonthPlanItem[];
    idleRows: MonthPlanItem[];
    editable: boolean;
    /** Typed amounts by category id; the table never writes to it. */
    amountInputs: Record<number, string>;
    rowState: (row: MonthPlanItem) => MonthPlanRowState;
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
    /** The Default Set-aside Percent behind the proposal's amount; null outside a proposal with a percent and in change mode. */
    setAsideDefault?: { percent: number; incomeMinorUnits: number } | null;
}>();

const emit = defineEmits<{
    'update-amount': [categoryId: number, value: string];
    'amount-blur': [categoryId: number];
    'set-aside-blur': [];
    'match-actual': [categoryId: number];
}>();

const idleOpen = defineModel<boolean>('idleOpen', { default: false });
const setAsideDirection = defineModel<SetAsideDirection>('setAsideDirection', {
    default: 'set_aside',
});
const setAsideInput = defineModel<string>('setAsideInput', { default: '' });
/** Ids of the Category Groups the visitor has collapsed; the page owns it and resets it. */
const collapsedGroupIds = defineModel<number[]>('collapsedGroupIds', {
    default: () => [],
});

const [DefineRow, ReuseRow] = createReusableTemplate<{
    row: MonthPlanItem;
    nested?: boolean;
}>();
const [DefineTree, ReuseTree] = createReusableTemplate<{
    tree: MonthPlanTreeEntry[];
    prefix: string;
}>();

const incomeTree = computed(() =>
    monthPlanGroupTree(props.incomeGroups, props.incomeRows, props.rowState),
);
const expenseTree = computed(() =>
    monthPlanGroupTree(props.expenseGroups, props.expenseRows, props.rowState),
);

function isGroupOpen(groupId: number): boolean {
    return !collapsedGroupIds.value.includes(groupId);
}

/** An open group with an unreadable plan field stays open: the field blocks approval and must show. */
function isGroupLocked(entry: MonthPlanGroupEntry): boolean {
    return entry.hasUnreadablePlan && isGroupOpen(entry.group.id);
}

function toggleGroup(entry: MonthPlanGroupEntry): void {
    if (isGroupLocked(entry)) {
        return;
    }

    const groupId = entry.group.id;

    collapsedGroupIds.value = isGroupOpen(groupId)
        ? [...collapsedGroupIds.value, groupId]
        : collapsedGroupIds.value.filter((id) => id !== groupId);
}

function groupBodyId(prefix: string, groupId: number): string {
    return `month-plan-group-body-${prefix}-${groupId}`;
}

const t = useTranslations();

const showSetAsideDefault = computed(
    () => props.editable && props.setAsideDefault != null,
);

const setAsideDescribedBy = computed(() => {
    const ids = [
        props.setAsideFlagged ? 'month-plan-set-aside-error' : null,
        showSetAsideDefault.value ? 'month-plan-set-aside-default' : null,
    ].filter((id): id is string => id !== null);

    return ids.length > 0 ? ids.join(' ') : undefined;
});

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
        <DefineRow v-slot="{ row, nested }">
            <MonthPlanItemRow
                :class="nested ? 'month-plan-row-nested' : ''"
                :model-value="amountInputs[row.category_id]"
                :row="row"
                :editable="editable"
                :plan-minor-units="rowState(row).planMinorUnits"
                :deviation="rowState(row).deviation"
                :over-plan="rowState(row).overPlan"
                :status="rowState(row).status"
                :collected-minor-units="rowState(row).collectedMinorUnits"
                :year-stale="rowState(row).yearStale"
                :invalid="rowState(row).invalid"
                :match-actual="rowState(row).matchActual"
                @update:model-value="
                    emit('update-amount', row.category_id, $event ?? '')
                "
                @amount-blur="emit('amount-blur', row.category_id)"
                @match-actual="emit('match-actual', row.category_id)"
            />
        </DefineRow>
        <DefineTree v-slot="{ tree, prefix }">
            <template
                v-for="entry in tree"
                :key="
                    entry.kind === 'group'
                        ? `group-${entry.group.id}`
                        : `category-${entry.row.category_id}`
                "
            >
                <template v-if="entry.kind === 'group'">
                    <MonthPlanGroupRow
                        :name="entry.group.name"
                        :count="entry.rows.length"
                        :plan-minor-units="entry.planMinorUnits"
                        :actual-minor-units="entry.actualMinorUnits"
                        :over-plan="entry.overPlan"
                        :open="isGroupOpen(entry.group.id)"
                        :locked="isGroupLocked(entry)"
                        :controls="groupBodyId(prefix, entry.group.id)"
                        @toggle="toggleGroup(entry)"
                    />
                    <div
                        :id="groupBodyId(prefix, entry.group.id)"
                        v-show="isGroupOpen(entry.group.id)"
                        class="month-plan-category-group-body"
                    >
                        <ReuseRow
                            v-for="row in entry.rows"
                            :key="row.category_id"
                            :row="row"
                            :nested="true"
                        />
                    </div>
                </template>
                <ReuseRow v-else :row="entry.row" />
            </template>
        </DefineTree>

        <div class="month-plan-grid month-plan-header-row">
            <span class="month-plan-cell-category">
                {{ t.month_plan.category }}
            </span>
            <span class="month-plan-cell-kind">
                {{ t.month_plan.kind_column }}
            </span>
            <span class="month-plan-cell-approved">
                {{ t.month_plan.approved_plan }}
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
        <ReuseTree :tree="incomeTree" prefix="income" />
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
        <ReuseTree :tree="expenseTree" prefix="expense" />
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
                            {{ t.month_plan.idle_even_annuals }}
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
                <ReuseRow
                    v-for="row in idleRows"
                    :key="row.category_id"
                    :row="row"
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
                        :aria-describedby="setAsideDescribedBy"
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
                    <p
                        v-if="showSetAsideDefault && setAsideDefault"
                        id="month-plan-set-aside-default"
                        class="meta text-muted-foreground text-right"
                    >
                        {{
                            t.month_plan.set_aside_default_basis
                                .replace(
                                    ':percent',
                                    String(setAsideDefault.percent),
                                )
                                .replace(
                                    ':amount',
                                    formatMoneyAmount(
                                        setAsideDefault.incomeMinorUnits,
                                    ),
                                )
                        }}
                        ·
                        <TextLink :href="editMonthPlanSettings()">
                            {{ t.month_plan.set_aside_default_change }}
                        </TextLink>
                    </p>
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
            {{ t.common.amounts_in_zl }}
        </p>
    </ListSurface>
</template>
