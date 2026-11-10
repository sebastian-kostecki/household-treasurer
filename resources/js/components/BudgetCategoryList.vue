<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import draggable from 'vuedraggable';
import { reorder } from '@/actions/App/Http/Controllers/CategoryController';
import BudgetAmountCell from '@/components/BudgetAmountCell.vue';
import BudgetCategoryGroupRow from '@/components/BudgetCategoryGroupRow.vue';
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
import {
    buildCategoryTree,
    toGroupMember,
    toTreeItem,
    treeToReorderItems,
    type CategoryTreeItem,
    type ReorderItem,
} from '@/lib/category-tree';
import type { DashboardTypeTotals } from '@/lib/dashboard-categories';
import { formatMoney } from '@/lib/format';

export type BudgetCategoryRow = {
    id: number;
    name: string;
    icon: string;
    color: string;
    type: 'income' | 'expense';
    /** The Category Group the Category stands in (ADR-0069), null without one. */
    group_id: number | null;
    /** Place in the one sequence of the type, shared with the groups. */
    sort_order: number;
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
    rhythm: 'fixed_charge' | 'running' | 'even_annual' | null;
};

/** A Category Group with at least one active line in the Budget (ADR-0069). */
export type BudgetCategoryGroup = {
    id: number;
    name: string;
    sort_order: number;
    totals: DashboardTypeTotals;
    has_over_limit_category: boolean;
};

type Tree = CategoryTreeItem<BudgetCategoryGroup, BudgetCategoryRow>[];

const NAME_FILTER_THRESHOLD = 12;

const props = defineProps<{
    title: string;
    categories: BudgetCategoryRow[];
    groups: BudgetCategoryGroup[];
    budgetId: number;
    categoryType: 'income' | 'expense';
    phase: 'draft' | 'execution';
    totals: DashboardTypeTotals;
    canReorder: boolean;
    emptyDescription: string;
    yearElapsedPercent: number | null;
    /** Groups that start collapsed; the state is local to the component. */
    initialCollapsedGroupIds?: number[];
}>();

const emit = defineEmits<{
    edit: [category: BudgetCategoryRow];
}>();

const t = useTranslations();
const reorderHttp = useHttp({
    type: props.categoryType,
    items: [] as ReorderItem[],
});

const tree = ref<Tree>([]);
const treeBeforeChange = ref<Tree>([]);
const collapsedGroupIds = ref<number[]>([
    ...(props.initialCollapsedGroupIds ?? []),
]);
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
    () => [props.categories, props.groups] as const,
    ([categories, groups]) => {
        tree.value = buildCategoryTree(
            groups,
            categories.filter((category) => !category.is_archived),
        );
    },
    { immediate: true, deep: true },
);

const archivedCategories = computed(() =>
    props.categories.filter((category) => category.is_archived),
);

function matchesNameFilter(category: BudgetCategoryRow): boolean {
    const query = nameFilter.value.trim().toLowerCase();

    if (query === '') {
        return true;
    }

    return category.name.toLowerCase().includes(query);
}

// With a filter a group shows only the Categories that match, and no row at
// all when none does.
function groupHasMatch(categories: BudgetCategoryRow[]): boolean {
    return !isFiltering.value || categories.some(matchesNameFilter);
}

const filteredArchivedCategories = computed(() =>
    archivedCategories.value.filter(matchesNameFilter),
);

