<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    FolderOpen,
    GripVertical,
    MoreVertical,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CategoryGroupController from '@/actions/App/Http/Controllers/CategoryGroupController';
import CategoryGroupFormDialog from '@/components/CategoryGroupFormDialog.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/composables/useTranslations';
import { activeCountForm, groupDeleteDescription } from '@/lib/category-list';
import type { CategoryGroupItem } from '@/types/categories';

const props = defineProps<{
    group: CategoryGroupItem;
    type: 'income' | 'expense';
    /** How many Categories stand in the group. */
    count: number;
    canMoveUp: boolean;
    canMoveDown: boolean;
}>();

const emit = defineEmits<{
    moveUp: [];
    moveDown: [];
}>();

const t = useTranslations();

const renameOpen = ref(false);
const deleteOpen = ref(false);

const countLabel = computed(() =>
    t.categories[`group_count_${activeCountForm(props.count)}`].replace(
        ':count',
        String(props.count),
    ),
);

const deleteDescription = computed(() =>
    groupDeleteDescription(props.count, props.group.archived_count, {
        group_delete_description: t.categories.group_delete_description,
        group_delete_orphans_zero: t.categories.group_delete_orphans_zero,
        group_delete_orphans_one: t.categories.group_delete_orphans_one,
        group_delete_orphans_few: t.categories.group_delete_orphans_few,
        group_delete_orphans_many: t.categories.group_delete_orphans_many,
        group_delete_archived: t.categories.group_delete_archived,
    }),
);
</script>

<template>
    <div class="categories-group-row" data-group-row>
        <button
            type="button"
            class="categories-handle drag-handle"
            tabindex="-1"
            :aria-label="t.categories.group_drag_handle"
        >
            <GripVertical class="size-4" aria-hidden="true" />
        </button>
        <FolderOpen
            class="text-muted-foreground size-4 shrink-0"
            aria-hidden="true"
        />
        <h3 class="categories-group-name">{{ group.name }}</h3>
        <span class="categories-group-count">{{ countLabel }}</span>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="
                        t.categories.group_more_actions.replace(
                            ':name',
                            group.name,
                        )
                    "
                >
                    <MoreVertical class="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem @select="renameOpen = true">
                    <Pencil class="size-4" />
                    {{ t.categories.group_rename }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    :disabled="!canMoveUp"
                    @select="emit('moveUp')"
                >
                    <ArrowUp class="size-4" />
                    {{ t.categories.group_move_up }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    :disabled="!canMoveDown"
                    @select="emit('moveDown')"
                >
                    <ArrowDown class="size-4" />
                    {{ t.categories.group_move_down }}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    class="text-destructive focus:text-destructive"
                    @select="deleteOpen = true"
                >
                    <Trash2 class="size-4" />
                    {{ t.categories.group_delete }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <CategoryGroupFormDialog
            v-model:open="renameOpen"
            mode="rename"
            :type="type"
            :group="group"
        />

        <ConfirmActionDialog
            v-model:open="deleteOpen"
            :form="
                CategoryGroupController.destroy.form({
                    categoryGroup: group.id,
                })
            "
            destructive
            :title="t.categories.group_delete_title"
            :description="deleteDescription"
            :confirm-label="t.categories.group_delete"
            :cancel-label="t.categories.group_cancel"
        />
    </div>
</template>
