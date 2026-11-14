<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import draggable from 'vuedraggable';
import { reorder } from '@/actions/App/Http/Controllers/CategoryGroupController';
import CategoryGroupFormDialog from '@/components/CategoryGroupFormDialog.vue';
import CategoryGroupRow from '@/components/CategoryGroupRow.vue';
import CategoryListFormDialog from '@/components/CategoryListFormDialog.vue';
import CategoryListRow from '@/components/CategoryListRow.vue';
import CategoryMergeDialog from '@/components/CategoryMergeDialog.vue';
import CategoryYearsStrip from '@/components/CategoryYearsStrip.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import {
    buildCategoryTree,
    moveTopLevelItem,
    toGroupMember,
    toTreeItem,
    treeToReorderItems,
    type CategoryTreeItem,
    type ReorderItem,
} from '@/lib/category-tree';
import { activeCountForm } from '@/lib/category-list';
import {
    EMPTY_CATEGORY_LIST_FILTER,
    archiveSections,
    filterCategoryTree,
    isFilterActive,
    rhythmCounts,
    tabCounts,
    type CategoryListFilter,
} from '@/lib/category-list-filter';
import { canMergeCategory, mergeTargetOptions } from '@/lib/category-merge';
import { index as categoriesIndex } from '@/routes/categories';
import type {
    CategoriesListTab,
    CategoriesPageProps,
    CategoryGroupItem,
    CategoryListItem,
} from '@/types/categories';
import type { Rhythm } from '@/types/rhythm';

type SectionKey = 'income' | 'expense';
type Tree = CategoryTreeItem<CategoryGroupItem, CategoryListItem>[];

const props = defineProps<CategoriesPageProps>();

const t = useTranslations();

const reorderHttp = useHttp({
    type: 'income' as SectionKey,
    items: [] as ReorderItem[],
});

const trees = ref<Record<SectionKey, Tree>>({ income: [], expense: [] });
const treeBeforeChange = ref<Tree>([]);
const createGroupType = ref<SectionKey | null>(null);

const categoryDialogOpen = ref(false);
const categoryDialogMode = ref<'create' | 'edit'>('create');
const categoryDialogType = ref<SectionKey>('expense');
const editedCategory = ref<CategoryListItem | null>(null);

const mergeSource = ref<CategoryListItem | null>(null);
const mergeDialogOpen = ref(false);

watch(
    () =>
        [
            props.incomeCategories,
            props.incomeGroups,
            props.expenseCategories,
            props.expenseGroups,
        ] as const,
    ([incomeCategories, incomeGroups, expenseCategories, expenseGroups]) => {
        trees.value = {
            income: buildCategoryTree(incomeGroups, incomeCategories),
            expense: buildCategoryTree(expenseGroups, expenseCategories),
        };
    },
    { immediate: true, deep: true },
);

const activeTab = ref<CategoriesListTab>(props.tab);
const query = ref(EMPTY_CATEGORY_LIST_FILTER.query);
const rhythm = ref<Rhythm | 'all'>(EMPTY_CATEGORY_LIST_FILTER.rhythm);

watch(
    () => props.tab,
    (tab) => {
        activeTab.value = tab;
    },
);

const filter = computed<CategoryListFilter>(() => ({
    query: query.value,
    rhythm: rhythm.value,
}));

/** The type whose tree the tab shows; the archive has no tree. */
const typeKey = computed<SectionKey>(() =>
    activeTab.value === 'income' ? 'income' : 'expense',
);

const filterActive = computed(() =>
    isFilterActive(filter.value, typeKey.value),
);

const counts = computed(() => tabCounts(props, filter.value));

const tabOptions = computed(() => [
    {
        value: 'expense' as const,
        label: t.categories.expense_title,
        count: counts.value.expense,
    },
    {
        value: 'income' as const,
        label: t.categories.income_title,
        count: counts.value.income,
    },
    {
        value: 'archive' as const,
        label: t.categories.archive_title,
        count: counts.value.archive,
    },
]);

const tabModel = computed<CategoriesListTab>({
    get: () => activeTab.value,
    set: (value) => changeTab(value),
});

