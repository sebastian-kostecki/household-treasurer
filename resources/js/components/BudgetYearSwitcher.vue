<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { CalendarPlus } from '@lucide/vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import { Button } from '@/components/ui/button';
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
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { useShell, useTranslations } from '@/composables/useTranslations';
import { ref } from 'vue';

const shell = useShell();
const t = useTranslations();
const createDialogOpen = ref(false);
const currentYear = new Date().getFullYear();
</script>

<template>
    <div class="px-2 py-2">
        <p class="text-muted-foreground mb-1 px-2 text-xs font-medium">
            {{ t.shell.budget_year }}
        </p>
        <div
            v-if="shell.budgetYears.length === 0"
            class="flex flex-col gap-2 rounded-md border border-dashed border-sidebar-border px-2 py-2"
        >
            <p class="text-muted-foreground text-xs">
                {{ t.shell.no_budget_year }}
            </p>
            <Dialog v-model:open="createDialogOpen">
                <DialogTrigger as-child>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 w-full justify-start gap-2"
                    >
                        <CalendarPlus class="size-4" />
                        {{ t.shell.add_budget_year }}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="BudgetController.store.form()"
                        class="space-y-4"
                        reset-on-success
                        @success="createDialogOpen = false"
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
                            <Label for="budget-year">{{ t.budget.year }}</Label>
                            <Input
                                id="budget-year"
                                name="year"
                                type="number"
                                :default-value="currentYear"
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
        </div>
        <div v-else class="space-y-2 px-2">
            <Form
                v-bind="BudgetController.selectYear.form()"
                class="space-y-1"
                v-slot="{ processing }"
            >
                <Label for="shell-budget-year" class="sr-only">
                    {{ t.shell.select_year }}
                </Label>
                <select
                    id="shell-budget-year"
                    name="year"
                    class="native-select h-8 px-2"
                    :value="shell.budgetYear ?? ''"
                    :disabled="processing"
                    @change="
                        ($event.target as HTMLSelectElement).form?.requestSubmit()
                    "
                >
                    <option
                        v-for="year in shell.budgetYears"
                        :key="year"
                        :value="year"
                    >
                        {{ year }}
                    </option>
                </select>
            </Form>
            <Dialog v-model:open="createDialogOpen">
                <DialogTrigger as-child>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 w-full justify-start gap-2"
                    >
                        <CalendarPlus class="size-4" />
                        {{ t.shell.add_budget_year }}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="BudgetController.store.form()"
                        class="space-y-4"
                        reset-on-success
                        @success="createDialogOpen = false"
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
                            <Label for="budget-year-add">
                                {{ t.budget.year }}
                            </Label>
                            <Input
                                id="budget-year-add"
                                name="year"
                                type="number"
                                :default-value="currentYear + 1"
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
        </div>
    </div>
</template>
