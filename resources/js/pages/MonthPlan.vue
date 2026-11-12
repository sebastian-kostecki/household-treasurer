<script setup lang="ts">
import { AlertTriangle, Check, Pencil } from '@lucide/vue';
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import MonthPlanController from '@/actions/App/Http/Controllers/MonthPlanController';
import BudgetMonthPicker from '@/components/BudgetMonthPicker.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import InputError from '@/components/InputError.vue';
import MonthPlanEditBar from '@/components/MonthPlanEditBar.vue';
import MonthPlanStatPanel from '@/components/MonthPlanStatPanel.vue';
import MonthPlanTitleRow from '@/components/MonthPlanTitleRow.vue';
import MonthPlanTable, {
    type MonthPlanRowState,
    type SetAsideDirection,
} from '@/components/MonthPlanTable.vue';
import MonthSummaryCard from '@/components/MonthSummaryCard.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney, minorUnitsToMoneyInput } from '@/lib/format';
import { drawExceedsSpendableLiquidity } from '@/lib/draw-warning';
import { collectedAtEndMinorUnits } from '@/lib/collected-calculator';
import { parsePlanAmountInput } from '@/lib/month-plan-amount';
import {
    isMonthItemOverPlan,
    monthItemDeviationMinorUnits,
} from '@/lib/month-item-deviation';
import { monthPacePreview } from '@/lib/month-pace-preview';
import {
    changeModeAmountInputs,
    matchActualState,
} from '@/lib/month-plan-match-actual';
import { monthPlanGroupTree } from '@/lib/month-plan-groups';
import { monthItemStatus } from '@/lib/month-plan-status';
import { toSpendBreakdown } from '@/lib/to-spend-calculator';
import { budget as budgetRoute, monthPlan as monthPlanRoute } from '@/routes';
import type { MonthPlanItem, MonthPlanReading } from '@/types/month-plan';

const props = defineProps<{
    budget: {
        id: number;
        year: number;
        phase: 'draft' | 'execution';
    } | null;
    viewedMonth: number | null;
    viewedMonthTiming: 'past' | 'current' | 'future' | null;
    monthPlan: MonthPlanReading | null;
}>();

const t = useTranslations();

const isChanging = ref(false);

/** Category Groups the visitor has collapsed; lasts until a change of month or Budget. */
const collapsedGroupIds = ref<number[]>([]);

/** Join rows show in change mode and in an editable proposal (ADR-0067). */
function visibleRows(rows: MonthPlanItem[]): MonthPlanItem[] {
    const showsJoinRows =
        isChanging.value ||
        (props.monthPlan?.state === 'proposal' &&
            props.monthPlan.can_approve === true);

    return showsJoinRows ? rows : rows.filter((row) => !row.is_join_candidate);
}

const allRows = computed<MonthPlanItem[]>(() => [
    ...visibleRows(props.monthPlan?.income_items ?? []),
    ...visibleRows(props.monthPlan?.expense_items ?? []),
]);

/** A row has an amount field when it is in the plan or can still join it. */
function isEditableRow(row: MonthPlanItem): boolean {
    return row.in_plan || !row.is_archived;
}

const planRows = computed(() => allRows.value.filter(isEditableRow));

const isEditable = computed(
    () =>
        (props.monthPlan?.state === 'proposal' &&
            props.monthPlan.can_approve === true) ||
        isChanging.value,
);

function initialAmountInputs(): Record<number, string> {
    return Object.fromEntries(
        planRows.value.map((row) => [
            row.category_id,
            minorUnitsToMoneyInput(row.plan_minor_units),
        ]),
    );
}

const amountInputs = ref<Record<number, string>>(initialAmountInputs());

const setAsideDirection = ref<SetAsideDirection>(initialSetAsideDirection());
const setAsideInput = ref<string>(initialSetAsideInput());

function initialSetAsideDirection(): SetAsideDirection {
    return (props.monthPlan?.set_aside_minor_units ?? 0) < 0
        ? 'draw'
        : 'set_aside';
}

function initialSetAsideInput(): string {
    return minorUnitsToMoneyInput(
        Math.abs(props.monthPlan?.set_aside_minor_units ?? 0),
    );
}

/** Amount fields the user has left once; an invalid one is flagged only after that. */
const touchedAmounts = ref<Record<number, boolean>>({});
const setAsideTouched = ref(false);

function resetTouched(): void {
    touchedAmounts.value = {};
    setAsideTouched.value = false;
}

