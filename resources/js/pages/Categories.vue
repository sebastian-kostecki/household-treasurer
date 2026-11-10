<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import CategoryArchiveSection from '@/components/CategoryArchiveSection.vue';
import CategoryListRow from '@/components/CategoryListRow.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import { activeCountForm } from '@/lib/category-list';
import type { CategoriesPageProps } from '@/types/categories';

const props = defineProps<CategoriesPageProps>();

const t = useTranslations();

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
        key: 'income',
        title: t.categories.income_title,
        categories: props.incomeCategories,
    },
    {
        key: 'expense',
        title: t.categories.expense_title,
        categories: props.expenseCategories,
    },
]);
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
        />

        <template v-else>
            <ListSurface
                v-for="section in sections"
                :key="section.key"
                flush
                :data-section="section.key"
            >
                <template #header>
                    <h2 class="section-title">{{ section.title }}</h2>
                </template>
                <div
                    v-if="section.categories.length > 0"
                    class="categories-grid categories-header-row"
                >
                    <div class="categories-cell-name">
                        {{ t.categories.name }}
                    </div>
                    <div class="categories-cell-years">
                        {{ t.categories.years }}
                    </div>
                </div>
                <CategoryListRow
                    v-for="category in section.categories"
                    :key="category.id"
                    :category="category"
                />
                <p
                    v-if="section.categories.length === 0"
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
    </div>
</template>
