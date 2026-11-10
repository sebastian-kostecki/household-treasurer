<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import draggable from 'vuedraggable';
import { reorder } from '@/actions/App/Http/Controllers/CategoryListController';
import CategoryArchiveSection from '@/components/CategoryArchiveSection.vue';
import CategoryListFormDialog from '@/components/CategoryListFormDialog.vue';
import CategoryListRow from '@/components/CategoryListRow.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { activeCountForm } from '@/lib/category-list';
import type { CategoriesPageProps, CategoryListItem } from '@/types/categories';

type SectionKey = 'income' | 'expense';

const props = defineProps<CategoriesPageProps>();

const t = useTranslations();

const reorderHttp = useHttp({
    type: 'expense' as SectionKey,
    category_ids: [] as number[],
});

/** The local order of each section while (and after) a drag, restored when saving fails. */
const localCategories = reactive<Record<SectionKey, CategoryListItem[]>>({
    income: [],
    expense: [],
});
let orderBeforeDrag: CategoryListItem[] = [];

watch(
    () => [props.incomeCategories, props.expenseCategories] as const,
    ([income, expense]) => {
        localCategories.income = [...income];
        localCategories.expense = [...expense];
    },
    { immediate: true, deep: true },
);

const dialogOpen = ref(false);
const dialogMode = ref<'create' | 'edit'>('create');
const dialogType = ref<SectionKey>('expense');
const editedCategory = ref<CategoryListItem | null>(null);

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
    },
    {
        key: 'expense' as const,
        title: t.categories.expense_title,
    },
]);

function openCreateDialog(type: SectionKey): void {
    dialogMode.value = 'create';
    dialogType.value = type;
    editedCategory.value = null;
    dialogOpen.value = true;
}

function openEditDialog(category: CategoryListItem): void {
    dialogMode.value = 'edit';
    dialogType.value = category.type;
    editedCategory.value = category;
    dialogOpen.value = true;
}

function onDragStart(key: SectionKey): void {
    orderBeforeDrag = [...localCategories[key]];
}

async function onDragEnd(key: SectionKey): Promise<void> {
    reorderHttp.type = key;
    reorderHttp.category_ids = localCategories[key].map(
        (category) => category.id,
    );

    try {
        await reorderHttp.patch(reorder.url());
    } catch {
        localCategories[key] = orderBeforeDrag;
        toast.error(t.categories.reorder_failed);
    }
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
                <Button @click="openCreateDialog('expense')">
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
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="section-title">{{ section.title }}</h2>
                        <Button
                            variant="outline"
                            size="sm"
                            :aria-label="
                                t.categories.add_label.replace(
                                    ':section',
                                    section.title,
                                )
                            "
                            @click="openCreateDialog(section.key)"
                        >
                            {{ t.categories.add }}
                        </Button>
                    </div>
                </template>
                <div
                    v-if="localCategories[section.key].length > 0"
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
                    v-model="localCategories[section.key]"
                    tag="div"
                    item-key="id"
                    handle=".drag-handle"
                    :disabled="reorderHttp.processing"
                    :animation="150"
                    ghost-class="categories-row-ghost"
                    @start="onDragStart(section.key)"
                    @end="onDragEnd(section.key)"
                >
                    <template #item="{ element: category }">
                        <CategoryListRow
                            :category="category"
                            draggable
                            @edit="openEditDialog(category)"
                        />
                    </template>
                </draggable>
                <p
                    v-if="localCategories[section.key].length === 0"
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

        <CategoryListFormDialog
            v-model:open="dialogOpen"
            :mode="dialogMode"
            :default-type="dialogType"
            :category="editedCategory"
            :category-appearance-options="categoryAppearanceOptions"
        />
    </div>
</template>