watch(
    () => [props.viewedMonth, props.monthPlan?.state, props.budget?.id],
    () => {
        amountInputs.value = initialAmountInputs();
        setAsideDirection.value = initialSetAsideDirection();
        setAsideInput.value = initialSetAsideInput();
        resetTouched();
    },
);

watch(
    () => [props.viewedMonth, props.budget?.id],
    () => {
        isChanging.value = false;
        collapsedGroupIds.value = [];
        amountInputs.value = initialAmountInputs();
        resetTouched();
    },
);

function startChanging(): void {
    isChanging.value = true;
    amountInputs.value = changeModeAmountInputs(planRows.value);
    resetTouched();
}

/** Writes the actual amount into the plan field; only where the row offers it (ADR-0062). */
function matchActual(categoryId: number): void {
    const row = planRows.value.find((item) => item.category_id === categoryId);

    if (!row || rowState(row).matchActual !== 'offer') {
        return;
    }

    amountInputs.value = {
        ...amountInputs.value,
        [categoryId]: minorUnitsToMoneyInput(row.actual_minor_units),
    };
}

function stopChanging(): void {
    isChanging.value = false;
    amountInputs.value = initialAmountInputs();
    resetTouched();
}

const formBinding = computed(() => {
    const route = {
        budget: props.budget?.id ?? 0,
        month: props.viewedMonth ?? 0,
    };

    return props.monthPlan?.state === 'approved'
        ? MonthPlanController.update.form(route)
        : MonthPlanController.approve.form(route);
});

function parsedAmount(row: MonthPlanItem): number | null {
    if (!isEditableRow(row)) {
        return 0;
    }

    return parsePlanAmountInput(
        amountInputs.value[row.category_id] ??
            minorUnitsToMoneyInput(row.plan_minor_units),
    );
}

function isAmountFlagged(row: MonthPlanItem): boolean {
    return (
        isEditable.value &&
        touchedAmounts.value[row.category_id] === true &&
        parsedAmount(row) === null
    );
}

const parsedPlanAmounts = computed(() =>
    planRows.value.map((row) => {
        const amount = parsedAmount(row);

        return {
            categoryId: row.category_id,
            amount,
            changed: amount !== row.plan_minor_units,
        };
    }),
);

/**
 * Approval needs the full set of categories. A change sends only the edited
 * rows, so a stale tab cannot overwrite amounts the user did not touch.
 */
const submittedAmounts = computed(() =>
    isChanging.value
        ? parsedPlanAmounts.value.filter((item) => item.changed)
        : parsedPlanAmounts.value,
);

/** Signed form value: a draw is negative. Null when the amount is invalid. */
const formSetAsideMinorUnits = computed<number | null>(() => {
    const parsed = parsePlanAmountInput(setAsideInput.value);

    if (parsed === null) {
        return null;
    }

    return setAsideDirection.value === 'draw' && parsed > 0 ? -parsed : parsed;
});

/** Every wrong amount field, touched or not: it explains why submit is disabled. */
const invalidAmountCount = computed(
    () =>
        parsedPlanAmounts.value.filter((item) => item.amount === null).length +
        (formSetAsideMinorUnits.value === null ? 1 : 0),
);

const allAmountsValid = computed(() => invalidAmountCount.value === 0);

const setAsideFlagged = computed(
    () =>
        isEditable.value &&
        setAsideTouched.value &&
        formSetAsideMinorUnits.value === null,
);

const shownSetAsideMinorUnits = computed<number | null>(() =>
    isEditable.value
        ? formSetAsideMinorUnits.value
        : (props.monthPlan?.set_aside_minor_units ?? 0),
);

const drawWarningMessage = computed<string | null>(() => {
    const value = shownSetAsideMinorUnits.value;

    if (
        value === null ||
        !drawExceedsSpendableLiquidity(
            value,
            props.monthPlan?.spendable_liquidity_minor_units ?? null,
        )
    ) {
        return null;
    }

    return t.month_plan.draw_warning
        .replace(':draw', formatMoney(-value))
        .replace(
            ':liquidity',
            formatMoney(props.monthPlan?.spendable_liquidity_minor_units ?? 0),
        );
});

