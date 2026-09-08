<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Pencil, Trash2 } from '@lucide/vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import type { CategoryFormCategory } from '@/components/CategoryFormDialog.vue';
import IconAction from '@/components/IconAction.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { formatMoney } from '@/lib/format';

export type BudgetCategoryRow = CategoryFormCategory & {
    initial_plan_grosze: number;
    current_plan_grosze: number;
    execution_grosze: number;
    can_edit: boolean;
    can_delete: boolean;
    can_change_type: boolean;
    is_archived: boolean;
    can_archive: boolean;
    can_restore: boolean;
};

defineProps<{
    title: string;
    categories: BudgetCategoryRow[];
    emptyDescription: string;
    addLabel: string;
    highlightInitialPlan?: boolean;
}>();

const emit = defineEmits<{
    add: [];
    edit: [category: BudgetCategoryRow];
}>();

const t = useTranslations();
</script>

<template>
    <IndexEmptyState
        v-if="categories.length === 0"
        :title="title"
        :description="emptyDescription"
    >
        <template #actions>
            <Button variant="outline" @click="emit('add')">
                {{ addLabel }}
            </Button>
        </template>
    </IndexEmptyState>

    <template v-else>
        <ListSurface class="hidden md:block" :title="title">
            <div class="overflow-x-auto">
                <table class="list-surface-table">
                    <thead>
                        <tr>
                            <th>{{ t.budget.category }}</th>
                            <th class="text-right">
                                {{ t.budget.initial_plan }}
                            </th>
                            <th class="text-right">
                                {{ t.budget.current_plan }}
                            </th>
                            <th class="text-right">
                                {{ t.budget.execution }}
                            </th>
                            <th class="text-right">
                                {{ t.budget.actions }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="category in categories"
                            :key="category.id"
                            :class="
                                category.is_archived
                                    ? 'text-muted-foreground'
                                    : ''
                            "
                        >
                            <td>
                                <div class="flex items-center gap-2">
                                    <span>{{ category.name }}</span>
                                    <Badge
                                        v-if="category.is_archived"
                                        variant="secondary"
                                    >
                                        {{ t.budget.archived_badge }}
                                    </Badge>
                                </div>
                            </td>
                            <td
                                class="tabular-amount"
                                :class="
                                    highlightInitialPlan ? 'text-success' : ''
                                "
                            >
                                {{
                                    formatMoney(category.initial_plan_grosze)
                                }}
                            </td>
                            <td class="tabular-amount">
                                {{
                                    formatMoney(category.current_plan_grosze)
                                }}
                            </td>
                            <td class="tabular-amount">
                                {{ formatMoney(category.execution_grosze) }}
                            </td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    <IconAction
                                        v-if="category.can_edit"
                                        :icon="Pencil"
                                        :label="t.budget.edit"
                                        @click="emit('edit', category)"
                                    />
                                    <Dialog v-if="category.can_delete">
                                        <IconAction
                                            as-dialog-trigger
                                            :icon="Trash2"
                                            :label="t.budget.delete"
                                            destructive
                                        />
                                        <DialogContent>
                                            <Form
                                                v-bind="
                                                    CategoryController.destroy.form(
                                                        category,
                                                    )
                                                "
                                                v-slot="{ processing }"
                                            >
                                                <DialogHeader>
                                                    <DialogTitle>
                                                        {{
                                                            t.budget.delete_category_confirm_title
                                                        }}
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        {{
                                                            t.budget.delete_category_confirm_body
                                                        }}
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <DialogFooter class="gap-2">
                                                    <DialogClose as-child>
                                                        <Button
                                                            type="button"
                                                            variant="secondary"
                                                        >
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
                                    <Dialog v-if="category.can_archive">
                                        <IconAction
                                            as-dialog-trigger
                                            :icon="Archive"
                                            :label="t.budget.archive"
                                        />
                                        <DialogContent>
                                            <Form
                                                v-bind="
                                                    CategoryController.archive.form(
                                                        category,
                                                    )
                                                "
                                                v-slot="{ processing }"
                                            >
                                                <DialogHeader>
                                                    <DialogTitle>
                                                        {{
                                                            t.budget.archive_confirm_title
                                                        }}
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        {{
                                                            t.budget.archive_confirm_body
                                                        }}
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <DialogFooter class="gap-2">
                                                    <DialogClose as-child>
                                                        <Button
                                                            type="button"
                                                            variant="secondary"
                                                        >
                                                            {{ t.budget.cancel }}
                                                        </Button>
                                                    </DialogClose>
                                                    <Button
                                                        type="submit"
                                                        :disabled="processing"
                                                    >
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
                                                v-bind="
                                                    CategoryController.restore.form(
                                                        category,
                                                    )
                                                "
                                                v-slot="{ processing }"
                                            >
                                                <DialogHeader>
                                                    <DialogTitle>
                                                        {{
                                                            t.budget.restore_confirm_title
                                                        }}
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        {{
                                                            t.budget.restore_confirm_body
                                                        }}
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <DialogFooter class="gap-2">
                                                    <DialogClose as-child>
                                                        <Button
                                                            type="button"
                                                            variant="secondary"
                                                        >
                                                            {{ t.budget.cancel }}
                                                        </Button>
                                                    </DialogClose>
                                                    <Button
                                                        type="submit"
                                                        :disabled="processing"
                                                    >
                                                        {{ t.budget.restore }}
                                                    </Button>
                                                </DialogFooter>
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </ListSurface>

        <div class="space-y-3 md:hidden">
            <Card
                v-for="category in categories"
                :key="category.id"
                :class="category.is_archived ? 'opacity-60' : ''"
            >
                <CardHeader class="gap-3 pb-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <CardTitle class="text-base">
                            {{ category.name }}
                        </CardTitle>
                        <Badge
                            v-if="category.is_archived"
                            variant="secondary"
                        >
                            {{ t.budget.archived_badge }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="grid grid-cols-3 gap-3 text-sm">
                        <div>
                            <p class="text-muted-foreground text-xs">
                                {{ t.budget.initial_plan }}
                            </p>
                            <p
                                class="font-medium tabular-nums"
                                :class="
                                    highlightInitialPlan ? 'text-success' : ''
                                "
                            >
                                {{
                                    formatMoney(category.initial_plan_grosze)
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">
                                {{ t.budget.current_plan }}
                            </p>
                            <p class="font-medium tabular-nums">
                                {{
                                    formatMoney(category.current_plan_grosze)
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">
                                {{ t.budget.execution }}
                            </p>
                            <p class="font-medium tabular-nums">
                                {{ formatMoney(category.execution_grosze) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1">
                    <IconAction
                        v-if="category.can_edit"
                        :icon="Pencil"
                        :label="t.budget.edit"
                        @click="emit('edit', category)"
                    />
                    <Dialog v-if="category.can_delete">
                        <IconAction
                            as-dialog-trigger
                            :icon="Trash2"
                            :label="t.budget.delete"
                            destructive
                        />
                        <DialogContent>
                            <Form
                                v-bind="
                                    CategoryController.destroy.form(category)
                                "
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{
                                            t.budget.delete_category_confirm_title
                                        }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{
                                            t.budget.delete_category_confirm_body
                                        }}
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
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
                    <Dialog v-if="category.can_archive">
                        <IconAction
                            as-dialog-trigger
                            :icon="Archive"
                            :label="t.budget.archive"
                        />
                        <DialogContent>
                            <Form
                                v-bind="
                                    CategoryController.archive.form(category)
                                "
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
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            {{ t.budget.cancel }}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                    >
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
                                v-bind="
                                    CategoryController.restore.form(category)
                                "
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
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            {{ t.budget.cancel }}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                    >
                                        {{ t.budget.restore }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                    </div>
                </CardContent>
            </Card>
        </div>
    </template>
</template>
