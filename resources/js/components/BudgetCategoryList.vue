<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ChevronDown, GripVertical } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import { reorder } from '@/actions/App/Http/Controllers/CategoryController';
import BudgetCategoryRowActions from '@/components/BudgetCategoryRowActions.vue';
import BudgetCategoryTableHeader from '@/components/BudgetCategoryTableHeader.vue';
import CategoryChip from '@/components/CategoryChip.vue';
import PlanChangedHint from '@/components/PlanChangedHint.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import {
    type DashboardTypeTotals,
    meterPercentClass,
} from '@/lib/dashboard-categories';
import { formatMoney } from '@/lib/format';

export type BudgetCategoryRow = {
    id: number;
    name: string;
    icon: string;
    color: string;
    type: 'income' | 'expense';
    planned_amount_minor_units: number;
    initial_plan_minor_units: number;
    current_plan_minor_units: number;
    execution_minor_units: number;
    remaining_minor_units: number;
    percent_complete: number | null;
    is_over_limit: boolean;
    can_edit: boolean;
    can_delete: boolean;
    can_change_type: boolean;
    is_archived: boolean;
    can_archive: boolean;
    can_restore: boolean;
    can_align_current_plan: boolean;
    is_paced: boolean;
};

const NAME_FILTER_THRESHOLD = 12;

const props = defineProps<{
    title: string;
    categories: BudgetCategoryRow[];
    budgetId: number;
    categoryType: 'income' | 'expense';
    phase: 'draft' | 'execution';
    totals: DashboardTypeTotals;
    canReorder: boolean;
    emptyDescription: string;
    addLabel: string;
}>();

const emit = defineEmits<{
    add: [];
    edit: [category: BudgetCategoryRow];
}>();

const t = useTranslations();
const reorderHttp = useHttp({
    type: props.categoryType,
    category_ids: [] as number[],
});

const localCategories = ref<BudgetCategoryRow[]>([]);
const orderBeforeDrag = ref<BudgetCategoryRow[]>([]);
const nameFilter = ref('');
const archivedOpen = ref(false);

const isDraft = computed(() => props.phase === 'draft');
const isExecution = computed(() => props.phase === 'execution');
const showNameFilter = computed(
    () => props.categories.length >= NAME_FILTER_THRESHOLD,
);
const isFiltering = computed(() => nameFilter.value.trim().length > 0);
const canDrag = computed(() => props.canReorder && !isFiltering.value);

const formulaLabel = computed(() =>
    props.categoryType === 'income'
        ? t.budget.formula_income
        : t.budget.formula_expense,
);

const remainingLabel = computed(() =>
    props.categoryType === 'income'
        ? t.budget.remaining_income
        : t.budget.remaining_expense,
);

watch(
    () => props.categories,
    (categories) => {
        localCategories.value = [...categories];
    },
    { immediate: true, deep: true },
);

const archivedCategories = computed(() =>
    localCategories.value.filter((category) => category.is_archived),
);

const activeCategories = computed({
    get: () =>
        localCategories.value.filter((category) => !category.is_archived),
    set: (reorderedActiveCategories) => {
        localCategories.value = [
            ...reorderedActiveCategories,
            ...archivedCategories.value,
        ];
    },
});

function matchesNameFilter(category: BudgetCategoryRow): boolean {
    const query = nameFilter.value.trim().toLowerCase();

    if (query === '') {
        return true;
    }

    return category.name.toLowerCase().includes(query);
}

const filteredArchivedCategories = computed(() =>
    archivedCategories.value.filter(matchesNameFilter),
);

function remainingClass(category: BudgetCategoryRow): string {
    if (props.categoryType === 'expense' && category.is_over_limit) {
        return 'text-destructive';
    }

    return 'text-muted-foreground';
}

function totalsRemainingClass(): string {
    if (
        props.categoryType === 'expense' &&
        props.totals.remaining_minor_units < 0
    ) {
        return 'text-destructive';
    }

    return 'text-muted-foreground';
}

function percentSuffixClass(
    percentComplete: number | null,
    isOverLimit: boolean,
): string {
    return (
        meterPercentClass(props.categoryType, percentComplete, isOverLimit) ||
        'text-muted-foreground'
    );
}

function totalsAreOverLimit(): boolean {
    return (
        props.categoryType === 'expense' &&
        props.totals.remaining_minor_units < 0
    );
}

function onDragStart(): void {
    orderBeforeDrag.value = [...localCategories.value];
}

