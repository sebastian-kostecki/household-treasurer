<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import CategoryFormDialog, {
    type CategoryFormCategory,
} from '@/components/CategoryFormDialog.vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';
import { budget } from '@/routes';

type BudgetSummary = {
    id: number;
    year: number;
    phase: 'draft' | 'execution';
    can_delete: boolean;
};

type CategoryRow = CategoryFormCategory & {
    initial_plan_grosze: number;
    current_plan_grosze: number;
    execution_grosze: number;
    can_edit: boolean;
    can_delete: boolean;
};

const props = defineProps<{
    budget: BudgetSummary | null;
    incomeCategories: CategoryRow[];
    expenseCategories: CategoryRow[];
}>();

const t = useTranslations();

const createIncomeDialogOpen = ref(false);
const createExpenseDialogOpen = ref(false);
const editDialogOpen = ref(false);
const editingCategory = ref<CategoryRow | null>(null);

const phaseLabel = computed(() =>
    props.budget?.phase === 'execution'
        ? t.budget.phase_execution
        : t.budget.phase_draft,
);

const isDraft = computed(() => props.budget?.phase === 'draft');

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.budget,
            href: budget(),
        },
    ],
});

function openEditDialog(category: CategoryRow): void {
    editingCategory.value = category;
    editDialogOpen.value = true;
}
</script>