const hasNoFilterResults = computed(
    () =>
        isFiltering.value &&
        !tree.value.some((item) =>
            item.kind === 'group'
                ? item.categories.some(matchesNameFilter)
                : matchesNameFilter(item.category),
        ) &&
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

function itemKey(item: Tree[number]): string {
    return item.kind === 'group'
        ? `group:${item.group.id}`
        : `category:${item.category.id}`;
}

function groupBodyId(groupId: number): string {
    return `budget-${props.categoryType}-group-${groupId}`;
}

function isGroupOpen(groupId: number): boolean {
    return !collapsedGroupIds.value.includes(groupId);
}

function toggleGroup(groupId: number): void {
    collapsedGroupIds.value = isGroupOpen(groupId)
        ? [...collapsedGroupIds.value, groupId]
        : collapsedGroupIds.value.filter((id) => id !== groupId);
}

const topListGroup = computed(() => ({
    name: `budget-list-${props.categoryType}`,
}));

// A group never enters a group: only a Category may be put into a group's list.
const groupListGroup = computed(() => ({
    name: `budget-list-${props.categoryType}`,
    put: (_to: unknown, _from: unknown, dragEl: HTMLElement): boolean =>
        dragEl.dataset.kind === 'category',
}));

function snapshotTree(): Tree {
    return tree.value.map((item) =>
        item.kind === 'group'
            ? { ...item, categories: [...item.categories] }
            : item,
    );
}

// Dragging between the top list and a group list moves the raw element, so the
// elements are put back into the shape of the list they now sit in.
function setTree(items: Array<Tree[number] | BudgetCategoryRow>): void {
    tree.value = items.map((element) =>
        toTreeItem<BudgetCategoryGroup, BudgetCategoryRow>(element),
    );
}

function setGroupCategories(
    item: Tree[number],
    categories: Array<Tree[number] | BudgetCategoryRow>,
): void {
    if (item.kind === 'group') {
        item.categories = categories.map((element) =>
            toGroupMember<BudgetCategoryGroup, BudgetCategoryRow>(element),
        );
    }
}

function onChangeStart(): void {
    treeBeforeChange.value = snapshotTree();
}

// Saves the visible tree of the type; the server answers with the new order,
// membership and group totals, so the page reloads those props afterwards (also
// after a rejection, so the view shows the order the server holds).
async function saveTree(): Promise<void> {
    if (!canDrag.value) {
        return;
    }

    reorderHttp.type = props.categoryType;
    reorderHttp.items = treeToReorderItems(tree.value);

    try {
        // A 422 (the tree no longer matches the Budget, e.g. changed in another
        // tab) does not throw: useHttp passes it to onError and resolves.
        await reorderHttp.patch(reorder.url(props.budgetId), {
            onError: (errors) => {
                console.error(
                    'The server rejected the budget category order.',
                    errors,
                );
                tree.value = treeBeforeChange.value;
                toast.error(t.budget.reorder_failed);
            },
        });
    } catch (error) {
        console.error('Saving the budget category order failed.', error);
        tree.value = treeBeforeChange.value;
        toast.error(t.budget.reorder_failed);

        return;
    }

    router.reload({
        only: [
            'incomeCategories',
            'expenseCategories',
            'incomeGroups',
            'expenseGroups',
        ],
    });
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
                :model-value="tree"
                tag="div"
                :item-key="itemKey"
                handle=".drag-handle"
                :group="topListGroup"
                :disabled="!canDrag || reorderHttp.processing"
                :animation="150"
                ghost-class="opacity-50"
                @update:model-value="setTree($event)"
                @start="onChangeStart"
                @end="saveTree"
            >
                <template #item="{ element: item }">
                    <BudgetCategoryRowComponent
                        v-if="item.kind === 'category'"
                        v-show="matchesNameFilter(item.category)"
                        data-kind="category"
                        :category="item.category"
                        :budget-id="budgetId"
                        :category-type="categoryType"
                        :phase="phase"
                        :show-handle="canDrag"
                        :year-elapsed-percent="yearElapsedPercent"
                        @edit="emit('edit', item.category)"
                    />
                    <div
                        v-else
                        v-show="groupHasMatch(item.categories)"
                        data-kind="group"
                    >
                        <BudgetCategoryGroupRow
                            :name="item.group.name"
                            :totals="item.group.totals"
                            :count="item.categories.length"
                            :has-over-limit-category="
                                item.group.has_over_limit_category
                            "
                            :category-type="categoryType"
                            :phase="phase"
                            :show-handle="canDrag"
                            :open="isGroupOpen(item.group.id)"
                            :controls="groupBodyId(item.group.id)"
                            @toggle="toggleGroup(item.group.id)"
                        />
                        <div
                            v-show="isGroupOpen(item.group.id)"
                            :id="groupBodyId(item.group.id)"
                            class="budget-category-group-body"
                        >
                            <draggable
                                :model-value="item.categories"
                                tag="div"
                                item-key="id"
                                handle=".drag-handle"
                                :group="groupListGroup"
                                :disabled="!canDrag || reorderHttp.processing"
                                :animation="150"
                                ghost-class="opacity-50"
                                :class="{
                                    'budget-category-group-items-empty':
                                        item.categories.length === 0,
                                }"
                                @update:model-value="
                                    setGroupCategories(item, $event)
                                "
                                @start="onChangeStart"
                                @end="saveTree"
                            >
                                <template #item="{ element: category }">
                                    <BudgetCategoryRowComponent
                                        v-show="matchesNameFilter(category)"
                                        data-kind="category"
                                        nested
                                        :category="category"
                                        :budget-id="budgetId"
                                        :category-type="categoryType"
                                        :phase="phase"
                                        :show-handle="canDrag"
                                        :year-elapsed-percent="
                                            yearElapsedPercent
                                        "
                                        @edit="emit('edit', category)"
                                    />
                                </template>
                            </draggable>
                            <p
                                v-if="item.categories.length === 0"
                                class="budget-category-group-hint"
                            >
                                {{ t.budget.group_empty_hint }}
                            </p>
                        </div>
                    </div>
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
                    :budget-id="budgetId"
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