// The tab is the only state in the address; it replaces the history entry, so
// Back from another page returns to the same tab (ADR-0097).
function changeTab(value: CategoriesListTab): void {
    if (value === activeTab.value) {
        return;
    }

    activeTab.value = value;
    router.get(
        categoriesIndex({ query: value === 'expense' ? {} : { tab: value } }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['tab'],
        },
    );
}

const rhythmOptions = computed(() => {
    const byRhythm = rhythmCounts(
        activeTab.value === 'archive'
            ? props.archivedCategories
            : props.expenseCategories,
        query.value,
    );
    const rhythms: Rhythm[] = [
        'fixed_charge',
        'running',
        'even_annual',
        'irregular',
    ];

    return [
        {
            value: 'all' as const,
            label: t.categories.rhythm_all,
            count: rhythms.reduce((sum, key) => sum + byRhythm[key], 0),
        },
        ...rhythms.map((key) => ({
            value: key,
            label: t.categories[`rhythm_option_${key}`],
            count: byRhythm[key],
        })),
    ];
});

function clearFilters(): void {
    query.value = EMPTY_CATEGORY_LIST_FILTER.query;
    rhythm.value = EMPTY_CATEGORY_LIST_FILTER.rhythm;
}

// Under a filter the view shows a computed tree that is never saved: `trees`
// stays the only source of the order the server accepts (ADR-0069).
const displayTree = computed(() =>
    filterActive.value
        ? filterCategoryTree(trees.value[typeKey.value], filter.value)
        : trees.value[typeKey.value],
);

function visibleCountOf(item: {
    categories: unknown[];
    visibleCount?: number;
}): number {
    return item.visibleCount ?? item.categories.length;
}

const archiveBlocks = computed(() => {
    const sections = archiveSections(props.archivedCategories, filter.value);

    return [
        {
            type: 'expense' as const,
            title: t.categories.expense_title,
            categories: sections.expense,
        },
        {
            type: 'income' as const,
            title: t.categories.income_title,
            categories: sections.income,
        },
    ].filter((block) => block.categories.length > 0);
});

const typeGroups = computed(() =>
    typeKey.value === 'income' ? props.incomeGroups : props.expenseGroups,
);

const typeCategories = computed(() =>
    typeKey.value === 'income'
        ? props.incomeCategories
        : props.expenseCategories,
);

const tabTotal = computed(() =>
    activeTab.value === 'archive'
        ? props.archivedCategories.length
        : typeCategories.value.length,
);

const visibleCategoryCount = computed(() =>
    activeTab.value === 'archive'
        ? counts.value.archive
        : displayTree.value.reduce(
              (sum, item) =>
                  sum + (item.kind === 'group' ? item.categories.length : 1),
              0,
          ),
);

const footerText = computed(() => {
    if (filterActive.value) {
        return t.categories.footer_filtered
            .replace(':visible', String(visibleCategoryCount.value))
            .replace(':total', String(tabTotal.value));
    }

    const countText = t.categories[
        `footer_count_${activeCountForm(tabTotal.value)}`
    ].replace(':count', String(tabTotal.value));
    const groups = typeGroups.value.length;

    if (activeTab.value === 'archive' || groups === 0) {
        return countText;
    }

    const groupsKey = groups === 1 ? 'footer_groups_one' : 'footer_groups_many';

    return `${countText} ${t.categories[groupsKey].replace(':groups', String(groups))}`;
});

const showDragHint = computed(
    () =>
        !filterActive.value &&
        activeTab.value !== 'archive' &&
        displayTree.value.length > 0,
);

const noResultsText = computed(() =>
    query.value.trim() !== ''
        ? t.categories.no_results_query.replace(':query', query.value.trim())
        : t.categories.no_results_rhythm,
);

const noResults = computed(
    () =>
        filterActive.value &&
        (activeTab.value === 'archive'
            ? archiveBlocks.value.length === 0
            : displayTree.value.length === 0),
);

const activeCount = computed(
    () => props.incomeCategories.length + props.expenseCategories.length,
);

const hasAnyCategory = computed(
    () => activeCount.value + props.archivedCategories.length > 0,
);

const activeBadge = computed(() =>
    t.categories[`active_badge_${activeCountForm(activeCount.value)}`].replace(
        ':count',
        String(activeCount.value),
    ),
);

const createDialogOpen = computed({
    get: () => createGroupType.value !== null,
    set: (open: boolean) => {
        if (!open) {
            createGroupType.value = null;
        }
    },
});

