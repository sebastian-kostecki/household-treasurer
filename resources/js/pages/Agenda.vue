<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { ref } from 'vue';
import CashExpectationFormDialog, {
    type CashExpectationFormAccount,
    type CashExpectationFormCategory,
} from '@/components/CashExpectationFormDialog.vue';
import CategoryChip from '@/components/CategoryChip.vue';
import CompactAccountIdentity from '@/components/CompactAccountIdentity.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ListSurface from '@/components/ListSurface.vue';
import OperationDescriptionCell from '@/components/OperationDescriptionCell.vue';
import OperationsDateCell from '@/components/OperationsDateCell.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useShell, useTranslations } from '@/composables/useTranslations';
import type { PrefixedAccountIdentityFields } from '@/lib/account-identity';
import {
    financialOperationAmountClass,
    formatFinancialOperationAmount,
} from '@/lib/format';
import { agenda as agendaRoute } from '@/routes';

type CashExpectationRow = {
    id: number;
    type: 'income' | 'expense';
    expectation_date: string;
    amount_minor_units: number;
    description: string;
    counterparty: string | null;
    is_expense_refund: boolean;
    is_overdue: boolean;
    is_today: boolean;
    account_id: number;
    category_id: number;
    category_name: string;
    category_icon: string;
    category_color: string;
} & PrefixedAccountIdentityFields<'account'>;

const props = defineProps<{
    cashExpectations: CashExpectationRow[];
    accounts: CashExpectationFormAccount[];
    incomeCategories: CashExpectationFormCategory[];
    expenseCategories: CashExpectationFormCategory[];
}>();

const t = useTranslations();
const shell = useShell();
const createDialogOpen = ref(false);

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.agenda,
            href: agendaRoute(),
        },
    ],
});

function typeVariant(
    expectation: CashExpectationRow,
): 'income' | 'expense' | 'refund' {
    if (expectation.type === 'income') {
        return 'income';
    }

    if (expectation.is_expense_refund) {
        return 'refund';
    }

    return 'expense';
}

function defaultExpectationDate(): string {
    if (shell.budgetYear === null) {
        return '';
    }

    const today = new Date();
    const todayYear = today.getFullYear();

    if (shell.budgetYear === todayYear) {
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');

        return `${shell.budgetYear}-${month}-${day}`;
    }

    return `${shell.budgetYear}-01-01`;
}
</script>

<template>
    <Head :title="t.agenda.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold">{{ t.agenda.title }}</h1>
            <Button @click="createDialogOpen = true">
                {{ t.agenda.add }}
            </Button>
        </div>

        <IndexEmptyState
            v-if="cashExpectations.length === 0"
            :title="t.agenda.empty_title"
            :description="t.agenda.empty_description"
        >
            <template #actions>
                <Button @click="createDialogOpen = true">
                    {{ t.agenda.add }}
                </Button>
            </template>
        </IndexEmptyState>

        <ListSurface
            v-else
            :title="t.agenda.title"
            :description="t.agenda.list_description"
        >
            <div
                v-for="expectation in cashExpectations"
                :key="expectation.id"
                class="operations-grid border-border/60 hover:bg-foreground/[0.03] gap-y-3 border-b py-3 text-sm transition-colors last:border-b-0 md:gap-y-0"
            >
                <div class="operations-cell-date">
                    <div class="flex items-start gap-2">
                        <OperationsDateCell
                            :date="expectation.expectation_date"
                        />
                        <Badge
                            v-if="expectation.is_overdue"
                            variant="destructive"
                        >
                            {{ t.agenda.overdue_badge }}
                        </Badge>
                        <Badge
                            v-else-if="expectation.is_today"
                            variant="secondary"
                        >
                            {{ t.agenda.today_badge }}
                        </Badge>
                    </div>
                </div>

                <div class="operations-cell-description">
                    <OperationDescriptionCell
                        :variant="typeVariant(expectation)"
                        :description="expectation.description"
                        :counterparty="expectation.counterparty"
                        :raw-statement-description="null"
                    />
                </div>

                <div class="operations-cell-category">
                    <CategoryChip
                        :name="expectation.category_name"
                        :icon="expectation.category_icon"
                        :color="expectation.category_color"
                    />
                </div>

                <CompactAccountIdentity
                    class="operations-cell-account"
                    mark-size="md"
                    :name="expectation.account_name"
                    :type="expectation.account_type"
                    :bank="expectation.account_bank"
                    :mark_url="expectation.account_mark_url"
                />

                <p
                    class="operations-cell-amount shrink-0 text-right font-medium tabular-nums"
                    :class="
                        financialOperationAmountClass(
                            expectation.type,
                            expectation.amount_minor_units,
                        )
                    "
                >
                    {{
                        formatFinancialOperationAmount(
                            expectation.type,
                            expectation.amount_minor_units,
                        )
                    }}
                </p>
            </div>
        </ListSurface>
    </div>

    <CashExpectationFormDialog
        v-model:open="createDialogOpen"
        :accounts="accounts"
        :income-categories="incomeCategories"
        :expense-categories="expenseCategories"
        :default-date="defaultExpectationDate()"
    />
</template>
