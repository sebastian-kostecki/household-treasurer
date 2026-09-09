<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Pencil, Trash2 } from '@lucide/vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import BudgetCategoryAlignPlanAction from '@/components/BudgetCategoryAlignPlanAction.vue';
import type { BudgetCategoryRow } from '@/components/BudgetCategoryList.vue';
import IconAction from '@/components/IconAction.vue';
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
import { useTranslations } from '@/composables/useTranslations';

defineProps<{
    category: BudgetCategoryRow;
}>();

const emit = defineEmits<{
    edit: [];
}>();

const t = useTranslations();
</script>

<template>
    <div class="flex justify-end gap-1">
        <IconAction
            v-if="category.can_edit"
            :icon="Pencil"
            :label="t.budget.edit"
            @click="emit('edit')"
        />
        <BudgetCategoryAlignPlanAction :category="category" />
        <Dialog v-if="category.can_archive">
            <IconAction
                as-dialog-trigger
                :icon="Archive"
                :label="t.budget.archive"
            />
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
        <Dialog v-if="category.can_restore">
            <IconAction
                as-dialog-trigger
                :icon="ArchiveRestore"
                :label="t.budget.restore"
            />
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
        <Dialog v-if="category.can_delete">
            <IconAction
                as-dialog-trigger
                :icon="Trash2"
                :label="t.budget.delete"
                destructive
            />
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