function openCreateCategoryDialog(type: SectionKey): void {
    categoryDialogMode.value = 'create';
    categoryDialogType.value = type;
    editedCategory.value = null;
    categoryDialogOpen.value = true;
}

function openEditCategoryDialog(category: CategoryListItem): void {
    categoryDialogMode.value = 'edit';
    categoryDialogType.value = category.type;
    editedCategory.value = category;
    categoryDialogOpen.value = true;
}

function openMergeCategoryDialog(category: CategoryListItem): void {
    mergeSource.value = category;
    mergeDialogOpen.value = true;
}

const activeCategories = computed(() => [
    ...props.incomeCategories,
    ...props.expenseCategories,
]);

function canMerge(category: CategoryListItem): boolean {
    return canMergeCategory(category, activeCategories.value);
}

const mergeTargets = computed(() => {
    const source = mergeSource.value;

    if (source === null) {
        return [];
    }

    return mergeTargetOptions(
        source,
        activeCategories.value,
        source.type === 'income' ? props.incomeGroups : props.expenseGroups,
    );
});

function itemKey(item: Tree[number]): string {
    return item.kind === 'group'
        ? `group:${item.group.id}`
        : `category:${item.category.id}`;
}

function topListGroup(key: SectionKey) {
    return { name: `category-list-${key}` };
}

// A group never enters a group: only a Category may be put into a group's list.
function groupListGroup(key: SectionKey) {
    return {
        name: `category-list-${key}`,
        put: (_to: unknown, _from: unknown, dragEl: HTMLElement): boolean =>
            dragEl.dataset.kind === 'category',
    };
}

function snapshotOf(key: SectionKey): Tree {
    return trees.value[key].map((item) =>
        item.kind === 'group'
            ? { ...item, categories: [...item.categories] }
            : item,
    );
}

// Dragging between the top list and a group list moves the raw element, so the
// elements are put back into the shape of the list they now sit in.
function setTree(
    key: SectionKey,
    tree: Array<Tree[number] | CategoryListItem>,
): void {
    trees.value[key] = tree.map((element) =>
        toTreeItem<CategoryGroupItem, CategoryListItem>(element),
    );
}

function setGroupCategories(
    item: Tree[number],
    categories: Array<Tree[number] | CategoryListItem>,
): void {
    if (item.kind === 'group') {
        item.categories = categories.map((element) =>
            toGroupMember<CategoryGroupItem, CategoryListItem>(element),
        );
    }
}

function onChangeStart(key: SectionKey): void {
    treeBeforeChange.value = snapshotOf(key);
}

// Saves the visible tree of the type; the server answers with the new order and
// membership, so the page reloads its props afterwards (also after a rejection,
// so the view shows the order the server holds).
async function saveTree(key: SectionKey): Promise<void> {
    reorderHttp.type = key;
    reorderHttp.items = treeToReorderItems(trees.value[key]);

    try {
        // A 422 (the tree no longer matches the list, e.g. changed in another
        // tab) does not throw: useHttp passes it to onError and resolves.
        await reorderHttp.patch(reorder.url(), {
            onError: (errors) => {
                console.error(
                    'The server rejected the category order.',
                    errors,
                );
                trees.value[key] = treeBeforeChange.value;
                toast.error(t.categories.group_reorder_failed);
            },
        });
    } catch (error) {
        console.error('Saving the category order failed.', error);
        trees.value[key] = treeBeforeChange.value;
        toast.error(t.categories.group_reorder_failed);

        return;
    }

    router.reload({
        only: [
            'incomeCategories',
            'expenseCategories',
            'archivedCategories',
            'incomeGroups',
            'expenseGroups',
        ],
    });
}

function moveGroup(
    key: SectionKey,
    groupId: number,
    direction: 'up' | 'down',
): void {
    // Like dragging, a move waits for the previous save: a second one would
    // overwrite the order restored when that save fails.
    if (reorderHttp.processing) {
        return;
    }

    treeBeforeChange.value = snapshotOf(key);
    trees.value[key] = moveTopLevelItem(trees.value[key], groupId, direction);
    void saveTree(key);
}
</script>

