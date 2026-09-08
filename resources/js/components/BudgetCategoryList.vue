<script setup lang="ts">
import { Form, useHttp } from '@inertiajs/vue3';
import {
    Archive,
    ArchiveRestore,
    GripVertical,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import { reorder } from '@/actions/App/Http/Controllers/CategoryController';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import CategoryChip from '@/components/CategoryChip.vue';
import IconAction from '@/components/IconAction.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
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

export type BudgetCategoryRow = {
    id: number;
    name: string;
    icon: string;
    color: string;
    type: 'income' | 'expense';
    planned_amount_grosze: number;
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

const props = defineProps<{
    title: string;
    categories: BudgetCategoryRow[];
    budgetId: number;
    categoryType: 'income' | 'expense';
    canReorder: boolean;
    emptyDescription: string;
    addLabel: string;
    highlightInitialPlan?: boolean;
}>();

const emit = defineEmits<{
    add: [];
    edit: [category: BudgetCategoryRow];
}>();

const t = useTranslations();
const reorderHttp = useHttp({
    type: props.categoryType,
    category_ids: [] as number[],
});

const localCategories = ref<BudgetCategoryRow[]>([]);
const orderBeforeDrag = ref<BudgetCategoryRow[]>([]);

watch(
    () => props.categories,
    (categories) => {
        localCategories.value = [...categories];
    },
    { immediate: true, deep: true },
);

const archivedCategories = computed(() =>
    localCategories.value.filter((category) => category.is_archived),
);

const activeCategories = computed({
    get: () =>
        localCategories.value.filter((category) => !category.is_archived),
    set: (reorderedActiveCategories) => {
        localCategories.value = [
            ...reorderedActiveCategories,
            ...archivedCategories.value,
        ];
    },
});

function onDragStart(): void {
    orderBeforeDrag.value = [...localCategories.value];
}

async function onDragEnd(): Promise<void> {
    if (!props.canReorder) {
        return;
    }

    reorderHttp.type = props.categoryType;
    reorderHttp.category_ids = activeCategories.value.map(
        (category) => category.id,
    );

    try {
        await reorderHttp.patch(reorder.url(props.budgetId));
    } catch {
        localCategories.value = [...orderBeforeDrag.value];
    }
}
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
                            <th v-if="canReorder" class="w-10" />
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
                    <draggable
                        v-model="activeCategories"
                        tag="tbody"
                        item-key="id"
                        handle=".drag-handle"
                        :disabled="!canReorder"
                        :animation="150"
                        ghost-class="opacity-50"
                        @start="onDragStart"
                        @end="onDragEnd"
                    >
                        <template #item="{ element: category }">
                            <tr>
                                <td v-if="canReorder" class="w-10">
                                    <button
                                        type="button"
                                        class="drag-handle flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-foreground/[0.04] hover:text-foreground active:cursor-grabbing"
                                        :aria-label="t.budget.drag_handle"
                                    >
                                        <GripVertical class="size-4" />
                                    </button>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <CategoryChip
                                            :name="category.name"
                                            :icon="category.icon"
                                            :color="category.color"
                                        />
                                    </div>
                                </td>
                                <td
                                    class="tabular-amount"
                                    :class="
                                        highlightInitialPlan
                                            ? 'text-success'
                                            : ''
                                    "
                                >
                                    {{
                                        formatMoney(
                                            category.initial_plan_grosze,
                                        )
                                    }}
                                </td>
                                <td class="tabular-amount">
                                    {{
                                        formatMoney(
                                            category.current_plan_grosze,
                                        )
                                    }}
                                </td>
                                <td class="tabular-amount">
                                    {{
                                        formatMoney(category.execution_grosze)
                                    }}
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
                                                                {{
                                                                    t.budget.cancel
                                                                }}
                                                            </Button>
                                                        </DialogClose>
                                                        <Button
                                                            type="submit"
                                                            variant="destructive"
                                                            :disabled="
                                                                processing
                                                            "
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
                                                                {{
                                                                    t.budget.cancel
                                                                }}
                                                            </Button>
                                                        </DialogClose>
                                                        <Button
                                                            type="submit"
                                                            :disabled="
                                                                processing
                                                            "
                                                        >
                                                            {{
                                                                t.budget.archive
                                                            }}
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
                                                                {{
                                                                    t.budget.cancel
                                                                }}
                                                            </Button>
                                                        </DialogClose>
                                                        <Button
                                                            type="submit"
                                                            :disabled="
                                                                processing
                                                            "
                                                        >
                                                            {{
                                                                t.budget.restore
                                                            }}
                                                        </Button>
                                                    </DialogFooter>
                                                </Form>
                                            </DialogContent>
                                        </Dialog>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </draggable>
                    <tbody v-if="archivedCategories.length > 0">
                        <tr
                            v-for="category in archivedCategories"
                            :key="category.id"
                            class="text-muted-foreground"
                        >
                            <td v-if="canReorder" class="w-10" />
                            <td>
                                <div class="flex items-center gap-2">
                                    <CategoryChip
                                        :name="category.name"
                                        :icon="category.icon"
                                        :color="category.color"
                                    />
                                    <Badge variant="secondary">
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

        <div class="md:hidden">
            <draggable
                v-model="activeCategories"
                item-key="id"
                handle=".drag-handle"
                :disabled="!canReorder"
                :animation="150"
                ghost-class="opacity-50"
                class="space-y-3"
                @start="onDragStart"
                @end="onDragEnd"
            >
                <template #item="{ element: category }">
                    <Card>
                        <CardHeader class="gap-3 pb-3">
                            <div class="flex items-start gap-2">
                                <button
                                    v-if="canReorder"
                                    type="button"
                                    class="drag-handle mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-foreground/[0.04] hover:text-foreground active:cursor-grabbing"
                                    :aria-label="t.budget.drag_handle"
                                >
                                    <GripVertical class="size-4" />
                                </button>
                                <CategoryChip
                                    :name="category.name"
                                    :icon="category.icon"
                                    :color="category.color"
                                />
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
                                            highlightInitialPlan
                                                ? 'text-success'
                                                : ''
                                        "
                                    >
                                        {{
                                            formatMoney(
                                                category.initial_plan_grosze,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground text-xs">
                                        {{ t.budget.current_plan }}
                                    </p>
                                    <p class="font-medium tabular-nums">
                                        {{
                                            formatMoney(
                                                category.current_plan_grosze,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground text-xs">
                                        {{ t.budget.execution }}
                                    </p>
                                    <p class="font-medium tabular-nums">
                                        {{
                                            formatMoney(
                                                category.execution_grosze,
                                            )
                                        }}
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
                            </div>
                        </CardContent>
                    </Card>
                </template>
            </draggable>

            <div
                v-if="archivedCategories.length > 0"
                class="mt-3 space-y-3"
            >
                <Card
                    v-for="category in archivedCategories"
                    :key="category.id"
                    class="opacity-60"
                >
                    <CardHeader class="gap-3 pb-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <CategoryChip
                                :name="category.name"
                                :icon="category.icon"
                                :color="category.color"
                            />
                            <Badge variant="secondary">
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
                                        highlightInitialPlan
                                            ? 'text-success'
                                            : ''
                                    "
                                >
                                    {{
                                        formatMoney(
                                            category.initial_plan_grosze,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs">
                                    {{ t.budget.current_plan }}
                                </p>
                                <p class="font-medium tabular-nums">
                                    {{
                                        formatMoney(
                                            category.current_plan_grosze,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs">
                                    {{ t.budget.execution }}
                                </p>
                                <p class="font-medium tabular-nums">
                                    {{
                                        formatMoney(category.execution_grosze)
                                    }}
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
                    </CardContent>
                </Card>
            </div>
        </div>
    </template>
</template>
