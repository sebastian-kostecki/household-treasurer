<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { GripVertical, Pencil } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import { reorder } from '@/actions/App/Http/Controllers/CategoryController';
import BudgetCategoryOverflowActions from '@/components/BudgetCategoryOverflowActions.vue';
import CategoryChip from '@/components/CategoryChip.vue';
import IconAction from '@/components/IconAction.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
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
    is_over_limit: boolean;
    can_edit: boolean;
    can_delete: boolean;
    can_change_type: boolean;
    is_archived: boolean;
    can_archive: boolean;
    can_restore: boolean;
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

const isDraft = computed(() => props.phase === 'draft');
const isExecution = computed(() => props.phase === 'execution');
const showNameFilter = computed(
    () => props.categories.length >= NAME_FILTER_THRESHOLD,
);
const isFiltering = computed(() => nameFilter.value.trim().length > 0);
const canDrag = computed(() => props.canReorder && !isFiltering.value);

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

    return '';
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
                        <h3 class="text-base font-semibold">
                            {{ title }}
                        </h3>
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

            <div class="list-surface-table-scroll">
                <table class="list-surface-table">
                    <thead>
                        <tr>
                            <th v-if="canReorder" class="w-10" />
                            <th>{{ t.budget.category }}</th>
                            <th v-if="isDraft" class="text-right">
                                {{ t.budget.plan }}
                            </th>
                            <template v-if="isExecution">
                                <th class="text-right">
                                    {{ t.budget.initial_plan }}
                                </th>
                                <th class="text-right">
                                    {{ t.budget.current_plan }}
                                </th>
                                <th class="text-right">
                                    {{ t.budget.execution }}
                                </th>
                                <th class="text-right">
                                    {{ t.budget.remaining }}
                                </th>
                            </template>
                            <th class="text-right">
                                {{ t.budget.actions }}
                            </th>
                        </tr>
                    </thead>
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
                                    <CategoryChip
                                        :name="category.name"
                                        :icon="category.icon"
                                        :color="category.color"
                                    />
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
                                    <td class="tabular-amount font-semibold">
                                        {{
                                            formatMoney(
                                                category.current_plan_minor_units,
                                            )
                                        }}
                                    </td>
                                    <td class="tabular-amount">
                                        {{
                                            formatMoney(
                                                category.execution_minor_units,
                                            )
                                        }}
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
                                    <div class="flex justify-end gap-1">
                                        <IconAction
                                            v-if="category.can_edit"
                                            :icon="Pencil"
                                            :label="t.budget.edit"
                                            @click="emit('edit', category)"
                                        />
                                        <BudgetCategoryOverflowActions
                                            :category="category"
                                        />
                                    </div>
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
                                <td class="tabular-amount">
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
                                <td class="tabular-amount">
                                    {{
                                        formatMoney(
                                            totals.execution_minor_units,
                                        )
                                    }}
                                </td>
                                <td
                                    class="tabular-amount"
                                    :class="
                                        categoryType === 'expense' &&
                                        totals.remaining_minor_units < 0
                                            ? 'text-destructive'
                                            : ''
                                    "
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

            <div
                v-if="filteredArchivedCategories.length > 0"
                class="mt-6 space-y-3"
            >
                <div class="flex items-center gap-2">
                    <h4 class="text-sm font-medium">
                        {{ t.budget.archived_section }}
                    </h4>
                    <Badge variant="secondary">
                        {{ filteredArchivedCategories.length }}
                    </Badge>
                </div>
                <div class="list-surface-table-scroll">
                    <table class="list-surface-table">
                        <thead>
                            <tr>
                                <th>{{ t.budget.category }}</th>
                                <th v-if="isDraft" class="text-right">
                                    {{ t.budget.plan }}
                                </th>
                                <template v-if="isExecution">
                                    <th class="text-right">
                                        {{ t.budget.initial_plan }}
                                    </th>
                                    <th class="text-right">
                                        {{ t.budget.current_plan }}
                                    </th>
                                    <th class="text-right">
                                        {{ t.budget.execution }}
                                    </th>
                                    <th class="text-right">
                                        {{ t.budget.remaining }}
                                    </th>
                                </template>
                                <th class="text-right">
                                    {{ t.budget.actions }}
                                </th>
                            </tr>
                        </thead>
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
                                    <td class="tabular-amount font-semibold">
                                        {{
                                            formatMoney(
                                                category.current_plan_minor_units,
                                            )
                                        }}
                                    </td>
                                    <td class="tabular-amount">
                                        {{
                                            formatMoney(
                                                category.execution_minor_units,
                                            )
                                        }}
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
                                    <div class="flex justify-end gap-1">
                                        <IconAction
                                            v-if="category.can_edit"
                                            :icon="Pencil"
                                            :label="t.budget.edit"
                                            @click="emit('edit', category)"
                                        />
                                        <BudgetCategoryOverflowActions
                                            :category="category"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </ListSurface>

        <div class="space-y-3 md:hidden">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-semibold">
                    {{ title }}
                </h3>
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
                                <CategoryChip
                                    :name="category.name"
                                    :icon="category.icon"
                                    :color="category.color"
                                />
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
                                            {{ t.budget.initial_plan }}
                                        </p>
                                        <p class="font-medium tabular-nums">
                                            {{
                                                formatMoney(
                                                    category.initial_plan_minor_units,
                                                )
                                            }}
                                        </p>
                                    </div>
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
                                            {{
                                                formatMoney(
                                                    category.execution_minor_units,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            {{ t.budget.remaining }}
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
                                </template>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <IconAction
                                    v-if="category.can_edit"
                                    :icon="Pencil"
                                    :label="t.budget.edit"
                                    @click="emit('edit', category)"
                                />
                                <BudgetCategoryOverflowActions
                                    :category="category"
                                />
                            </div>
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
                                    {{ t.budget.initial_plan }}
                                </p>
                                <p class="font-semibold tabular-nums">
                                    {{
                                        formatMoney(
                                            totals.initial_plan_minor_units,
                                        )
                                    }}
                                </p>
                            </div>
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
                                <p class="font-semibold tabular-nums">
                                    {{
                                        formatMoney(
                                            totals.execution_minor_units,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs">
                                    {{ t.budget.remaining }}
                                </p>
                                <p
                                    class="font-semibold tabular-nums"
                                    :class="
                                        categoryType === 'expense' &&
                                        totals.remaining_minor_units < 0
                                            ? 'text-destructive'
                                            : ''
                                    "
                                >
                                    {{
                                        formatMoney(
                                            totals.remaining_minor_units,
                                        )
                                    }}
                                </p>
                            </div>
                        </template>
                    </div>
                </CardContent>
            </Card>

            <div v-if="filteredArchivedCategories.length > 0" class="space-y-3">
                <div class="flex items-center gap-2">
                    <h4 class="text-sm font-medium">
                        {{ t.budget.archived_section }}
                    </h4>
                    <Badge variant="secondary">
                        {{ filteredArchivedCategories.length }}
                    </Badge>
                </div>
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
                                    <p class="text-muted-foreground text-xs">
                                        {{ t.budget.initial_plan }}
                                    </p>
                                    <p class="font-medium tabular-nums">
                                        {{
                                            formatMoney(
                                                category.initial_plan_minor_units,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground text-xs">
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
                                    <p class="text-muted-foreground text-xs">
                                        {{ t.budget.execution }}
                                    </p>
                                    <p class="font-medium tabular-nums">
                                        {{
                                            formatMoney(
                                                category.execution_minor_units,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground text-xs">
                                        {{ t.budget.remaining }}
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
                            </template>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <IconAction
                                v-if="category.can_edit"
                                :icon="Pencil"
                                :label="t.budget.edit"
                                @click="emit('edit', category)"
                            />
                            <BudgetCategoryOverflowActions
                                :category="category"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </template>
</template>
