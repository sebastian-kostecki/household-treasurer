<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import {
    formatDate,
    formatFinancialOperationAmount,
    formatMoney,
} from '@/lib/format';
import { operations as operationsRoute } from '@/routes';

export type BudgetMonthInMonthEntry = {
    kind: 'expected' | 'booked';
    date: string;
    description: string;
    amount_minor_units: number;
    cash_expectation_id?: number;
    financial_operation_id?: number;
    budget_year: number;
};

const props = defineProps<{
    categoryType: 'income' | 'expense';
    isAgendaCategory: boolean;
    monthInMonthEntries?: BudgetMonthInMonthEntry[];
    monthIncomeExecutionMinorUnits?: number;
    monthRemainingMinorUnits?: number | null;
    monthExecutionMinorUnits?: number;
    isPaced?: boolean;
}>();

const t = useTranslations();

const entries = computed(() => props.monthInMonthEntries ?? []);

const optionalSumMinorUnits = computed(() => {
    if (entries.value.length === 0) {
        return null;
    }

    return entries.value.reduce(
        (total, entry) => total + entry.amount_minor_units,
        0,
    );
});

function legacyMonthLabel(): string {
    if (
        props.isPaced &&
        props.monthRemainingMinorUnits !== undefined &&
        props.monthRemainingMinorUnits !== null
    ) {
        return formatMoney(props.monthRemainingMinorUnits);
    }

    if (!props.isPaced && (props.monthExecutionMinorUnits ?? 0) !== 0) {
        return formatMoney(props.monthExecutionMinorUnits ?? 0);
    }

    return '—';
}

function incomeOrdinaryLabel(): string {
    const execution = props.monthIncomeExecutionMinorUnits ?? 0;

    if (execution === 0) {
        return '—';
    }

    return formatMoney(execution);
}
</script>

<template>
    <div v-if="isAgendaCategory" class="space-y-1.5 text-sm">
        <ul
            v-if="entries.length > 0"
            class="space-y-1.5"
            :aria-label="t.budget.in_month_column"
        >
            <li
                v-for="(entry, index) in entries"
                :key="`${entry.kind}-${entry.date}-${index}`"
                class="border-border/60 rounded-md border px-2 py-1.5"
            >
                <div class="flex flex-wrap items-center gap-1.5">
                    <Badge
                        :variant="
                            entry.kind === 'expected' ? 'outline' : 'secondary'
                        "
                    >
                        {{
                            entry.kind === 'expected'
                                ? t.budget.in_month_expected
                                : t.budget.in_month_booked
                        }}
                    </Badge>
                    <span class="text-muted-foreground text-xs tabular-nums">
                        {{ formatDate(entry.date) }}
                    </span>
                </div>
                <p class="mt-1 text-xs leading-snug">{{ entry.description }}</p>
                <p class="mt-0.5 font-medium tabular-nums">
                    {{
                        formatFinancialOperationAmount(
                            categoryType,
                            entry.amount_minor_units,
                        )
                    }}
                </p>
                <Form
                    v-if="entry.kind === 'expected'"
                    v-bind="BudgetController.selectYear.form()"
                    class="mt-1"
                >
                    <input
                        type="hidden"
                        name="year"
                        :value="entry.budget_year"
                    />
                    <input type="hidden" name="redirect" value="agenda" />
                    <button
                        type="submit"
                        class="text-primary text-xs font-medium hover:underline"
                    >
                        {{ t.budget.in_month_open_agenda }}
                    </button>
                </Form>
                <Link
                    v-else
                    :href="operationsRoute()"
                    class="text-primary mt-1 inline-block text-xs font-medium hover:underline"
                >
                    {{ t.budget.in_month_open_operations }}
                </Link>
            </li>
        </ul>
        <p
            v-if="optionalSumMinorUnits !== null"
            class="text-muted-foreground text-xs tabular-nums"
        >
            {{ t.budget.in_month_sum }}:
            {{ formatMoney(optionalSumMinorUnits) }}
        </p>
    </div>
    <p v-else-if="categoryType === 'income'" class="font-medium tabular-nums">
        {{ incomeOrdinaryLabel() }}
    </p>
    <p v-else class="font-medium tabular-nums">
        {{ legacyMonthLabel() }}
    </p>
</template>
