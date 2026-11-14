<script setup lang="ts">
import { GripVertical } from '@lucide/vue';
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import CategoryListRowActions from '@/components/CategoryListRowActions.vue';
import CategoryYearsStrip from '@/components/CategoryYearsStrip.vue';
import { Badge } from '@/components/ui/badge';
import { useShell, useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/lib/format';
import type { CategoryGroupItem, CategoryListItem } from '@/types/categories';

const props = withDefaults(
    defineProps<{
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
        /** False under a phrase or a Rhythm filter: no drag handle, „Zmień grupę…” stays. */
        draggable?: boolean;
        /** The expense list has the Rhythm column; incomes do not. */
        showRhythm?: boolean;
        /** The years of the strip; the default is the user's Budget years (`shell.budgetYears`). */
        axis?: number[];
    }>(),
    {
        draggable: true,
        showRhythm: false,
    },
);

const emit = defineEmits<{
    edit: [];
    merge: [];
}>();

const t = useTranslations();

const shell = useShell();

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
        : t.categories[`rhythm_option_${props.category.rhythm}`],
);

/** The muted second line under the chip: the archive date and/or the description. */
const caption = computed(() =>
    [archivedOn.value, props.category.description]
        .filter((part): part is string => part !== null && part !== '')
        .join(' · '),
);
</script>

<template>
    <div
        class="categories-grid categories-row"
        :class="{
            'categories-grid-no-rhythm': !showRhythm,
            'categories-row-archived': archived,
            'categories-row-nested': nested,
        }"
        data-category-row
    >
        <div class="categories-cell-name">
            <button
                v-if="groups !== undefined && draggable"
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

        <div v-if="showRhythm" class="categories-cell-rhythm">
            <Badge v-if="rhythmLabel !== null" variant="neutral">
                {{ rhythmLabel }}
            </Badge>
        </div>

        <div class="categories-cell-years">
            <CategoryYearsStrip
                :years="category.budget_years"
                :axis="axis ?? shell.budgetYears"
            />
        </div>

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
