<script setup lang="ts">
import { Archive, ArchiveRestore, Pencil, Trash2 } from '@lucide/vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import CategoryListController from '@/actions/App/Http/Controllers/CategoryListController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import IconAction from '@/components/IconAction.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { CategoryListItem } from '@/types/categories';

defineProps<{
    category: CategoryListItem;
    /** An archive row: only restore and delete (when allowed). */
    archived?: boolean;
}>();

const emit = defineEmits<{
    edit: [];
}>();

const t = useTranslations();
</script>

<template>
    <div class="categories-cell-actions">
        <IconAction
            v-if="!archived"
            :icon="Pencil"
            :label="t.categories.edit"
            @click="emit('edit')"
        />
        <ConfirmActionDialog
            v-if="!archived"
            :form="CategoryController.archive.form({ category: category.id })"
            :title="t.categories.archive_confirm_title"
            :description="t.categories.archive_confirm_body"
            :confirm-label="t.categories.archive"
            :cancel-label="t.categories.cancel"
        >
            <template #trigger>
                <IconAction
                    as-dialog-trigger
                    :icon="Archive"
                    :label="t.categories.archive"
                />
            </template>
        </ConfirmActionDialog>
        <ConfirmActionDialog
            v-else
            :form="CategoryController.restore.form({ category: category.id })"
            :title="t.categories.restore_confirm_title"
            :description="t.categories.restore_confirm_body"
            :confirm-label="t.categories.restore"
            :cancel-label="t.categories.cancel"
        >
            <template #trigger>
                <IconAction
                    as-dialog-trigger
                    :icon="ArchiveRestore"
                    :label="t.categories.restore"
                />
            </template>
        </ConfirmActionDialog>
        <ConfirmActionDialog
            v-if="category.can_delete"
            destructive
            :form="CategoryListController.destroy.form(category.id)"
            :title="t.categories.delete_confirm_title"
            :description="t.categories.delete_confirm_body"
            :confirm-label="t.categories.delete"
            :cancel-label="t.categories.cancel"
        >
            <template #trigger>
                <IconAction
                    as-dialog-trigger
                    destructive
                    :icon="Trash2"
                    :label="t.categories.delete"
                />
            </template>
        </ConfirmActionDialog>
    </div>
</template>
