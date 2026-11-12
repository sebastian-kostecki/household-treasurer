<script setup lang="ts">
import { GripVertical } from '@lucide/vue';
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import CategoryListRowActions from '@/components/CategoryListRowActions.vue';
import { useTranslations } from '@/composables/useTranslations';
import { categoryYearsLabel } from '@/lib/category-list';
import { formatDate } from '@/lib/format';
import type { CategoryGroupItem, CategoryListItem } from '@/types/categories';

const props = defineProps<{
    category: CategoryListItem;
    /** An archive row: muted, with the archive date under the name. */
    archived?: boolean;
    /** The groups of the Category's type; with it an active row gets a drag handle and „Zmień grupę…” in the menu. */
    groups?: CategoryGroupItem[];
    /** The row stands under a group: indented. */
    nested?: boolean;
    /** Whether another active Category of the type exists to merge into. */
    canMerge?: boolean;
    /** The order is being saved: „Zmień grupę…” is disabled meanwhile. */
    reorderSaving?: boolean;
}>();

const emit = defineEmits<{
    edit: [];
    merge: [];
}>();

const t = useTranslations();

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

const rhythmLabel = computed(() =>
    props.category.rhythm === null
        ? null
        : t.categories[`rhythm_${props.category.rhythm}`],
);

/** The muted second line under the chip: the Rhythm, the archive date and/or the description. */
const caption = computed(() =>
    [rhythmLabel.value, archivedOn.value, props.category.description]
        .filter((part): part is string => part !== null && part !== '')
        .join(' · '),
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
                :aria-label="t.categories.drag_handle"
            >
                <GripVertical class="size-4" aria-hidden="true" />
            </button>
            <CategoryChip
                :name="category.name"
                :icon="category.icon"
                :color="category.color"
                :size="caption === '' ? 'md' : 'lg'"
            >
                <template v-if="caption !== ''" #subtitle>
                    <span class="categories-description" :title="caption">
                        {{ caption }}
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

        <CategoryListRowActions
            :category="category"
            :archived="archived"
            :can-merge="canMerge"
            :groups="groups"
            :reorder-saving="reorderSaving"
            @edit="emit('edit')"
            @merge="emit('merge')"
        />
    </div>
</template>
