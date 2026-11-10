<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import draggable from 'vuedraggable';
import { reorder } from '@/actions/App/Http/Controllers/CategoryGroupController';
import CategoryArchiveSection from '@/components/CategoryArchiveSection.vue';
import CategoryGroupFormDialog from '@/components/CategoryGroupFormDialog.vue';
import CategoryGroupRow from '@/components/CategoryGroupRow.vue';
import CategoryListFormDialog from '@/components/CategoryListFormDialog.vue';
import CategoryListRow from '@/components/CategoryListRow.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type {
    CategoriesPageProps,
    CategoryGroupItem,
    CategoryListItem,
} from '@/types/categories';

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

const sections = computed(() => [
    {
        key: 'income' as const,
        title: t.categories.income_title,
        categories: props.incomeCategories,
        groups: props.incomeGroups,
    },
    {
        key: 'expense' as const,
        title: t.categories.expense_title,
        categories: props.expenseCategories,
        groups: props.expenseGroups,
    },
]);

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
            <div class="flex items-center gap-2">
                <h1 class="page-title">{{ t.categories.title }}</h1>
                <Badge variant="neutral">{{ activeBadge }}</Badge>
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
            <ListSurface
                v-for="section in sections"
                :key="section.key"
                flush
                :data-section="section.key"
            >
                <template #header>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="section-title">{{ section.title }}</h2>
                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="createGroupType = section.key"
                            >
                                <Plus class="size-4" aria-hidden="true" />
                                {{ t.categories.add_group }}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :aria-label="
                                    t.categories.add_label.replace(
                                        ':section',
                                        section.title,
                                    )
                                "
                                @click="openCreateCategoryDialog(section.key)"
                            >
                                {{ t.categories.add }}
                            </Button>
                        </div>
                    </div>
                </template>
                <div
                    v-if="
                        section.categories.length > 0 ||
                        section.groups.length > 0
                    "
                    class="categories-grid categories-header-row"
                >
                    <div class="categories-cell-name">
                        {{ t.categories.name }}
                    </div>
                    <div class="categories-cell-years">
                        {{ t.categories.years }}
                    </div>
                </div>
                <draggable
                    :model-value="trees[section.key]"
                    tag="div"
                    :item-key="itemKey"
                    handle=".drag-handle"
                    :group="topListGroup(section.key)"
                    :disabled="reorderHttp.processing"
                    :animation="150"
                    ghost-class="categories-ghost"
                    @update:model-value="setTree(section.key, $event)"
                    @start="onChangeStart(section.key)"
                    @end="saveTree(section.key)"
                >
                    <template #item="{ element: item, index }">
                        <CategoryListRow
                            v-if="item.kind === 'category'"
                            data-kind="category"
                            :category="item.category"
                            :groups="section.groups"
                            @edit="openEditCategoryDialog(item.category)"
                        />
                        <div v-else data-kind="group">
                            <CategoryGroupRow
                                :group="item.group"
                                :type="section.key"
                                :count="item.categories.length"
                                :can-move-up="index > 0"
                                :can-move-down="
                                    index < trees[section.key].length - 1
                                "
                                @move-up="
                                    moveGroup(section.key, item.group.id, 'up')
                                "
                                @move-down="
                                    moveGroup(
                                        section.key,
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
                                    :group="groupListGroup(section.key)"
                                    :disabled="reorderHttp.processing"
                                    :animation="150"
                                    ghost-class="categories-ghost"
                                    :class="{
                                        'categories-group-items-empty':
                                            item.categories.length === 0,
                                    }"
                                    @update:model-value="
                                        setGroupCategories(item, $event)
                                    "
                                    @start="onChangeStart(section.key)"
                                    @end="saveTree(section.key)"
                                >
                                    <template #item="{ element: category }">
                                        <CategoryListRow
                                            data-kind="category"
                                            nested
                                            :category="category"
                                            :groups="section.groups"
                                            @edit="
                                                openEditCategoryDialog(category)
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
                    v-if="
                        section.categories.length === 0 &&
                        section.groups.length === 0
                    "
                    class="text-muted-foreground px-4 py-3 text-sm"
                >
                    {{ t.categories.section_empty }}
                </p>
            </ListSurface>

            <CategoryArchiveSection
                v-if="archivedCategories.length > 0"
                :categories="archivedCategories"
            />
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
    </div>
</template>
