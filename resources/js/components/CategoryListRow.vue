<script setup lang="ts">
import { GripVertical } from '@lucide/vue';
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import CategoryGroupPicker from '@/components/CategoryGroupPicker.vue';
import TextLink from '@/components/TextLink.vue';
import { useTranslations } from '@/composables/useTranslations';
import {
    categoryAllYearsOperationsQuery,
    categoryYearsLabel,
} from '@/lib/category-list';
import { formatDate } from '@/lib/format';
import { operations } from '@/routes';
import type { CategoryGroupItem, CategoryListItem } from '@/types/categories';

const props = defineProps<{
    category: CategoryListItem;
    /** An archive row: muted, with the archive date under the name. */
    archived?: boolean;
    /** The groups of the Category's type; with it an active row gets "Grupa…" and a drag handle. */
    groups?: CategoryGroupItem[];
    /** The row stands under a group: indented. */
    nested?: boolean;
    /** Only the kitchen sink opens the "Grupa…" menu on mount. */
    pickerOpen?: boolean;
}>();

const t = useTranslations();

const href = computed(() =>
    operations({ query: categoryAllYearsOperationsQuery(props.category) }),
);

const yearsLabel = computed(() =>
    categoryYearsLabel(props.category.budget_years),
);

const archivedOn = computed(() =>
    props.category.archived_at === null
        ? null
        : t.categories.archived_on.replace(
              ':date',
              formatDate(props.category.archived_at),
          ),
);
</script>

<template>
    <div
        class="categories-grid categories-row"
        :class="{
            'categories-row-archived': archived,
            'categories-row-nested': nested,
        }"
        data-category-row
    >
        <div class="categories-cell-name">
            <button
                v-if="groups !== undefined"
                type="button"
                class="categories-handle drag-handle"
                tabindex="-1"
                :aria-label="t.categories.group_drag_handle"
            >
                <GripVertical class="size-4" aria-hidden="true" />
            </button>
            <CategoryChip
                :name="category.name"
                :icon="category.icon"
                :color="category.color"
                :size="archivedOn === null ? 'md' : 'lg'"
            >
                <template v-if="archivedOn !== null" #subtitle>
                    <span class="meta text-muted-foreground">
                        {{ archivedOn }}
                    </span>
                </template>
            </CategoryChip>
        </div>

        <p class="categories-cell-years">
            <template v-if="yearsLabel !== ''">{{ yearsLabel }}</template>
            <template v-else>
                <span aria-hidden="true">{{ t.categories.no_years }}</span>
                <span class="sr-only">{{ t.categories.no_years_label }}</span>
            </template>
        </p>

        <div class="categories-cell-link">
            <TextLink
                :href="href"
                :aria-label="
                    t.categories.operations_link_label.replace(
                        ':name',
                        category.name,
                    )
                "
            >
                {{ t.categories.operations_link }}
            </TextLink>
            <CategoryGroupPicker
                v-if="groups !== undefined"
                :category="category"
                :groups="groups"
                :default-open="pickerOpen"
            />
        </div>
    </div>
</template>
