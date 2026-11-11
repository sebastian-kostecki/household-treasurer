<script setup lang="ts">
import {
    Archive,
    ArchiveRestore,
    GitMerge,
    MoreVertical,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import CategoryListController from '@/actions/App/Http/Controllers/CategoryListController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/composables/useTranslations';
import type { CategoryListItem } from '@/types/categories';

const props = defineProps<{
    category: CategoryListItem;
    /** An archive row: the menu has restore instead of edit and archive. */
    archived?: boolean;
    /**
     * Whether another active Category of the type exists to merge into. Left
     * out, the menu has no „Scal z inną…” item; false shows it disabled.
     */
    canMerge?: boolean;
}>();

const emit = defineEmits<{
    edit: [];
    merge: [];
}>();

const t = useTranslations();

const archiveOpen = ref(false);
const restoreOpen = ref(false);
const deleteOpen = ref(false);
</script>

<template>
    <div class="categories-cell-actions">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="
                        t.categories.more_actions.replace(
                            ':name',
                            category.name,
                        )
                    "
                >
                    <MoreVertical class="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem v-if="!archived" @select="emit('edit')">
                    <Pencil class="size-4" />
                    {{ t.categories.edit }}
                </DropdownMenuItem>
                <DropdownMenuItem v-if="!archived" @select="archiveOpen = true">
                    <Archive class="size-4" />
                    {{ t.categories.archive }}
                </DropdownMenuItem>
                <DropdownMenuItem v-else @select="restoreOpen = true">
                    <ArchiveRestore class="size-4" />
                    {{ t.categories.restore }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="canMerge !== undefined"
                    :disabled="!canMerge"
                    @select="emit('merge')"
                >
                    <GitMerge class="size-4" />
                    {{ t.categories.merge_action }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="category.can_delete"
                    class="text-destructive focus:text-destructive"
                    @select="deleteOpen = true"
                >
                    <Trash2 class="size-4" />
                    {{ t.categories.delete }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
        <ConfirmActionDialog
            v-if="!archived"
            v-model:open="archiveOpen"
            :form="CategoryController.archive.form({ category: category.id })"
            :title="t.categories.archive_confirm_title"
            :description="t.categories.archive_confirm_body"
            :confirm-label="t.categories.archive"
            :cancel-label="t.categories.cancel"
        />
        <ConfirmActionDialog
            v-else
            v-model:open="restoreOpen"
            :form="CategoryController.restore.form({ category: category.id })"
            :title="t.categories.restore_confirm_title"
            :description="t.categories.restore_confirm_body"
            :confirm-label="t.categories.restore"
            :cancel-label="t.categories.cancel"
        />
        <ConfirmActionDialog
            v-if="category.can_delete"
            v-model:open="deleteOpen"
            destructive
            :form="CategoryListController.destroy.form(category.id)"
            :title="t.categories.delete_confirm_title"
            :description="t.categories.delete_confirm_body"
            :confirm-label="t.categories.delete"
            :cancel-label="t.categories.cancel"
        />
    </div>
</template>
