<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, useId } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import BudgetPlanSummaryStrip from '@/components/BudgetPlanSummaryStrip.vue';
import CreateBudgetDialog from '@/components/CreateBudgetDialog.vue';
import DashboardExecutionPanel from '@/components/DashboardExecutionPanel.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import type {
    DashboardCategory,
    DashboardTypeTotals,
} from '@/lib/dashboard-categories';
import type { BudgetSummary, PlanSummary } from '@/types/dashboard';

const props = defineProps<{
    budget: BudgetSummary;
    budgetYear: number;
    planSummary: PlanSummary | null;
    incomeCategories: DashboardCategory[];
    expenseCategories: DashboardCategory[];
    incomeTotals: DashboardTypeTotals;
    expenseTotals: DashboardTypeTotals;
    yearElapsedPercent: number | null;
    canSelectCurrentYearInShell: boolean;
}>();

const showYearElapsedMark = defineModel<boolean>('showYearElapsedMark', {
    required: true,
});

const emit = defineEmits<{
    start: [];
    finish: [];
    error: [];
}>();

const t = useTranslations();
const yearMarkSwitchId = useId();
const createBudgetDialogOpen = ref(false);

const title = computed(() =>
    t.dashboard.execution_panel_title.replace(
        ':year',
        String(props.budgetYear),
    ),
);

const panelYearElapsedPercent = computed(() =>
    showYearElapsedMark.value ? props.yearElapsedPercent : null,
);
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title">{{ title }}</h2>

            <div class="flex flex-wrap items-center gap-3">
                <div
                    v-if="budget.phase === 'execution'"
                    class="flex items-center gap-2"
                >
                    <Switch
                        :id="yearMarkSwitchId"
                        v-model="showYearElapsedMark"
                    />
                    <Label
                        :for="yearMarkSwitchId"
                        class="text-muted-foreground text-sm"
                    >
                        {{ t.dashboard.year_elapsed }}
                    </Label>
                </div>

                <Form
                    v-if="canSelectCurrentYearInShell"
                    v-bind="BudgetController.selectYear.form()"
                    v-slot="{ processing }"
                    @start="emit('start')"
                    @finish="emit('finish')"
                    @error="emit('error')"
                >
                    <input type="hidden" name="year" :value="budgetYear" />
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="processing"
                    >
                        {{ t.dashboard.open_budget }}
                    </Button>
                </Form>
                <CreateBudgetDialog
                    v-else
                    v-model:open="createBudgetDialogOpen"
                    year-input-id="dashboard-open-budget-year"
                    :suggested-year="budgetYear"
                >
                    <template #trigger>
                        <Button variant="outline">
                            {{ t.dashboard.open_budget }}
                        </Button>
                    </template>
                </CreateBudgetDialog>
            </div>
        </div>

        <BudgetPlanSummaryStrip
            v-if="planSummary"
            :income-totals="planSummary.income"
            :expense-totals="planSummary.expense"
            :planned-balance-minor-units="
                planSummary.planned_balance_minor_units
            "
        />

        <Card v-if="budget.phase === 'draft'">
            <IndexEmptyState
                :title="t.dashboard.draft_categories_title"
                :description="t.dashboard.draft_categories_description"
            />
        </Card>

        <DashboardExecutionPanel
            v-else
            :income-categories="incomeCategories"
            :expense-categories="expenseCategories"
            :income-totals="incomeTotals"
            :expense-totals="expenseTotals"
            :year-elapsed-percent="panelYearElapsedPercent"
            :budget-year="budgetYear"
        />
    </div>
</template>
