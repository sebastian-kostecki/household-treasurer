<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    AlignHorizontalJustifyCenter,
    Archive,
    ArchiveRestore,
    MoreVertical,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import BudgetCategoryAlignPlanAction from '@/components/BudgetCategoryAlignPlanAction.vue';
import type { BudgetCategoryRow } from '@/components/BudgetCategoryList.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    category: BudgetCategoryRow;
}>();

const emit = defineEmits<{
    edit: [];
}>();

const t = useTranslations();

const alignDialogOpen = ref(false);
const archiveDialogOpen = ref(false);
const restoreDialogOpen = ref(false);
const deleteDialogOpen = ref(false);

const hasActions = computed(
    () =>
        props.category.can_edit ||
        props.category.can_align_current_plan ||
        props.category.can_archive ||
        props.category.can_restore ||
        props.category.can_delete,
);

function openAlignDialog(): void {
    alignDialogOpen.value = true;
}

function openArchiveDialog(): void {
    archiveDialogOpen.value = true;
}

function openRestoreDialog(): void {
    restoreDialogOpen.value = true;
}

function openDeleteDialog(): void {
    deleteDialogOpen.value = true;
}
</script>

<template>
    <div class="flex justify-end">
        <DropdownMenu v-if="hasActions">
            <DropdownMenuTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="t.budget.more_actions"
                >
                    <MoreVertical class="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem
                    v-if="category.can_edit"
                    @select="emit('edit')"
                >
                    <Pencil class="size-4" />
                    {{ t.budget.edit }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="category.can_align_current_plan"
                    @select="openAlignDialog"
                >
                    <AlignHorizontalJustifyCenter class="size-4" />
                    {{ t.budget.align_current_plan }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="category.can_archive"
                    class="text-destructive focus:text-destructive"
                    @select="openArchiveDialog"
                >
                    <Archive class="size-4" />
                    {{ t.budget.archive }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="category.can_restore"
                    @select="openRestoreDialog"
                >
                    <ArchiveRestore class="size-4" />
                    {{ t.budget.restore }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="category.can_delete"
                    class="text-destructive focus:text-destructive"
                    @select="openDeleteDialog"
                >
                    <Trash2 class="size-4" />
                    {{ t.budget.delete }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <BudgetCategoryAlignPlanAction
            v-model:open="alignDialogOpen"
            :category="category"
        />

        <Dialog v-model:open="archiveDialogOpen">
            <DialogContent>
                <Form
                    v-bind="CategoryController.archive.form(category)"
                    v-slot="{ processing }"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {{ t.budget.archive_confirm_title }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ t.budget.archive_confirm_body }}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                {{ t.budget.cancel }}
                            </Button>
                        </DialogClose>
                        <Button type="submit" :disabled="processing">
                            {{ t.budget.archive }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="restoreDialogOpen">
            <DialogContent>
                <Form
                    v-bind="CategoryController.restore.form(category)"
                    v-slot="{ processing }"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {{ t.budget.restore_confirm_title }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ t.budget.restore_confirm_body }}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                {{ t.budget.cancel }}
                            </Button>
                        </DialogClose>
                        <Button type="submit" :disabled="processing">
                            {{ t.budget.restore }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent>
                <Form
                    v-bind="CategoryController.destroy.form(category)"
                    v-slot="{ processing }"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {{ t.budget.delete_category_confirm_title }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ t.budget.delete_category_confirm_body }}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                {{ t.budget.cancel }}
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                        >
                            {{ t.budget.delete }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
