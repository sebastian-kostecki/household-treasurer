<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import MonthPlanController from '@/actions/App/Http/Controllers/MonthPlanController';
import BudgetMonthPicker from '@/components/BudgetMonthPicker.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import InputError from '@/components/InputError.vue';
import ListSurface from '@/components/ListSurface.vue';
import MonthPlanItemRow from '@/components/MonthPlanItemRow.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import {
    formatMoney,
    minorUnitsToMoneyInput,
    parseMoneyInputToMinorUnits,
} from '@/lib/format';
import { drawExceedsSpendableLiquidity } from '@/lib/draw-warning';
import {
    isMonthItemOverPlan,
    monthItemDeviationMinorUnits,
} from '@/lib/month-item-deviation';
import { toSpendMinorUnits } from '@/lib/to-spend-calculator';
import { budget as budgetRoute, monthPlan as monthPlanRoute } from '@/routes';
import type { MonthItemKind, MonthPlanItem } from '@/types/month-plan';

type MonthPlanReading = {
    state: 'proposal' | 'approved' | 'none';
    approved_at: string | null;
    can_approve: boolean;
    to_spend_minor_units: number | null;
    set_aside_minor_units: number;
    spendable_liquidity_minor_units: number | null;
    income_items: MonthPlanItem[];
    expense_items: MonthPlanItem[];
};

const props = defineProps<{
    budget: {
        id: number;
        year: number;
        phase: 'draft' | 'execution';
    } | null;
    viewedMonth: number | null;
    viewedMonthTiming: 'past' | 'current' | 'future' | null;
    currentMonth: number | null;
    monthPlan: MonthPlanReading | null;
}>();

const t = useTranslations();

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.month_plan,
            href: monthPlanRoute(),
        },
    ],
});

const allRows = computed<MonthPlanItem[]>(() => [
    ...(props.monthPlan?.income_items ?? []),
    ...(props.monthPlan?.expense_items ?? []),
]);

const planRows = computed(() => allRows.value.filter((row) => row.in_plan));