<template>
    <Head :title="t.budget.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div
            v-if="budget === null"
            class="flex flex-col items-start gap-4"
        >
            <Card class="w-full max-w-lg">
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ t.budget.no_budget }}
                    </CardTitle>
                    <CardDescription>
                        {{ t.budget.no_budget_description }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button>
                                {{ t.budget.create_budget }}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="BudgetController.store.form()"
                                class="space-y-4"
                                reset-on-success
                                v-slot="{ errors, processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{ t.budget.create_budget_title }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{ t.budget.create_budget_description }}
                                    </DialogDescription>
                                </DialogHeader>
                                <div class="grid gap-2">
                                    <Label for="budget-page-year">
                                        {{ t.budget.year }}
                                    </Label>
                                    <Input
                                        id="budget-page-year"
                                        name="year"
                                        type="number"
                                        :default-value="new Date().getFullYear()"
                                        min="2000"
                                        max="2100"
                                        required
                                    />
                                    <InputError :message="errors.year" />
                                </div>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button type="button" variant="secondary">
                                            {{ t.budget.cancel }}
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" :disabled="processing">
                                        {{ t.budget.create_budget }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </CardContent>
            </Card>
        </div>

        <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-semibold">
                        {{ t.budget.title }} {{ budget.year }}
                    </h1>
                    <Badge variant="secondary">{{ phaseLabel }}</Badge>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="isDraft"
                        variant="outline"
                        @click="createIncomeDialogOpen = true"
                    >
                        {{ t.budget.add_income }}
                    </Button>
                    <Button
                        v-if="isDraft"
                        variant="outline"
                        @click="createExpenseDialogOpen = true"
                    >
                        {{ t.budget.add_expense }}
                    </Button>
                    <Button disabled :title="t.budget.start_execution_hint">
                        {{ t.budget.start_execution }}
                    </Button>
                    <Dialog v-if="budget.can_delete">
                        <DialogTrigger as-child>
                            <Button variant="destructive">
                                {{ t.budget.delete_budget }}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="BudgetController.destroy.form(budget)"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{
                                            t.budget.delete_budget_confirm_title
                                        }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{
                                            t.budget.delete_budget_confirm_body
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
                                        {{ t.budget.delete_budget }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Checkbox id="show-archived-budget" disabled />
                <Label
                    for="show-archived-budget"
                    class="text-muted-foreground text-sm"
                >
                    {{ t.budget.show_archived }}
                </Label>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">
                            {{ t.budget.income_table }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-2 font-medium">
                                        {{ t.budget.category }}
                                    </th>
                                    <th class="pb-2 text-right font-medium">
                                        {{ t.budget.initial_plan }}
                                    </th>
                                    <th class="pb-2 text-right font-medium">
                                        {{ t.budget.current_plan }}
                                    </th>
                                    <th class="pb-2 text-right font-medium">
                                        {{ t.budget.execution }}
                                    </th>
                                    <th
                                        v-if="isDraft"
                                        class="pb-2 text-right font-medium"
                                    />
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="incomeCategories.length === 0">
                                    <td
                                        colspan="5"
                                        class="text-muted-foreground py-6 text-center"
                                    >
                                        {{ t.budget.empty }}
                                    </td>
                                </tr>
                                <tr
                                    v-for="category in incomeCategories"
                                    :key="category.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-2">{{ category.name }}</td>
                                    <td class="py-2 text-right text-success">
                                        {{
                                            formatMoney(
                                                category.initial_plan_grosze,
                                            )
                                        }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{
                                            formatMoney(
                                                category.current_plan_grosze,
                                            )
                                        }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{
                                            formatMoney(
                                                category.execution_grosze,
                                            )
                                        }}
                                    </td>
                                    <td
                                        v-if="isDraft"
                                        class="py-2 text-right"
                                    >
                                        <div
                                            class="flex justify-end gap-1"
                                        >
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="
                                                    openEditDialog(category)
                                                "
                                            >
                                                {{ t.budget.edit }}
                                            </Button>
                                            <Dialog
                                                v-if="category.can_delete"
                                            >
                                                <DialogTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="text-destructive"
                                                    >
                                                        {{ t.budget.delete }}
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <Form
                                                        v-bind="
                                                            CategoryController.destroy.form(
                                                                category,
                                                            )
                                                        "
                                                        v-slot="{
                                                            processing,
                                                        }"
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
                                                        <DialogFooter
                                                            class="gap-2"
                                                        >
                                                            <DialogClose
                                                                as-child
                                                            >
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
                                                                {{
                                                                    t.budget.delete
                                                                }}
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
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">
                            {{ t.budget.expense_table }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="pb-2 font-medium">
                                        {{ t.budget.category }}
                                    </th>
                                    <th class="pb-2 text-right font-medium">
                                        {{ t.budget.initial_plan }}
                                    </th>
                                    <th class="pb-2 text-right font-medium">
                                        {{ t.budget.current_plan }}
                                    </th>
                                    <th class="pb-2 text-right font-medium">
                                        {{ t.budget.execution }}
                                    </th>
                                    <th
                                        v-if="isDraft"
                                        class="pb-2 text-right font-medium"
                                    />
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="expenseCategories.length === 0">
                                    <td
                                        colspan="5"
                                        class="text-muted-foreground py-6 text-center"
                                    >
                                        {{ t.budget.empty }}
                                    </td>
                                </tr>
                                <tr
                                    v-for="category in expenseCategories"
                                    :key="category.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-2">{{ category.name }}</td>
                                    <td class="py-2 text-right">
                                        {{
                                            formatMoney(
                                                category.initial_plan_grosze,
                                            )
                                        }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{
                                            formatMoney(
                                                category.current_plan_grosze,
                                            )
                                        }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{
                                            formatMoney(
                                                category.execution_grosze,
                                            )
                                        }}
                                    </td>
                                    <td
                                        v-if="isDraft"
                                        class="py-2 text-right"
                                    >
                                        <div
                                            class="flex justify-end gap-1"
                                        >
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="
                                                    openEditDialog(category)
                                                "
                                            >
                                                {{ t.budget.edit }}
                                            </Button>
                                            <Dialog
                                                v-if="category.can_delete"
                                            >
                                                <DialogTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="text-destructive"
                                                    >
                                                        {{ t.budget.delete }}
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <Form
                                                        v-bind="
                                                            CategoryController.destroy.form(
                                                                category,
                                                            )
                                                        "
                                                        v-slot="{
                                                            processing,
                                                        }"
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
                                                        <DialogFooter
                                                            class="gap-2"
                                                        >
                                                            <DialogClose
                                                                as-child
                                                            >
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
                                                                {{
                                                                    t.budget.delete
                                                                }}
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
                    </CardContent>
                </Card>
            </div>
        </template>
    </div>

    <CategoryFormDialog
        v-if="budget"
        v-model:open="createIncomeDialogOpen"
        mode="create"
        :budget-id="budget.id"
        default-type="income"
    />

    <CategoryFormDialog
        v-if="budget"
        v-model:open="createExpenseDialogOpen"
        mode="create"
        :budget-id="budget.id"
        default-type="expense"
    />

    <CategoryFormDialog
        v-if="budget"
        v-model:open="editDialogOpen"
        mode="edit"
        :budget-id="budget.id"
        default-type="income"
        :category="editingCategory"
    />
</template>
