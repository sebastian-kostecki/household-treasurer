<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Check, Circle } from '@lucide/vue';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';
import AccountIdentityBlock from '@/components/AccountIdentityBlock.vue';
import DashboardCategorySection from '@/components/DashboardCategorySection.vue';
import { Badge } from '@/components/ui/badge';
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
import { formatMoney } from '@/lib/format';
import { dashboard } from '@/routes';

type AccountSummary = {
    id: number;
    name: string;
    type: 'bank' | 'cash';
    bank: AccountBank | null;
    mark_url: string | null;
    current_balance_grosze: number;
    is_negative: boolean;
    is_archived: boolean;
};

type BudgetSummary = {
    year: number;
    phase: 'draft' | 'execution';
};

const props = defineProps<{
    accounts: AccountSummary[];
    liquidity_total_grosze: number;
    budget: BudgetSummary | null;
    incomeCategories: DashboardCategory[];
    expenseCategories: DashboardCategory[];
    incomeTotals: DashboardTypeTotals;
    expenseTotals: DashboardTypeTotals;
}>();

const t = useTranslations();
const shell = useShell();
const checklist = useFirstRunChecklist();

const checklistComplete = computed(() =>
    checklist.value.every((item) => item.done),
);

const liquidityClass = computed(() =>
    props.liquidity_total_grosze < 0 ? 'text-destructive' : 'text-success',
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

function balanceClass(account: AccountSummary): string {
    if (account.is_negative) {
        return 'text-destructive';
    }

    if (account.current_balance_grosze > 0) {
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
                        v-bind="DashboardController.dismissFirstRunChecklist.form()"
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

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>
                        {{ t.dashboard.total_funds }}
                    </CardDescription>
                    <CardTitle class="text-3xl" :class="liquidityClass">
                        {{ formatMoney(liquidity_total_grosze) }}
                    </CardTitle>
                </CardHeader>
            </Card>

            <div
                v-if="accounts.length > 0"
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
            >
                <Card
                    v-for="account in accounts"
                    :key="account.id"
                    :class="account.is_archived ? 'opacity-70' : ''"
                >
                    <CardHeader class="space-y-3 pb-2">
                        <AccountIdentityBlock
                            :name="account.name"
                            :type="account.type"
                            :bank="account.bank"
                            :mark_url="account.mark_url"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium">
                                    {{ account.name }}
                                </p>
                                <Badge
                                    v-if="account.is_archived"
                                    variant="secondary"
                                >
                                    {{ t.budget.archived_badge }}
                                </Badge>
                            </div>
                            <p class="text-muted-foreground text-xs">
                                {{ accountTypeLabel(account.type) }}
                            </p>
                        </AccountIdentityBlock>
                        <CardTitle
                            class="text-lg"
                            :class="balanceClass(account)"
                        >
                            {{ formatMoney(account.current_balance_grosze) }}
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
        </section>

        <section class="space-y-3">
            <h2 class="text-sm font-semibold">
                {{ t.dashboard.execution_title }}
            </h2>

            <p
                v-if="budget === null"
                class="text-muted-foreground text-sm"
            >
                {{ t.dashboard.no_budget }}
            </p>

            <div
                v-else
                class="space-y-3"
            >
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
                    show-order-toggle
                />
            </div>
        </section>
    </div>
</template>
