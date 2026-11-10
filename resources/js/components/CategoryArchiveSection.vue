<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import CategoryListRow from '@/components/CategoryListRow.vue';
import ListSurface from '@/components/ListSurface.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useTranslations } from '@/composables/useTranslations';
import type { CategoryListItem } from '@/types/categories';

const props = defineProps<{
    categories: CategoryListItem[];
    /** Only the kitchen sink opens the section on mount. */
    defaultOpen?: boolean;
}>();

const t = useTranslations();

const open = ref(props.defaultOpen === true);
</script>

<template>
    <ListSurface flush>
        <Collapsible v-model:open="open">
            <CollapsibleTrigger
                class="focus-visible:ring-ring flex w-full items-center justify-between gap-2 px-4 py-3 text-left outline-none focus-visible:ring-2"
            >
                <span class="section-title">
                    {{ t.categories.archive_title }} ({{ categories.length }})
                </span>
                <ChevronDown
                    class="text-muted-foreground size-4 transition-transform"
                    :class="{ 'rotate-180': open }"
                    aria-hidden="true"
                />
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div
                    class="categories-grid categories-grid-no-handle categories-header-row"
                >
                    <div class="categories-cell-name">
                        {{ t.categories.name }}
                    </div>
                    <div class="categories-cell-years">
                        {{ t.categories.years }}
                    </div>
                </div>
                <CategoryListRow
                    v-for="category in categories"
                    :key="category.id"
                    :category="category"
                    archived
                />
            </CollapsibleContent>
        </Collapsible>
    </ListSurface>
</template>