/** Server reading outside edit mode; the same rule in TS while amounts are typed. */
const toSpendReading = computed<{
    toSpendMinorUnits: number;
    incomeMinorUnits: number;
    expenseMinorUnits: number;
} | null>(() => {
    if (props.monthPlan === null || props.monthPlan.state === 'none') {
        return null;
    }

    if (!isEditable.value) {
        return {
            toSpendMinorUnits: props.monthPlan.to_spend_minor_units ?? 0,
            incomeMinorUnits: props.monthPlan.to_spend_income_minor_units ?? 0,
            expenseMinorUnits:
                props.monthPlan.to_spend_expense_minor_units ?? 0,
        };
    }

    return toSpendBreakdown(
        allRows.value.map((row) => ({
            type: row.type,
            rhythm: row.rhythm,
            plan: isEditableRow(row) ? (parsedAmount(row) ?? 0) : 0,
            actual: row.actual_minor_units,
            hasPositiveAmount: row.has_positive_amount,
            collectedAtStart: row.collected?.start_minor_units ?? 0,
        })),
        formSetAsideMinorUnits.value ?? 0,
    );
});

const pacePreview = computed<{
    percent: number | null;
    isAhead: boolean;
    elapsedDay: number;
    daysInMonth: number;
} | null>(() => {
    const pace = props.monthPlan?.month_pace ?? null;

    if (pace === null) {
        return null;
    }

    const reading = isEditable.value
        ? monthPacePreview(
              allRows.value,
              (row) => (isEditableRow(row) ? (parsedAmount(row) ?? 0) : 0),
              pace.elapsed_day,
              pace.days_in_month,
          )
        : { percent: pace.percent, isAhead: pace.is_ahead };

    return {
        ...reading,
        elapsedDay: pace.elapsed_day,
        daysInMonth: pace.days_in_month,
    };
});

const viewedMonthLabel = computed(() => {
    if (props.budget === null || props.viewedMonth === null) {
        return '';
    }

    const monthName =
        t.budget[`month_name_${props.viewedMonth}` as keyof typeof t.budget];

    return `${monthName} ${props.budget.year}`;
});

const stateBadge = computed<{
    variant: 'brand' | 'warning' | 'outline';
    label: string;
} | null>(() => {
    if (props.monthPlan === null || props.monthPlan.state === 'none') {
        return null;
    }

    if (props.monthPlan.state === 'proposal') {
        return { variant: 'warning', label: t.month_plan.state_proposal };
    }

    return props.monthPlan.summary === null
        ? { variant: 'brand', label: t.month_plan.state_approved }
        : { variant: 'outline', label: t.month_plan.state_final };
});

const stateHint = computed(() => {
    if (isChanging.value) {
        return t.month_plan.changing_hint;
    }

    if (props.monthPlan?.state === 'approved') {
        return props.monthPlan.can_change
            ? t.month_plan.approved_hint
            : t.month_plan.approved_past_hint;
    }

    return props.monthPlan?.can_approve
        ? t.month_plan.proposal_hint
        : t.month_plan.blocked_hint;
});

function monthUrl(month: number): string {
    return monthPlanRoute({ query: { month } }).url;
}

/**
 * While the proposal is edited the deviation follows the typed plan amount;
 * otherwise the server reading is shown.
 */
function rowReading(row: MonthPlanItem): {
    deviation: number | null;
    overPlan: boolean;
} {
    if (!isEditable.value) {
        return {
            deviation: row.deviation_minor_units,
            overPlan: row.is_over_plan,
        };
    }

    const plan = parsedAmount(row);

    if (plan === null) {
        return { deviation: null, overPlan: false };
    }

    const item = {
        type: row.type,
        rhythm: row.rhythm,
        plan,
        yearExecution: row.year_execution_minor_units,
        currentPlan: row.current_plan_minor_units,
        actual: row.actual_minor_units,
        hasPositiveAmount: row.has_positive_amount,
        collectedAtStart: row.collected?.start_minor_units ?? 0,
    };

    return {
        deviation: monthItemDeviationMinorUnits(item),
        overPlan: isMonthItemOverPlan(item),
    };
}

/** The year line is not recomputed while editing, so a changed amount marks it stale. */
function yearStale(row: MonthPlanItem): boolean {
    return (
        isEditable.value &&
        isEditableRow(row) &&
        parsedAmount(row) !== row.plan_minor_units
    );
}

/**
 * The Collected at the end of the month: the server value, or while amounts are
 * typed the same rule in TS from the typed rate (ADR-0073).
 */