<template>
    <Head :title="t.categories.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="page-title">{{ t.categories.title }}</h1>
                <Badge variant="neutral">{{ activeBadge }}</Badge>
                <div
                    v-if="hasAnyCategory"
                    class="ml-auto flex items-center gap-2"
                >
                    <Button
                        v-if="activeTab !== 'archive'"
                        type="button"
                        variant="outline"
                        @click="createGroupType = typeKey"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        {{ t.categories.add_group }}
                    </Button>
                    <Button @click="openCreateCategoryDialog(typeKey)">
                        {{ t.categories.add }}
                    </Button>
                </div>
            </div>
            <p class="text-muted-foreground text-sm">
                {{ t.categories.list_description }}
            </p>
        </div>

        <IndexEmptyState
            v-if="!hasAnyCategory"
            :title="t.categories.empty_title"
            :description="t.categories.empty_description"
        >
            <template #actions>
                <Button @click="openCreateCategoryDialog('expense')">
                    {{ t.categories.add }}
                </Button>
            </template>
        </IndexEmptyState>

        <template v-else>
            <div class="categories-toolbar">
                <div class="max-w-full overflow-x-auto">
                    <SegmentedControl
                        v-model="tabModel"
                        tone="neutral"
                        class="segmented-control-tabs"
                        :ariaLabel="t.categories.tabs_label"
                        :options="tabOptions"
                    />
                </div>
                <CategoryYearsStrip :years="[]" :axis="[]" legend />
            </div>

            <ListSurface flush :data-tab="activeTab">
                <template #header>
                    <div class="categories-filters" role="search">
                        <div class="relative min-w-56 flex-1">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                v-model="query"
                                type="search"
                                class="pr-3 pl-9"
                                :placeholder="t.categories.search_placeholder"
                                :aria-label="t.categories.search_label"
                            />
                        </div>
                        <div
                            v-if="activeTab !== 'income'"
                            class="max-w-full overflow-x-auto"
                        >
                            <SegmentedControl
                                v-model="rhythm"
                                tone="neutral"
                                :ariaLabel="t.categories.rhythm_filter_label"
                                :options="rhythmOptions"
                            />
                        </div>
                    </div>
                </template>

                <template v-if="activeTab !== 'archive'">
                    <div
                        v-if="displayTree.length > 0"
                        class="categories-grid categories-header-row"
                        :class="{
                            'categories-grid-no-rhythm': typeKey === 'income',
                        }"
                    >
                        <div class="categories-cell-name">
                            {{ t.categories.name }}
                        </div>
                        <div
                            v-if="typeKey === 'expense'"
                            class="categories-cell-rhythm"
                        >
                            {{ t.categories.rhythm }}
                        </div>
                        <div class="categories-cell-years">
                            {{ t.categories.years }}
                        </div>
                    </div>
                    <draggable
                        :model-value="displayTree"
                        tag="div"
                        :item-key="itemKey"
                        handle=".drag-handle"
                        :group="topListGroup(typeKey)"
                        :disabled="reorderHttp.processing || filterActive"
                        :animation="150"
                        ghost-class="categories-ghost"
                        @update:model-value="setTree(typeKey, $event)"
                        @start="onChangeStart(typeKey)"
                        @end="saveTree(typeKey)"
                    >
                        <template #item="{ element: item, index }">
                            <CategoryListRow
                                v-if="item.kind === 'category'"
                                data-kind="category"
                                :category="item.category"
                                :groups="typeGroups"
                                :draggable="!filterActive"
                                :show-rhythm="typeKey === 'expense'"
                                :reorder-saving="reorderHttp.processing"
                                :can-merge="canMerge(item.category)"
                                @edit="openEditCategoryDialog(item.category)"
                                @merge="openMergeCategoryDialog(item.category)"
                            />
                            <div v-else data-kind="group">
                                <CategoryGroupRow
                                    :group="item.group"
                                    :type="typeKey"
                                    :count="
                                        item.count ?? item.categories.length
                                    "
                                    :visible-count="visibleCountOf(item)"
                                    :movable="!filterActive"
                                    :can-move-up="index > 0"
                                    :can-move-down="
                                        index < displayTree.length - 1
                                    "
                                    @move-up="
                                        moveGroup(typeKey, item.group.id, 'up')
                                    "
                                    @move-down="
                                        moveGroup(
                                            typeKey,
                                            item.group.id,
                                            'down',
                                        )
                                    "
                                />
                                <div class="categories-group-body">
                                    <draggable
                                        :model-value="item.categories"
                                        tag="div"
                                        item-key="id"
                                        handle=".drag-handle"
                                        :group="groupListGroup(typeKey)"
                                        :disabled="
                                            reorderHttp.processing ||
                                            filterActive
                                        "
                                        :animation="150"
                                        ghost-class="categories-ghost"
                                        :class="{
                                            'categories-group-items-empty':
                                                item.categories.length === 0,
                                        }"
                                        @update:model-value="
                                            setGroupCategories(item, $event)
                                        "
                                        @start="onChangeStart(typeKey)"
                                        @end="saveTree(typeKey)"
                                    >
                                        <template #item="{ element: category }">
                                            <CategoryListRow
                                                data-kind="category"
                                                nested
                                                :category="category"
                                                :groups="typeGroups"
                                                :draggable="!filterActive"
                                                :show-rhythm="
                                                    typeKey === 'expense'
                                                "
                                                :reorder-saving="
                                                    reorderHttp.processing
                                                "
                                                :can-merge="canMerge(category)"
                                                @edit="
                                                    openEditCategoryDialog(
                                                        category,
                                                    )
                                                "
                                                @merge="
                                                    openMergeCategoryDialog(
                                                        category,
                                                    )
                                                "
                                            />
                                        </template>
                                    </draggable>
                                    <p
                                        v-if="item.categories.length === 0"
                                        class="categories-group-hint"
                                    >
                                        {{ t.categories.group_empty_hint }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </draggable>
                    <p
                        v-if="displayTree.length === 0 && !filterActive"
                        class="text-muted-foreground px-4 py-3 text-sm"
                    >
                        {{ t.categories.section_empty }}
                    </p>
                </template>

                <template v-else>
                    <section
                        v-for="block in archiveBlocks"
                        :key="block.type"
                        :data-archive-type="block.type"
                    >
                        <h2
                            class="section-title border-border-soft border-b px-4 py-2"
                        >
                            {{ block.title }}
                        </h2>
                        <div
                            class="categories-grid categories-header-row"
                            :class="{
                                'categories-grid-no-rhythm':
                                    block.type === 'income',
                            }"
                        >
                            <div class="categories-cell-name">
                                {{ t.categories.name }}
                            </div>
                            <div
                                v-if="block.type === 'expense'"
                                class="categories-cell-rhythm"
                            >
                                {{ t.categories.rhythm }}
                            </div>
                            <div class="categories-cell-years">
                                {{ t.categories.years }}
                            </div>
                        </div>
                        <CategoryListRow
                            v-for="category in block.categories"
                            :key="category.id"
                            :category="category"
                            archived
                            :show-rhythm="block.type === 'expense'"
                            :can-merge="canMerge(category)"
                            @merge="openMergeCategoryDialog(category)"
                        />
                    </section>
                    <p
                        v-if="archiveBlocks.length === 0 && !filterActive"
                        class="text-muted-foreground px-4 py-3 text-sm"
                    >
                        {{ t.categories.archive_empty }}
                    </p>
                </template>

                <div
                    v-if="noResults"
                    class="flex flex-col items-start gap-3 px-4 py-6"
                >
                    <p class="text-muted-foreground text-sm">
                        {{ noResultsText }}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="clearFilters"
                    >
                        {{ t.categories.clear_filters }}
                    </Button>
                </div>

                <p class="categories-footer">
                    {{ footerText }}
                    <template v-if="showDragHint">
                        · {{ t.categories.drag_hint }}
                    </template>
                </p>
            </ListSurface>
        </template>

        <CategoryGroupFormDialog
            v-model:open="createDialogOpen"
            mode="create"
            :type="createGroupType ?? 'expense'"
        />

        <CategoryListFormDialog
            v-model:open="categoryDialogOpen"
            :mode="categoryDialogMode"
            :default-type="categoryDialogType"
            :category="editedCategory"
            :category-appearance-options="categoryAppearanceOptions"
            :rhythm-change-month="rhythmChangeMonth"
        />

        <CategoryMergeDialog
            v-model:open="mergeDialogOpen"
            :source="mergeSource"
            :targets="mergeTargets"
        />
    </div>
</template>
