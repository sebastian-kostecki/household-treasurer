<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import draggable from 'vuedraggable';
import { reorder } from '@/actions/App/Http/Controllers/CategoryController';
import BudgetAmountCell from '@/components/BudgetAmountCell.vue';
import BudgetCategoryHeaderRow from '@/components/BudgetCategoryHeaderRow.vue';
import BudgetCategoryRowComponent from '@/components/BudgetCategoryRow.vue';
import ExecutionMeter from '@/components/ExecutionMeter.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import {
    executionClass,
    totalsRemainingClass,
} from '@/lib/budget-category-cells';
import type { DashboardTypeTotals } from '@/lib/dashboard-categories';
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
    month_item_kind: 'bill' | 'running';
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
    yearElapsedPercent: number | null;
}>();

const emit = defineEmits<{
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

const hasNoFilterResults = computed(
    () =>
        isFiltering.value &&
        !activeCategories.value.some(matchesNameFilter) &&
        filteredArchivedCategories.value.length === 0,
);

const panelMeta = computed(() =>
    isDraft.value
        ? formatMoney(props.totals.current_plan_minor_units)
        : t.budget.panel_execution_of_plan
              .replace(
                  ':execution',
                  formatMoney(props.totals.execution_minor_units),
              )
              .replace(
                  ':plan',
                  formatMoney(props.totals.current_plan_minor_units),
              ),
);

const totalsAreOverLimit = computed(
    () =>
        props.categoryType === 'expense' &&
        props.totals.remaining_minor_units < 0,
);

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
        toast.error(t.budget.reorder_failed);
    }
}
</script>

<template>
    <IndexEmptyState
        v-if="categories.length === 0"
        :title="title"
        :description="emptyDescription"
    />

    <ListSurface v-else>
        <template #header>
            <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="space-y-1">
                        <h3 class="section-title">
                            {{ title }}
                        </h3>
                        <p
                            v-if="isExecution"
                            class="meta text-muted-foreground"
                        >
                            {{ formulaLabel }}
                        </p>
                    </div>
                    <p class="amount text-muted-foreground">
                        {{ panelMeta }}
                    </p>
                </div>
                <Input
                    v-if="showNameFilter"
                    v-model="nameFilter"
                    type="search"
                    :aria-label="t.budget.filter_by_name"
                    :placeholder="t.budget.filter_by_name"
                />
            </div>
        </template>

        <div>
            <BudgetCategoryHeaderRow
                :category-type="categoryType"
                :phase="phase"
                :show-handle="canDrag"
            />
            <draggable
                v-model="activeCategories"
                tag="div"
                item-key="id"
                handle=".drag-handle"
                :disabled="!canDrag || reorderHttp.processing"
                :animation="150"
                ghost-class="opacity-50"
                @start="onDragStart"
                @end="onDragEnd"
            >
                <template #item="{ element: category }">
                    <BudgetCategoryRowComponent
                        v-show="matchesNameFilter(category)"
                        :category="category"
                        :category-type="categoryType"
                        :phase="phase"
                        :show-handle="canDrag"
                        :year-elapsed-percent="yearElapsedPercent"
                        @edit="emit('edit', category)"
                    />
                </template>
            </draggable>
            <p
                v-if="hasNoFilterResults"
                class="text-muted-foreground px-4 py-6 text-center text-sm"
                role="status"
            >
                {{ t.budget.filter_no_results }}
            </p>
            <div
                class="budget-category-grid budget-category-totals-row font-semibold"
                :class="{
                    'budget-category-grid-draft': isDraft,
                    'budget-category-grid-no-handle': !canDrag,
                }"
            >
                <div class="budget-category-cell-category">
                    {{ t.budget.totals }}
                </div>
                <BudgetAmountCell
                    v-if="isDraft"
                    area="plan"
                    :label="t.budget.plan"
                    :amount="totals.current_plan_minor_units"
                    label-hidden
                    total
                />
                <template v-else>
                    <BudgetAmountCell
                        area="initial"
                        :label="t.budget.initial_plan"
                        :amount="totals.initial_plan_minor_units"
                        total
                        value-class="text-muted-foreground"
                    />
                    <BudgetAmountCell
                        area="current"
                        :label="t.budget.current_plan"
                        :amount="totals.current_plan_minor_units"
                        total
                    />
                    <BudgetAmountCell
                        area="execution"
                        :label="t.budget.execution"
                        :amount="totals.execution_minor_units"
                        total
                        :value-class="executionClass(categoryType, false)"
                    />
                    <BudgetAmountCell
                        area="remaining"
                        :label="remainingLabel"
                        :amount="totals.remaining_minor_units"
                        total
                        :value-class="
                            totalsRemainingClass(
                                categoryType,
                                totals.remaining_minor_units,
                            )
                        "
                    />
                    <div
                        class="budget-category-cell-meter"
                        :title="t.budget.progress_hint"
                    >
                        <ExecutionMeter
                            :percent-complete="totals.percent_complete"
                            :category-type="categoryType"
                            :is-over-limit="totalsAreOverLimit"
                            :year-elapsed-percent="null"
                        />
                    </div>
                </template>
            </div>
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
                    class="-ml-3 gap-2"
                >
                    <span class="table-header">
                        {{ t.budget.archived_section }}
                    </span>
                    <Badge variant="neutral">
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
                <BudgetCategoryHeaderRow
                    :category-type="categoryType"
                    :phase="phase"
                    :show-handle="false"
                />
                <BudgetCategoryRowComponent
                    v-for="category in filteredArchivedCategories"
                    :key="category.id"
                    :category="category"
                    :category-type="categoryType"
                    :phase="phase"
                    :show-handle="false"
                    :year-elapsed-percent="yearElapsedPercent"
                    @edit="emit('edit', category)"
                />
            </CollapsibleContent>
        </Collapsible>

        <p class="meta text-muted-foreground pt-3">
            {{ t.common.amounts_in_zl }}
        </p>
    </ListSurface>
</template>
