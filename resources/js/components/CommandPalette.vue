<script setup lang="ts">
import {
    CalendarPlus,
    Plus,
    Settings,
    Upload,
    type LucideIcon,
} from '@lucide/vue';
import { computed, nextTick, ref, useId } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslations } from '@/composables/useTranslations';
import {
    buildPaletteActions,
    paletteActionSearchValue,
    paletteCategorySearchValue,
    paletteSearchText,
    type PaletteAction,
    type PaletteCategory,
    type PaletteItem,
} from '@/lib/command-palette-items';
import { formatMoney } from '@/lib/format';
import type { NavItem } from '@/types';

export type PaletteCategories =
    | { status: 'loading' }
    | { status: 'error' }
    | {
          status: 'loaded';
          budgetYear: number | null;
          items: PaletteCategory[];
      };

const props = defineProps<{
    open: boolean;
    views: NavItem[];
    categories: PaletteCategories;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
    select: [item: PaletteItem];
}>();

const t = useTranslations();
const listId = useId();
const command = ref<InstanceType<typeof Command> | null>(null);

const actions = computed(() =>
    buildPaletteActions(props.views, {
        addOperation: t.palette.add_operation,
        importCsv: t.palette.import_csv,
        addBudgetYear: t.palette.add_budget_year,
        settings: t.palette.settings,
    }),
);

const actionIcons: Partial<Record<PaletteItem['kind'], LucideIcon>> = {
    'add-operation': Plus,
    import: Upload,
    'add-budget-year': CalendarPlus,
    settings: Settings,
};

function actionIcon(action: PaletteAction): LucideIcon | undefined {
    if (action.item.kind === 'view') {
        return props.views[action.item.index]?.icon;
    }

    return actionIcons[action.item.kind];
}

function onOpenAutoFocus(): void {
    void nextTick(() => command.value?.highlightFirstItem());
}
</script>

<template>
    <Dialog
        :open="open"
        @update:open="(value: boolean) => emit('update:open', value)"
    >
        <DialogContent
            :show-close-button="false"
            class="command-palette"
            :aria-describedby="undefined"
            @open-auto-focus="onOpenAutoFocus"
        >
            <DialogTitle class="sr-only">
                {{ t.shell.search_and_go }}
            </DialogTitle>

            <Command ref="command" class="command-palette-root">
                <div class="command-palette-search">
                    <CommandInput
                        role="combobox"
                        aria-expanded="true"
                        aria-autocomplete="list"
                        :aria-controls="listId"
                        :aria-label="t.palette.input_label"
                        :placeholder="t.palette.placeholder"
                    />
                    <kbd class="kbd command-palette-esc" aria-hidden="true">
                        Esc
                    </kbd>
                </div>

                <CommandList :id="listId" class="command-palette-list">
                    <CommandEmpty>{{ t.palette.empty }}</CommandEmpty>

                    <CommandGroup :heading="t.palette.group_actions">
                        <CommandItem
                            v-for="action in actions"
                            :key="action.key"
                            :value="action.item"
                            :search-text="
                                paletteSearchText(
                                    paletteActionSearchValue(action),
                                )
                            "
                            class="command-palette-item"
                            @select="emit('select', action.item)"
                        >
                            <component
                                :is="actionIcon(action)"
                                v-if="actionIcon(action)"
                                class="size-4"
                            />
                            <span>{{ paletteActionSearchValue(action) }}</span>
                            <kbd
                                v-if="action.shortcut"
                                class="kbd"
                                aria-hidden="true"
                            >
                                {{ action.shortcut }}
                            </kbd>
                        </CommandItem>
                    </CommandGroup>

                    <CommandGroup :heading="t.palette.group_categories">
                        <div
                            v-if="categories.status === 'loading'"
                            class="command-palette-state"
                            role="status"
                            :aria-label="t.palette.loading"
                        >
                            <Skeleton class="h-control-sm w-full" />
                        </div>
                        <p
                            v-else-if="categories.status === 'error'"
                            class="command-palette-state"
                        >
                            {{ t.palette.error }}
                        </p>
                        <p
                            v-else-if="categories.budgetYear === null"
                            class="command-palette-state"
                        >
                            {{ t.palette.no_budget_year }}
                        </p>
                        <template v-else>
                            <CommandItem
                                v-for="category in categories.items"
                                :key="category.id"
                                :value="{ kind: 'category', category }"
                                :search-text="
                                    paletteSearchText(
                                        paletteCategorySearchValue(category),
                                    )
                                "
                                class="command-palette-item"
                                @select="
                                    emit('select', {
                                        kind: 'category',
                                        category,
                                    })
                                "
                            >
                                <CategoryChip
                                    :name="category.name"
                                    :icon="category.icon"
                                    :color="category.color"
                                    size="sm"
                                    :show-name="false"
                                />
                                <span class="truncate">{{
                                    paletteCategorySearchValue(category)
                                }}</span>
                                <span
                                    v-if="category.type === 'income'"
                                    class="meta"
                                >
                                    {{ t.palette.income_note }}
                                </span>
                                <span class="command-palette-amount amount">
                                    {{
                                        formatMoney(
                                            category.execution_minor_units,
                                        )
                                    }}
                                </span>
                            </CommandItem>
                        </template>
                    </CommandGroup>
                </CommandList>
            </Command>
        </DialogContent>
    </Dialog>
</template>