function collectedAtEnd(row: MonthPlanItem): number | null {
    if (row.collected === null) {
        return null;
    }

    if (!isEditable.value || !isEditableRow(row)) {
        return row.collected.end_minor_units;
    }

    const plan = parsedAmount(row);

    return plan === null
        ? null
        : collectedAtEndMinorUnits(
              row.collected.start_minor_units,
              plan,
              row.actual_minor_units,
          );
}

function rowState(row: MonthPlanItem): MonthPlanRowState {
    const reading = rowReading(row);

    return {
        ...reading,
        planMinorUnits:
            isEditable.value && isEditableRow(row)
                ? parsedAmount(row)
                : row.plan_minor_units,
        status: monthItemStatus(row, {
            overPlan: reading.overPlan,
            planMinorUnits: isEditable.value
                ? parsedAmount(row)
                : row.plan_minor_units,
            isClosed: props.monthPlan?.summary != null,
        }),
        collectedMinorUnits: collectedAtEnd(row),
        yearStale: yearStale(row),
        invalid: isAmountFlagged(row),
        matchActual: isChanging.value
            ? matchActualState(row, parsedAmount(row), isEditableRow(row))
            : null,
    };
}

const idleOpen = ref(false);

const incomeRows = computed(() =>
    visibleRows(props.monthPlan?.income_items ?? []),
);
const expenseItems = computed(() =>
    visibleRows(props.monthPlan?.expense_items ?? []),
);
const expenseRows = computed(() =>
    expenseItems.value.filter((row) => !row.is_idle_even_annual),
);
const idleRows = computed(() =>
    expenseItems.value.filter((row) => row.is_idle_even_annual),
);

/** A section total is built from the saved plan, so one stale row marks it too. */
const yearTotalsStale = computed(() => ({
    income: incomeRows.value.some(yearStale),
    expense: [...expenseRows.value, ...idleRows.value].some(yearStale),
}));

const hasInvalidIdleAmount = computed(
    () =>
        isEditable.value &&
        idleRows.value.some((row) => parsedAmount(row) === null),
);

// An invalid amount blocks approval, so reveal the row that causes it.
watch(hasInvalidIdleAmount, (invalid) => {
    if (invalid) {
        idleOpen.value = true;
    }
});

/** Groups with a plan field that cannot be read; the table's own tree, so a group has no second rule. */
const unreadableGroupIds = computed<number[]>(() => {
    if (!isEditable.value) {
        return [];
    }

    return [
        ...monthPlanGroupTree(
            props.monthPlan?.income_groups ?? [],
            incomeRows.value,
            rowState,
        ),
        ...monthPlanGroupTree(
            props.monthPlan?.expense_groups ?? [],
            expenseRows.value,
            rowState,
        ),
    ].flatMap((entry) =>
        entry.kind === 'group' && entry.hasUnreadablePlan
            ? [entry.group.id]
            : [],
    );
});

// An invalid amount blocks approval, so open the group that holds it.
watch(unreadableGroupIds, (ids) => {
    if (ids.some((id) => collapsedGroupIds.value.includes(id))) {
        collapsedGroupIds.value = collapsedGroupIds.value.filter(
            (id) => !ids.includes(id),
        );
    }
});

function updateAmount(categoryId: number, value: string): void {
    amountInputs.value[categoryId] = value;
}

function touchAmount(categoryId: number): void {
    touchedAmounts.value[categoryId] = true;
}

const toSpendLabel = computed<'live' | 'final' | 'default'>(() => {
    if (props.monthPlan?.summary) {
        return 'final';
    }

    return isEditable.value ? 'live' : 'default';
});
</script>