const isEditable = computed(
    () =>
        props.monthPlan?.state === 'proposal' &&
        props.monthPlan.can_approve === true,
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

type SetAsideDirection = 'set_aside' | 'draw';

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

watch(
    () => [props.viewedMonth, props.monthPlan?.state, props.budget?.id],
    () => {
        amountInputs.value = initialAmountInputs();
        setAsideDirection.value = initialSetAsideDirection();
        setAsideInput.value = initialSetAsideInput();
    },
);

/** Matches the server limit in ApproveMonthPlanRequest. */
const MAX_PLAN_AMOUNT_MINOR_UNITS = 1_000_000_000_000;

function parsedAmount(row: MonthPlanItem): number | null {
    if (!row.in_plan) {
        return 0;
    }

    const parsed = parseMoneyInputToMinorUnits(
        amountInputs.value[row.category_id] ??
            minorUnitsToMoneyInput(row.plan_minor_units),
    );

    return parsed !== null &&
        parsed >= 0 &&
        parsed <= MAX_PLAN_AMOUNT_MINOR_UNITS
        ? parsed
        : null;
}

const parsedPlanAmounts = computed(() =>
    planRows.value.map((row) => ({
        categoryId: row.category_id,
        amount: parsedAmount(row),
    })),
);

/** Signed form value: a draw is negative. Null when the amount is invalid. */
const formSetAsideMinorUnits = computed<number | null>(() => {
    const parsed = parseMoneyInputToMinorUnits(setAsideInput.value);

    if (parsed === null || parsed < 0 || parsed > MAX_PLAN_AMOUNT_MINOR_UNITS) {
        return null;
    }

    return setAsideDirection.value === 'draw' && parsed > 0 ? -parsed : parsed;
});

const allAmountsValid = computed(
    () =>
        parsedPlanAmounts.value.every((item) => item.amount !== null) &&
        formSetAsideMinorUnits.value !== null,
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

const setAsideDirections: SetAsideDirection[] = ['set_aside', 'draw'];

function setAsideDirectionLabel(direction: SetAsideDirection): string {
    return direction === 'draw'
        ? t.month_plan.set_aside_draw
        : t.month_plan.set_aside_save;
}

const toSpendPreviewMinorUnits = computed<number | null>(() => {
    if (props.monthPlan === null || props.monthPlan.state === 'none') {
        return null;
    }

    if (!isEditable.value) {
        return props.monthPlan.to_spend_minor_units;
    }

    return toSpendMinorUnits(
        allRows.value.map((row) => ({
            type: row.type,
            kind: row.month_item_kind,
            plan: row.in_plan ? (parsedAmount(row) ?? 0) : 0,
            actual: row.actual_minor_units,
            hasPositiveAmount: row.has_positive_amount,
        })),
        formSetAsideMinorUnits.value ?? 0,
    );
});

const viewedMonthLabel = computed(() => {
    if (props.budget === null || props.viewedMonth === null) {
        return '';
    }

    const monthName =
        t.budget[`month_name_${props.viewedMonth}` as keyof typeof t.budget];

    return `${monthName} ${props.budget.year}`;
});

const stateLabel = computed(() =>
    props.monthPlan?.state === 'approved'
        ? t.month_plan.state_approved
        : t.month_plan.state_proposal,
);

function monthUrl(month: number): string {
    return monthPlanRoute({ query: { month } }).url;
}

const kindError = ref<string | null>(null);

function changeKind(row: MonthPlanItem, kind: MonthItemKind): void {
    if (row.month_item_kind === kind) {
        return;
    }

    kindError.value = null;

    router.patch(
        CategoryController.updateMonthItemKind.url(row.category_id),
        { month_item_kind: kind },
        {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => {
                kindError.value = Object.values(errors)[0] ?? null;
            },
        },
    );
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
        kind: row.month_item_kind,
        plan,
        actual: row.actual_minor_units,
        hasPositiveAmount: row.has_positive_amount,
    };

    return {
        deviation: monthItemDeviationMinorUnits(item),
        overPlan: isMonthItemOverPlan(item),
    };
}

const idleOpen = ref(false);

const sections = computed(() => {
    const incomeItems = props.monthPlan?.income_items ?? [];
    const expenseItems = props.monthPlan?.expense_items ?? [];

    return [
        {
            key: 'income',
            title: t.month_plan.income_table,
            rows: incomeItems,
            idleRows: [] as MonthPlanItem[],
        },
        {
            key: 'expense',
            title: t.month_plan.expense_table,
            rows: expenseItems.filter((row) => !row.is_idle_one_off),
            idleRows: expenseItems.filter((row) => row.is_idle_one_off),
        },
    ];
});

const hasInvalidIdleAmount = computed(
    () =>
        isEditable.value &&
        sections.value[1].idleRows.some((row) => parsedAmount(row) === null),
);

// An invalid amount blocks approval, so reveal the row that causes it.
watch(hasInvalidIdleAmount, (invalid) => {
    if (invalid) {
        idleOpen.value = true;
    }
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

        <template v-else-if="viewedMonth !== null">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-lg font-semibold capitalize">
                    {{ viewedMonthLabel }}
                </h1>
                <Badge
                    v-if="monthPlan.state !== 'none'"
                    :variant="
                        monthPlan.state === 'approved' ? 'secondary' : 'outline'
                    "
                >
                    {{ stateLabel }}
                </Badge>
            </div>

            <Card>
                <CardContent class="grid gap-4 pt-6">
                    <BudgetMonthPicker
                        :budget-year="budget.year"
                        :viewed-month="viewedMonth"
                        :show-archived="false"
                        :current-month="currentMonth"
                        :month-url="monthUrl"
                    />
                </CardContent>
            </Card>

            <IndexEmptyState
                v-if="monthPlan.state === 'none'"
                :title="t.month_plan.none_title"
                :description="t.month_plan.none_description"
            />

            <template v-else>
                <Card>
                    <CardContent class="grid gap-1 pt-6">
                        <p
                            class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                        >
                            {{ t.month_plan.to_spend }}
                        </p>
                        <p
                            class="text-3xl font-semibold tabular-nums"
                            :class="
                                (toSpendPreviewMinorUnits ?? 0) < 0
                                    ? 'text-destructive'
                                    : ''
                            "
                        >
                            {{ formatMoney(toSpendPreviewMinorUnits ?? 0) }}
                        </p>
                        <p class="text-muted-foreground text-xs">
                            {{
                                monthPlan.state === 'approved'
                                    ? t.month_plan.approved_hint
                                    : monthPlan.can_approve
                                      ? t.month_plan.proposal_hint
                                      : t.month_plan.blocked_hint
                            }}
                        </p>
                    </CardContent>
                </Card>

                <Form
                    v-bind="
                        MonthPlanController.approve.form({
                            budget: budget.id,
                            month: viewedMonth,
                        })
                    "
                    v-slot="{ errors, processing }"
                    class="space-y-4"
                >
                    <Card>
                        <CardContent class="grid gap-3 pt-6">
                            <p
                                class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                            >
                                {{ t.month_plan.set_aside }}
                            </p>

                            <div
                                v-if="isEditable"
                                class="flex flex-wrap items-center gap-3"
                            >
                                <div
                                    role="group"
                                    :aria-label="
                                        t.month_plan.set_aside_direction
                                    "
                                    class="border-border/60 bg-muted/40 inline-flex gap-1 rounded-xl border p-1"
                                >
                                    <Button
                                        v-for="direction in setAsideDirections"
                                        :key="direction"
                                        type="button"
                                        size="sm"
                                        :variant="
                                            setAsideDirection === direction
                                                ? 'default'
                                                : 'ghost'
                                        "
                                        :aria-pressed="
                                            setAsideDirection === direction
                                        "
                                        class="h-7 rounded-lg px-2 text-xs"
                                        @click="setAsideDirection = direction"
                                    >
                                        {{ setAsideDirectionLabel(direction) }}
                                    </Button>
                                </div>
                                <Input
                                    v-model="setAsideInput"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    :aria-label="t.month_plan.set_aside_amount"
                                    class="w-40 text-right tabular-nums"
                                />
                                <input
                                    type="hidden"
                                    name="set_aside_minor_units"
                                    :value="formSetAsideMinorUnits ?? 0"
                                />
                            </div>
                            <p v-else class="text-sm tabular-nums">
                                {{
                                    setAsideDirectionLabel(
                                        monthPlan.set_aside_minor_units < 0
                                            ? 'draw'
                                            : 'set_aside',
                                    )
                                }}
                                {{
                                    formatMoney(
                                        Math.abs(
                                            monthPlan.set_aside_minor_units,
                                        ),
                                    )
                                }}
                            </p>

                            <InputError
                                :message="errors.set_aside_minor_units"
                            />

                            <p class="text-muted-foreground text-xs">
                                {{ t.month_plan.set_aside_hint }}
                            </p>

                            <Alert v-if="drawWarningMessage" variant="warning">
                                <AlertTriangle class="size-4" />
                                <AlertTitle>{{
                                    t.month_plan.draw_warning_title
                                }}</AlertTitle>
                                <AlertDescription>
                                    {{ drawWarningMessage }}
                                </AlertDescription>
                            </Alert>
                        </CardContent>
                    </Card>

                    <template v-if="isEditable">
                        <template
                            v-for="(row, index) in planRows"
                            :key="row.category_id"
                        >
                            <input
                                type="hidden"
                                :name="`items[${index}][category_id]`"
                                :value="row.category_id"
                            />
                            <input
                                type="hidden"
                                :name="`items[${index}][amount_minor_units]`"
                                :value="parsedPlanAmounts[index]?.amount ?? 0"
                            />
                        </template>
                    </template>

                    <InputError :message="kindError ?? undefined" />

                    <ListSurface
                        v-for="section in sections"
                        :key="section.key"
                        :title="section.title"
                    >
                        <div
                            class="border-border/60 text-muted-foreground grid grid-cols-[1fr_5.5rem_5.5rem] gap-3 border-b pb-2.5 text-[11px] font-medium tracking-wider uppercase sm:grid-cols-[1fr_9rem_9rem] lg:grid-cols-[1fr_9rem_9rem_9rem]"
                        >
                            <span>{{ t.month_plan.category }}</span>
                            <span class="text-right">
                                {{ t.month_plan.plan }}
                            </span>
                            <span class="text-right">
                                {{ t.month_plan.actual }}
                            </span>
                            <span class="hidden text-right lg:block">
                                {{ t.month_plan.deviation }}
                            </span>
                        </div>

                        <p
                            v-if="
                                section.rows.length === 0 &&
                                section.idleRows.length === 0
                            "
                            class="text-muted-foreground py-6 text-center text-sm"
                        >
                            {{ t.month_plan.empty_list }}
                        </p>

                        <MonthPlanItemRow
                            v-for="row in section.rows"
                            :key="row.category_id"
                            v-model="amountInputs[row.category_id]"
                            :row="row"
                            :editable="isEditable"
                            :deviation="rowReading(row).deviation"
                            :over-plan="rowReading(row).overPlan"
                            @change-kind="changeKind(row, $event)"
                        />

                        <Collapsible
                            v-if="section.idleRows.length > 0"
                            v-model:open="idleOpen"
                            class="border-border/60 border-t pt-2"
                        >
                            <CollapsibleTrigger as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="gap-2 px-0 hover:bg-transparent"
                                >
                                    <span class="text-sm font-medium">
                                        {{ t.month_plan.idle_one_offs }}
                                    </span>
                                    <Badge variant="secondary">
                                        {{ section.idleRows.length }}
                                    </Badge>
                                    <ChevronDown
                                        class="size-4 transition-transform"
                                        :class="idleOpen ? 'rotate-180' : ''"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <MonthPlanItemRow
                                    v-for="row in section.idleRows"
                                    :key="row.category_id"
                                    v-model="amountInputs[row.category_id]"
                                    :row="row"
                                    :editable="isEditable"
                                    :deviation="rowReading(row).deviation"
                                    :over-plan="rowReading(row).overPlan"
                                    @change-kind="changeKind(row, $event)"
                                />
                            </CollapsibleContent>
                        </Collapsible>
                    </ListSurface>

                    <div v-if="monthPlan.can_approve" class="space-y-2">
                        <InputError :message="errors.items" />
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :disabled="processing || !allAmountsValid"
                            >
                                {{ t.month_plan.approve }}
                            </Button>
                        </div>
                    </div>
                </Form>
            </template>
        </template>
    </div>
</template>