async function onDragEnd(): Promise<void> {
    if (!canDrag.value) {
        return;
    }

    reorderHttp.type = props.categoryType;
    reorderHttp.category_ids = activeCategories.value.map(
        (category) => category.id,
    );

    try {
        await reorderHttp.patch(reorder.url(props.budgetId));
    } catch {
        localCategories.value = [...orderBeforeDrag.value];
    }
}
</script>

<template>
    <IndexEmptyState
        v-if="categories.length === 0"
        :title="title"
        :description="emptyDescription"
    >
        <template #actions>
            <Button variant="outline" @click="emit('add')">
                {{ addLabel }}
            </Button>
        </template>
    </IndexEmptyState>

    <template v-else>
        <ListSurface class="hidden md:block">
            <template #header>
                <div class="space-y-3">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div class="space-y-1">
                            <h3 class="text-base font-semibold">
                                {{ title }}
                            </h3>
                            <p
                                v-if="isExecution"
                                class="text-muted-foreground text-xs"
                            >
                                {{ formulaLabel }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="emit('add')"
                        >
                            {{ addLabel }}
                        </Button>
                    </div>
                    <Input
                        v-if="showNameFilter"
                        v-model="nameFilter"
                        type="search"
                        :placeholder="t.budget.filter_by_name"
                    />
                </div>
            </template>

            <div class="list-surface-table-scroll quiet-scrollbar">
                <table class="list-surface-table">
                    <BudgetCategoryTableHeader
                        :category-type="categoryType"
                        :phase="phase"
                        :can-reorder="canReorder"
                    />
                    <draggable
                        v-model="activeCategories"
                        tag="tbody"
                        item-key="id"
                        handle=".drag-handle"
                        :disabled="!canDrag"
                        :animation="150"
                        ghost-class="opacity-50"
                        @start="onDragStart"
                        @end="onDragEnd"
                    >
                        <template #item="{ element: category }">
                            <tr v-show="matchesNameFilter(category)">
                                <td v-if="canReorder" class="w-10">
                                    <button
                                        v-if="canDrag"
                                        type="button"
                                        class="drag-handle text-muted-foreground hover:bg-foreground/[0.04] hover:text-foreground flex size-8 items-center justify-center rounded-md active:cursor-grabbing"
                                        :aria-label="t.budget.drag_handle"
                                    >
                                        <GripVertical class="size-4" />
                                    </button>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <CategoryChip
                                            :name="category.name"
                                            :icon="category.icon"
                                            :color="category.color"
                                        />
                                    </div>
                                </td>
                                <td
                                    v-if="isDraft"
                                    class="tabular-amount font-semibold"
                                >
                                    {{
                                        formatMoney(
                                            category.current_plan_minor_units,
                                        )
                                    }}
                                </td>
                                <template v-if="isExecution">
                                    <td
                                        class="tabular-amount text-muted-foreground"
                                    >
                                        <span
                                            class="inline-flex items-center justify-end gap-1"
                                        >
                                            {{
                                                formatMoney(
                                                    category.initial_plan_minor_units,
                                                )
                                            }}
                                            <PlanChangedHint
                                                :category="category"
                                                :label="t.budget.plan_changed"
                                            />
                                        </span>
                                    </td>
                                    <td class="tabular-amount font-semibold">
                                        {{
                                            formatMoney(
                                                category.current_plan_minor_units,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="tabular-amount"
                                        :class="
                                            categoryType === 'income'
                                                ? 'text-success'
                                                : ''
                                        "
                                    >
                                        <span
                                            class="inline-flex items-baseline justify-end"
                                        >
                                            {{
                                                formatMoney(
                                                    category.execution_minor_units,
                                                )
                                            }}
                                            <span
                                                v-if="
                                                    category.percent_complete !==
                                                    null
                                                "
                                                class="ml-1.5 text-xs tabular-nums"
                                                :class="
                                                    percentSuffixClass(
                                                        category.percent_complete,
                                                        category.is_over_limit,
                                                    )
                                                "
                                                :title="t.budget.progress_hint"
                                            >
                                                {{ category.percent_complete }}%
                                            </span>
                                        </span>
                                    </td>
                                    <td
                                        class="tabular-amount"
                                        :class="remainingClass(category)"
                                    >
                                        {{
                                            formatMoney(
                                                category.remaining_minor_units,
                                            )
                                        }}
                                    </td>
                                </template>
                                <td>
                                    <BudgetCategoryRowActions
                                        :category="category"
                                        @edit="emit('edit', category)"
                                    />
                                </td>
                            </tr>
                        </template>
                    </draggable>
                    <tfoot>
                        <tr class="font-semibold">
                            <td v-if="canReorder" />
                            <td>{{ t.budget.totals }}</td>
                            <td v-if="isDraft" class="tabular-amount">
                                {{
                                    formatMoney(totals.current_plan_minor_units)
                                }}
                            </td>
                            <template v-if="isExecution">
                                <td
                                    class="tabular-amount text-muted-foreground"
                                >
                                    {{
                                        formatMoney(
                                            totals.initial_plan_minor_units,
                                        )
                                    }}
                                </td>
                                <td class="tabular-amount">
                                    {{
                                        formatMoney(
                                            totals.current_plan_minor_units,
                                        )
                                    }}
                                </td>
                                <td
                                    class="tabular-amount"
                                    :class="
                                        categoryType === 'income'
                                            ? 'text-success'
                                            : ''
                                    "
                                >
                                    <span
                                        class="inline-flex items-baseline justify-end"
                                    >
                                        {{
                                            formatMoney(
                                                totals.execution_minor_units,
                                            )
                                        }}
                                        <span
                                            v-if="
                                                totals.percent_complete !== null
                                            "
                                            class="ml-1.5 text-xs font-medium tabular-nums"
                                            :class="
                                                percentSuffixClass(
                                                    totals.percent_complete,
                                                    totalsAreOverLimit(),
                                                )
                                            "
                                            :title="t.budget.progress_hint"
                                        >
                                            {{ totals.percent_complete }}%
                                        </span>
                                    </span>
                                </td>
                                <td
                                    class="tabular-amount"
                                    :class="totalsRemainingClass()"
                                >
                                    {{
                                        formatMoney(
                                            totals.remaining_minor_units,
                                        )
                                    }}
                                </td>
                            </template>
                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>

            <Collapsible
                v-if="filteredArchivedCategories.length > 0"
                v-model:open="archivedOpen"
                class="mt-6 space-y-3"
            >
                <CollapsibleTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="gap-2 px-0 hover:bg-transparent"
                    >
                        <h4 class="text-sm font-medium">
                            {{ t.budget.archived_section }}
                        </h4>
                        <Badge variant="secondary">
                            {{ filteredArchivedCategories.length }}
                        </Badge>
                        <ChevronDown
                            class="size-4 transition-transform"
                            :class="archivedOpen ? 'rotate-180' : ''"
                            aria-hidden="true"
                        />
                    </Button>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <div class="list-surface-table-scroll quiet-scrollbar">
                        <table class="list-surface-table">
                            <BudgetCategoryTableHeader
                                :category-type="categoryType"
                                :phase="phase"
                            />
                            <tbody>
                                <tr
                                    v-for="category in filteredArchivedCategories"
                                    :key="category.id"
                                    class="text-muted-foreground"
                                >
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <CategoryChip
                                                :name="category.name"
                                                :icon="category.icon"
                                                :color="category.color"
                                            />
                                            <Badge variant="secondary">
                                                {{ t.budget.archived_badge }}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td
                                        v-if="isDraft"
                                        class="tabular-amount font-semibold"
                                    >
                                        {{
                                            formatMoney(
                                                category.current_plan_minor_units,
                                            )
                                        }}
                                    </td>
                                    <template v-if="isExecution">
                                        <td class="tabular-amount">
                                            {{
                                                formatMoney(
                                                    category.initial_plan_minor_units,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="tabular-amount font-semibold"
                                        >
                                            {{
                                                formatMoney(
                                                    category.current_plan_minor_units,
                                                )
                                            }}
                                        </td>
                                        <td class="tabular-amount">
                                            <span
                                                class="inline-flex items-baseline justify-end"
                                            >
                                                {{
                                                    formatMoney(
                                                        category.execution_minor_units,
                                                    )
                                                }}
                                                <span
                                                    v-if="
                                                        category.percent_complete !==
                                                        null
                                                    "
                                                    class="ml-1.5 text-xs tabular-nums"
                                                    :title="
                                                        t.budget.progress_hint
                                                    "
                                                >
                                                    {{
                                                        category.percent_complete
                                                    }}%
                                                </span>
                                            </span>
                                        </td>
                                        <td
                                            class="tabular-amount"
                                            :class="remainingClass(category)"
                                        >
                                            {{
                                                formatMoney(
                                                    category.remaining_minor_units,
                                                )
                                            }}
                                        </td>
                                    </template>
                                    <td>
                                        <BudgetCategoryRowActions
                                            :category="category"
                                            @edit="emit('edit', category)"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </ListSurface>

        <div class="space-y-3 md:hidden">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <h3 class="text-base font-semibold">
                        {{ title }}
                    </h3>
                    <p v-if="isExecution" class="text-muted-foreground text-xs">
                        {{ formulaLabel }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="emit('add')"
                >
                    {{ addLabel }}
                </Button>
            </div>
            <Input
                v-if="showNameFilter"
                v-model="nameFilter"
                type="search"
                :placeholder="t.budget.filter_by_name"
            />

            <draggable
                v-model="activeCategories"
                item-key="id"
                handle=".drag-handle"
                :disabled="!canDrag"
                :animation="150"
                ghost-class="opacity-50"
                class="space-y-3"
                @start="onDragStart"
                @end="onDragEnd"
            >
                <template #item="{ element: category }">
                    <Card v-show="matchesNameFilter(category)">
                        <CardHeader class="gap-3 pb-3">
                            <div class="flex items-start gap-2">
                                <button
                                    v-if="canDrag"
                                    type="button"
                                    class="drag-handle text-muted-foreground hover:bg-foreground/[0.04] hover:text-foreground mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md active:cursor-grabbing"
                                    :aria-label="t.budget.drag_handle"
                                >
                                    <GripVertical class="size-4" />
                                </button>
                                <div class="flex flex-wrap items-center gap-2">
                                    <CategoryChip
                                        :name="category.name"
                                        :icon="category.icon"
                                        :color="category.color"
                                    />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                class="grid gap-3 text-sm"
                                :class="isDraft ? 'grid-cols-1' : 'grid-cols-2'"
                            >
                                <div v-if="isDraft">
                                    <p class="text-muted-foreground text-xs">
                                        {{ t.budget.plan }}
                                    </p>
                                    <p class="font-semibold tabular-nums">
                                        {{
                                            formatMoney(
                                                category.current_plan_minor_units,
                                            )
                                        }}
                                    </p>
                                </div>
                                <template v-if="isExecution">
                                    <div>
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            {{ t.budget.current_plan }}
                                        </p>
                                        <p class="font-semibold tabular-nums">
                                            {{
                                                formatMoney(
                                                    category.current_plan_minor_units,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            {{ t.budget.execution }}
                                        </p>
                                        <p
                                            class="font-medium tabular-nums"
                                            :class="
                                                categoryType === 'income'
                                                    ? 'text-success'
                                                    : ''
                                            "
                                        >
                                            <span
                                                class="inline-flex items-baseline"
                                            >
                                                {{
                                                    formatMoney(
                                                        category.execution_minor_units,
                                                    )
                                                }}
                                                <span
                                                    v-if="
                                                        category.percent_complete !==
                                                        null
                                                    "
                                                    class="ml-1.5 text-xs tabular-nums"
                                                    :class="
                                                        percentSuffixClass(
                                                            category.percent_complete,
                                                            category.is_over_limit,
                                                        )
                                                    "
                                                    :title="
                                                        t.budget.progress_hint
                                                    "
                                                >
                                                    {{
                                                        category.percent_complete
                                                    }}%
                                                </span>
                                            </span>
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            {{ remainingLabel }}
                                        </p>
                                        <p
                                            class="font-medium tabular-nums"
                                            :class="remainingClass(category)"
                                        >
                                            {{
                                                formatMoney(
                                                    category.remaining_minor_units,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            {{ t.budget.initial_plan }}
                                        </p>
                                        <p
                                            class="text-muted-foreground font-medium tabular-nums"
                                        >
                                            {{
                                                formatMoney(
                                                    category.initial_plan_minor_units,
                                                )
                                            }}
                                            <PlanChangedHint
                                                :category="category"
                                                :label="t.budget.plan_changed"
                                            />
                                        </p>
                                    </div>
                                </template>
                            </div>
                            <BudgetCategoryRowActions
                                :category="category"
                                @edit="emit('edit', category)"
                            />
                        </CardContent>
                    </Card>
                </template>
            </draggable>

            <Card class="border-dashed">
                <CardContent class="grid gap-3 pt-6 text-sm">
                    <p class="font-semibold">
                        {{ t.budget.totals }}
                    </p>
                    <div
                        class="grid gap-3"
                        :class="isDraft ? 'grid-cols-1' : 'grid-cols-2'"
                    >
                        <div v-if="isDraft">
                            <p class="text-muted-foreground text-xs">
                                {{ t.budget.plan }}
                            </p>
                            <p class="font-semibold tabular-nums">
                                {{
                                    formatMoney(totals.current_plan_minor_units)
                                }}
                            </p>
                        </div>
                        <template v-if="isExecution">
                            <div>
                                <p class="text-muted-foreground text-xs">
                                    {{ t.budget.current_plan }}
                                </p>
                                <p class="font-semibold tabular-nums">
                                    {{
                                        formatMoney(
                                            totals.current_plan_minor_units,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs">
                                    {{ t.budget.execution }}
                                </p>
                                <p
                                    class="font-semibold tabular-nums"
                                    :class="
                                        categoryType === 'income'
                                            ? 'text-success'
                                            : ''
                                    "
                                >
                                    <span class="inline-flex items-baseline">
                                        {{
                                            formatMoney(
                                                totals.execution_minor_units,
                                            )
                                        }}
                                        <span
                                            v-if="
                                                totals.percent_complete !== null
                                            "
                                            class="ml-1.5 text-xs font-medium tabular-nums"
                                            :class="
                                                percentSuffixClass(
                                                    totals.percent_complete,
                                                    totalsAreOverLimit(),
                                                )
                                            "
                                            :title="t.budget.progress_hint"
                                        >
                                            {{ totals.percent_complete }}%
                                        </span>
                                    </span>
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs">
                                    {{ remainingLabel }}
                                </p>
                                <p
                                    class="font-semibold tabular-nums"
                                    :class="totalsRemainingClass()"
                                >
                                    {{
                                        formatMoney(
                                            totals.remaining_minor_units,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs">
                                    {{ t.budget.initial_plan }}
                                </p>
                                <p
                                    class="text-muted-foreground font-semibold tabular-nums"
                                >
                                    {{
                                        formatMoney(
                                            totals.initial_plan_minor_units,
                                        )
                                    }}
                                </p>
                            </div>
                        </template>
                    </div>
                </CardContent>
            </Card>

            <Collapsible
                v-if="filteredArchivedCategories.length > 0"
                v-model:open="archivedOpen"
                class="space-y-3"
            >
                <CollapsibleTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="gap-2 px-0 hover:bg-transparent"
                    >
                        <h4 class="text-sm font-medium">
                            {{ t.budget.archived_section }}
                        </h4>
                        <Badge variant="secondary">
                            {{ filteredArchivedCategories.length }}
                        </Badge>
                        <ChevronDown
                            class="size-4 transition-transform"
                            :class="archivedOpen ? 'rotate-180' : ''"
                            aria-hidden="true"
                        />
                    </Button>
                </CollapsibleTrigger>
                <CollapsibleContent class="space-y-3">
                    <Card
                        v-for="category in filteredArchivedCategories"
                        :key="category.id"
                        class="opacity-60"
                    >
                        <CardHeader class="gap-3 pb-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <CategoryChip
                                    :name="category.name"
                                    :icon="category.icon"
                                    :color="category.color"
                                />
                                <Badge variant="secondary">
                                    {{ t.budget.archived_badge }}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                class="grid gap-3 text-sm"
                                :class="isDraft ? 'grid-cols-1' : 'grid-cols-2'"
                            >
                                <div v-if="isDraft">
                                    <p class="text-muted-foreground text-xs">
                                        {{ t.budget.plan }}
                                    </p>
                                    <p class="font-semibold tabular-nums">
                                        {{
                                            formatMoney(
                                                category.current_plan_minor_units,
                                            )
                                        }}
                                    </p>
                                </div>
                                <template v-if="isExecution">
                                    <div>
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            {{ t.budget.current_plan }}
                                        </p>
                                        <p class="font-semibold tabular-nums">
                                            {{
                                                formatMoney(
                                                    category.current_plan_minor_units,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            {{ t.budget.execution }}
                                        </p>
                                        <p class="font-medium tabular-nums">
                                            <span
                                                class="inline-flex items-baseline"
                                            >
                                                {{
                                                    formatMoney(
                                                        category.execution_minor_units,
                                                    )
                                                }}
                                                <span
                                                    v-if="
                                                        category.percent_complete !==
                                                        null
                                                    "
                                                    class="text-muted-foreground ml-1.5 text-xs tabular-nums"
                                                    :title="
                                                        t.budget.progress_hint
                                                    "
                                                >
                                                    {{
                                                        category.percent_complete
                                                    }}%
                                                </span>
                                            </span>
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            {{ remainingLabel }}
                                        </p>
                                        <p
                                            class="font-medium tabular-nums"
                                            :class="remainingClass(category)"
                                        >
                                            {{
                                                formatMoney(
                                                    category.remaining_minor_units,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            {{ t.budget.initial_plan }}
                                        </p>
                                        <p
                                            class="text-muted-foreground font-medium tabular-nums"
                                        >
                                            {{
                                                formatMoney(
                                                    category.initial_plan_minor_units,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </template>
                            </div>
                            <BudgetCategoryRowActions
                                :category="category"
                                @edit="emit('edit', category)"
                            />
                        </CardContent>
                    </Card>
                </CollapsibleContent>
            </Collapsible>
        </div>
    </template>
</template>
