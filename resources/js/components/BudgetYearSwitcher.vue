<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { CalendarPlus } from '@lucide/vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import CreateBudgetDialog from '@/components/CreateBudgetDialog.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useShell, useTranslations } from '@/composables/useTranslations';
import { ref } from 'vue';

const shell = useShell();
const t = useTranslations();
const createDialogOpen = ref(false);
</script>

<template>
    <div class="px-2 py-2">
        <p class="text-muted-foreground mb-1 px-2 text-xs font-medium">
            {{ t.shell.budget_year }}
        </p>
        <div
            v-if="shell.budgetYears.length === 0"
            class="border-sidebar-border flex flex-col gap-2 rounded-md border border-dashed px-2 py-2"
        >
            <p class="text-muted-foreground text-xs">
                {{ t.shell.no_budget_year }}
            </p>
            <CreateBudgetDialog
                v-model:open="createDialogOpen"
                year-input-id="shell-create-budget-year-empty"
            >
                <template #trigger>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 w-full justify-start gap-2"
                    >
                        <CalendarPlus class="size-4" />
                        {{ t.shell.add_budget_year }}
                    </Button>
                </template>
            </CreateBudgetDialog>
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
                        (
                            $event.target as HTMLSelectElement
                        ).form?.requestSubmit()
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
            <CreateBudgetDialog
                v-model:open="createDialogOpen"
                year-input-id="shell-create-budget-year"
            >
                <template #trigger>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 w-full justify-start gap-2"
                    >
                        <CalendarPlus class="size-4" />
                        {{ t.shell.add_budget_year }}
                    </Button>
                </template>
            </CreateBudgetDialog>
        </div>
    </div>
</template>