<template>
    <Head :title="t.month_plan.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <IndexEmptyState
            v-if="budget === null"
            :title="t.month_plan.no_budget_title"
            :description="t.month_plan.no_budget_description"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="budgetRoute()">
                        {{ t.month_plan.go_to_budget }}
                    </Link>
                </Button>
            </template>
        </IndexEmptyState>

        <IndexEmptyState
            v-else-if="monthPlan === null"
            :title="t.month_plan.draft_title"
            :description="t.month_plan.draft_description"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="budgetRoute()">
                        {{ t.month_plan.go_to_budget }}
                    </Link>
                </Button>
            </template>
        </IndexEmptyState>

        <Form
            v-else-if="viewedMonth !== null"
            v-bind="formBinding"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-4 md:gap-5"
            @success="stopChanging"
        >
            <MonthPlanTitleRow
                :title="
                    t.month_plan.title_with_month.replace(
                        ':month',
                        viewedMonthLabel,
                    )
                "
                :badge="stateBadge"
                :hint="monthPlan.state !== 'none' ? stateHint : null"
            >
                <template #actions>
                    <template
                        v-if="
                            monthPlan.state === 'proposal' &&
                            monthPlan.can_approve
                        "
                    >
                        <span
                            v-if="invalidAmountCount > 0"
                            id="month-plan-invalid-count"
                            class="text-destructive text-sm"
                        >
                            {{
                                t.month_plan.invalid_amounts.replace(
                                    ':count',
                                    String(invalidAmountCount),
                                )
                            }}
                        </span>
                        <Button
                            type="submit"
                            variant="brand"
                            :disabled="processing || !allAmountsValid"
                            :aria-describedby="
                                invalidAmountCount > 0
                                    ? 'month-plan-invalid-count'
                                    : undefined
                            "
                        >
                            <Spinner v-if="processing" />
                            <Check v-else aria-hidden="true" />
                            {{ t.month_plan.approve }}
                        </Button>
                    </template>
                    <Button
                        v-else-if="monthPlan.can_change && !isChanging"
                        type="button"
                        variant="outline"
                        @click="startChanging"
                    >
                        <Pencil aria-hidden="true" />
                        {{ t.month_plan.change }}
                    </Button>
                </template>
            </MonthPlanTitleRow>
            <InputError
                v-if="monthPlan.state === 'proposal'"
                :message="errors.items"
            />

            <BudgetMonthPicker
                :budget-year="budget.year"
                :viewed-month="viewedMonth"
                :month-url="monthUrl"
                :month-states="monthPlan.month_states"
            />

            <IndexEmptyState
                v-if="monthPlan.state === 'none'"
                :title="t.month_plan.none_title"
                :description="t.month_plan.none_description"
            />

            <template v-else>
                <MonthPlanStatPanel
                    v-if="toSpendReading !== null"
                    :to-spend-minor-units="toSpendReading.toSpendMinorUnits"
                    :income-minor-units="toSpendReading.incomeMinorUnits"
                    :expense-minor-units="toSpendReading.expenseMinorUnits"
                    :to-spend-label="toSpendLabel"
                    :pace="pacePreview"
                />

                <MonthSummaryCard
                    v-if="monthPlan.summary"
                    :summary="monthPlan.summary"
                />

                <template v-if="isEditable">
                    <input
                        type="hidden"
                        name="set_aside_minor_units"
                        :value="formSetAsideMinorUnits ?? 0"
                    />
                    <template
                        v-for="(item, index) in submittedAmounts"
                        :key="item.categoryId"
                    >
                        <input
                            type="hidden"
                            :name="`items[${index}][category_id]`"
                            :value="item.categoryId"
                        />
                        <input
                            type="hidden"
                            :name="`items[${index}][amount_minor_units]`"
                            :value="item.amount ?? 0"
                        />
                    </template>
                </template>

                <MonthPlanTable
                    v-model:idle-open="idleOpen"
                    v-model:collapsed-group-ids="collapsedGroupIds"
                    v-model:set-aside-direction="setAsideDirection"
                    v-model:set-aside-input="setAsideInput"
                    :income-rows="incomeRows"
                    :income-groups="monthPlan.income_groups"
                    :expense-groups="monthPlan.expense_groups"
                    :expense-rows="expenseRows"
                    :idle-rows="idleRows"
                    :editable="isEditable"
                    :amount-inputs="amountInputs"
                    :row-state="rowState"
                    :year-totals="monthPlan.year_totals"
                    :year-totals-stale="yearTotalsStale"
                    :set-aside-minor-units="shownSetAsideMinorUnits"
                    :show-set-aside="monthPlan.summary === null"
                    :set-aside-flagged="setAsideFlagged"
                    :set-aside-error="errors.set_aside_minor_units"
                    @update-amount="updateAmount"
                    @amount-blur="touchAmount"
                    @match-actual="matchActual"
                    @set-aside-blur="setAsideTouched = true"
                />

                <Alert v-if="drawWarningMessage" variant="note">
                    <AlertTriangle aria-hidden="true" />
                    <AlertDescription>
                        {{ drawWarningMessage }}
                    </AlertDescription>
                </Alert>

                <MonthPlanEditBar
                    v-if="isChanging"
                    :processing="processing"
                    :can-submit="allAmountsValid && submittedAmounts.length > 0"
                    :invalid-count="invalidAmountCount"
                    :error="errors.items"
                    @cancel="stopChanging"
                />
            </template>
        </Form>
    </div>
</template>
