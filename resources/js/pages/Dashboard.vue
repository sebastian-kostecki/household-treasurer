<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Check, Circle } from '@lucide/vue';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';
import AccountIdentityBlock from '@/components/AccountIdentityBlock.vue';
import DashboardCategorySection from '@/components/DashboardCategorySection.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    useFirstRunChecklist,
    useShell,
    useTranslations,
} from '@/composables/useTranslations';
import type { AccountBank } from '@/lib/account-identity';
import type {
    DashboardCategory,
    DashboardTypeTotals,
} from '@/lib/dashboard-categories';
import { formatDate, formatMoney } from '@/lib/format';
import { dashboard } from '@/routes';

type AccountSummary = {
    id: number;
    name: string;
    type: 'bank' | 'cash';
    bank: AccountBank | null;
    mark_url: string | null;
    current_balance_minor_units: number;
    is_negative: boolean;
    is_archived: boolean;
};

type TermDepositSummary = {
    id: number;
    name: string;
    bank: AccountBank;
    mark_url: string | null;
    end_date: string | null;
    current_balance_minor_units: number;
    is_negative: boolean;
    is_archived: boolean;
};

type BudgetSummary = {
    year: number;
    phase: 'draft' | 'execution';
};

const props = defineProps<{
    accounts: AccountSummary[];
    termDeposits: TermDepositSummary[];
    spendable_liquidity_minor_units: number;
    total_funds_minor_units: number;
    budget: BudgetSummary | null;
    incomeCategories: DashboardCategory[];
    expenseCategories: DashboardCategory[];
    incomeTotals: DashboardTypeTotals;
    expenseTotals: DashboardTypeTotals;
    showMonthRemainingOrder: boolean;
    month_elapsed_percent: number | null;
}>();

const t = useTranslations();
const shell = useShell();
const checklist = useFirstRunChecklist();

const checklistComplete = computed(() =>
    checklist.value.every((item) => item.done),
);

const spendableLiquidityClass = computed(() =>
    props.spendable_liquidity_minor_units < 0
        ? 'text-destructive'
        : 'text-success',
);

const totalFundsClass = computed(() =>
    props.total_funds_minor_units < 0 ? 'text-destructive' : 'text-success',
);

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.dashboard,
            href: dashboard(),
        },
    ],
});

function accountTypeLabel(type: AccountSummary['type']): string {
    return type === 'bank' ? t.accounts.type_bank : t.accounts.type_cash;
}

function balanceClass(holding: AccountSummary | TermDepositSummary): string {
    if (holding.is_negative) {
        return 'text-destructive';
    }

    if (holding.current_balance_minor_units > 0) {
        return 'text-success';
    }

    return '';
}
</script>

<template>
    <Head :title="t.dashboard.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <Card v-if="shell.firstRun.showChecklist">
            <CardHeader class="pb-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <CardTitle class="text-base">
                            {{ t.dashboard.checklist_title }}
                        </CardTitle>
                        <CardDescription>
                            {{
                                checklistComplete
                                    ? t.dashboard.checklist_complete_description
                                    : t.dashboard.checklist_description
                            }}
                        </CardDescription>
                    </div>
                    <Form
                        v-if="checklistComplete"
                        v-bind="
                            DashboardController.dismissFirstRunChecklist.form()
                        "
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="ghost"
                            size="sm"
                            :disabled="processing"
                        >
                            {{ t.dashboard.checklist_dismiss }}
                        </Button>
                    </Form>
                </div>
            </CardHeader>
            <CardContent class="space-y-2">
                <div
                    v-for="item in checklist"
                    :key="item.key"
                    class="flex items-center gap-3 rounded-md border px-3 py-2 text-sm"
                >
                    <Check
                        v-if="item.done"
                        class="text-success size-4 shrink-0"
                    />
                    <Circle
                        v-else
                        class="text-muted-foreground size-4 shrink-0"
                    />
                    <span
                        :class="
                            item.done
                                ? 'text-muted-foreground line-through'
                                : ''
                        "
                    >
                        {{ t.dashboard[item.labelKey] }}
                    </span>
                </div>
            </CardContent>
        </Card>

        <section class="space-y-3">
            <h2 class="text-sm font-semibold">
                {{ t.dashboard.liquidity_title }}
            </h2>

            <div class="grid gap-3 sm:grid-cols-2">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>
                            {{ t.dashboard.spendable_liquidity }}
                        </CardDescription>
                        <CardTitle
                            class="text-3xl"
                            :class="spendableLiquidityClass"
                        >
                            {{ formatMoney(spendable_liquidity_minor_units) }}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>
                            {{ t.dashboard.total_funds }}
                        </CardDescription>
                        <CardTitle class="text-3xl" :class="totalFundsClass">
                            {{ formatMoney(total_funds_minor_units) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <div
                v-if="accounts.length > 0"
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
            >
                <Card v-for="account in accounts" :key="account.id">
                    <CardHeader class="space-y-3 pb-2">
                        <AccountIdentityBlock
                            :name="account.name"
                            :type="account.type"
                            :bank="account.bank"
                            :mark_url="account.mark_url"
                        >
                            <p class="text-sm font-medium">
                                {{ account.name }}
                            </p>
                            <p class="text-muted-foreground text-xs">
                                {{ accountTypeLabel(account.type) }}
                            </p>
                        </AccountIdentityBlock>
                        <CardTitle
                            class="text-lg"
                            :class="balanceClass(account)"
                        >
                            {{
                                formatMoney(account.current_balance_minor_units)
                            }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <Card v-else>
                <CardHeader class="pb-2">
                    <CardDescription>
                        {{ t.dashboard.no_accounts }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground text-sm">
                        {{ t.accounts.empty }}
                    </p>
                </CardContent>
            </Card>

            <div
                v-if="termDeposits.length > 0"
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
            >
                <Card
                    v-for="termDeposit in termDeposits"
                    :key="termDeposit.id"
                >
                    <CardHeader class="space-y-3 pb-2">
                        <AccountIdentityBlock
                            :name="termDeposit.name"
                            type="bank"
                            :bank="termDeposit.bank"
                            :mark_url="termDeposit.mark_url"
                        >
                            <p class="text-sm font-medium">
                                {{ termDeposit.name }}
                            </p>
                            <p
                                v-if="termDeposit.end_date"
                                class="text-muted-foreground text-xs"
                            >
                                {{ t.term_deposits.end_date }}:
                                {{ formatDate(termDeposit.end_date) }}
                            </p>
                        </AccountIdentityBlock>
                        <CardTitle
                            class="text-lg"
                            :class="balanceClass(termDeposit)"
                        >
                            {{
                                formatMoney(
                                    termDeposit.current_balance_minor_units,
                                )
                            }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>
        </section>

        <section class="space-y-3">
            <p v-if="budget === null" class="text-muted-foreground text-sm">
                {{ t.dashboard.no_budget }}
            </p>

            <div v-else class="space-y-3">
                <DashboardCategorySection
                    :title="t.dashboard.income"
                    category-type="income"
                    :categories="incomeCategories"
                    :totals="incomeTotals"
                />

                <DashboardCategorySection
                    :title="t.dashboard.expenses"
                    category-type="expense"
                    :categories="expenseCategories"
                    :totals="expenseTotals"
                    :show-month-remaining-order="showMonthRemainingOrder"
                    :month-elapsed-percent="month_elapsed_percent"
                    show-order-toggle
                />
            </div>
        </section>
    </div>
</template>
