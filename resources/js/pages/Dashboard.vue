<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Check, Circle } from '@lucide/vue';
import AccountIdentityBlock from '@/components/AccountIdentityBlock.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    useFirstRunChecklist,
    useTranslations,
} from '@/composables/useTranslations';
import type { AccountBank } from '@/lib/account-identity';
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

type CategorySummary = {
    id: number;
    name: string;
    initial_plan_grosze: number;
    current_plan_grosze: number;
    execution_grosze: number;
    remaining_grosze: number;
    percent_complete: number | null;
    is_over_limit: boolean;
    is_archived: boolean;
};

type BudgetSummary = {
    year: number;
    phase: 'draft' | 'execution';
};

type Totals = {
    current_plan_grosze: number;
    execution_grosze: number;
};

const props = defineProps<{
    accounts: AccountSummary[];
    liquidity_total_grosze: number;
    budget: BudgetSummary | null;
    incomeCategories: CategorySummary[];
    expenseCategories: CategorySummary[];
    incomeTotals: Totals;
    expenseTotals: Totals;
}>();

const t = useTranslations();
const checklist = useFirstRunChecklist();

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

function remainingClass(category: CategorySummary): string {
    if (category.is_over_limit) {
        return 'text-destructive';
    }

    return '';
}

function progressWidth(category: CategorySummary): string {
    if (category.percent_complete === null) {
        return '0%';
    }

    return `${Math.min(category.percent_complete, 100)}%`;
}

function progressBarClass(category: CategorySummary): string {
    if (category.is_over_limit) {
        return 'bg-destructive';
    }

    return 'bg-primary';
}
</script>

<template>
    <Head :title="t.dashboard.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-base">
                    {{ t.dashboard.checklist_title }}
                </CardTitle>
                <CardDescription>
                    {{ t.dashboard.checklist_description }}
                </CardDescription>
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
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>
                            {{ t.dashboard.total_funds }}
                        </CardDescription>
                        <CardTitle class="text-2xl" :class="liquidityClass">
                            {{ formatMoney(liquidity_total_grosze) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
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
                            class="text-xl"
                            :class="balanceClass(account)"
                        >
                            {{ formatMoney(account.current_balance_grosze) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card v-if="accounts.length === 0">
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
            </div>
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
                class="grid gap-3 lg:grid-cols-2"
            >
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">
                            {{ t.dashboard.income }}
                        </CardTitle>
                        <CardDescription>
                            {{ t.dashboard.income_totals }}:
                            {{ formatMoney(incomeTotals.current_plan_grosze) }}
                            /
                            {{ formatMoney(incomeTotals.execution_grosze) }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p
                            v-if="incomeCategories.length === 0"
                            class="text-muted-foreground text-sm"
                        >
                            {{ t.dashboard.no_categories }}
                        </p>
                        <div
                            v-for="category in incomeCategories"
                            :key="category.id"
                            class="space-y-2 rounded-md border p-3"
                            :class="category.is_archived ? 'opacity-70' : ''"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">
                                        {{ category.name }}
                                    </span>
                                    <Badge
                                        v-if="category.is_archived"
                                        variant="secondary"
                                    >
                                        {{ t.budget.archived_badge }}
                                    </Badge>
                                </div>
                                <Badge
                                    v-if="category.percent_complete === null"
                                    variant="secondary"
                                >
                                    {{ t.common.not_applicable }}
                                </Badge>
                                <Badge
                                    v-else
                                    variant="secondary"
                                >
                                    {{ category.percent_complete }}%
                                </Badge>
                            </div>
                            <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                <dt class="text-muted-foreground">
                                    {{ t.dashboard.initial_plan }}
                                </dt>
                                <dd class="text-right">
                                    {{ formatMoney(category.initial_plan_grosze) }}
                                </dd>
                                <dt class="text-muted-foreground">
                                    {{ t.dashboard.current_plan }}
                                </dt>
                                <dd class="text-right">
                                    {{ formatMoney(category.current_plan_grosze) }}
                                </dd>
                                <dt class="text-muted-foreground">
                                    {{ t.dashboard.execution }}
                                </dt>
                                <dd class="text-right text-success">
                                    {{ formatMoney(category.execution_grosze) }}
                                </dd>
                                <dt class="text-muted-foreground">
                                    {{ t.dashboard.remaining }}
                                </dt>
                                <dd
                                    class="text-right"
                                    :class="remainingClass(category)"
                                >
                                    {{ formatMoney(category.remaining_grosze) }}
                                </dd>
                            </dl>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">
                            {{ t.dashboard.expenses }}
                        </CardTitle>
                        <CardDescription>
                            {{ t.dashboard.expense_totals }}:
                            {{ formatMoney(expenseTotals.current_plan_grosze) }}
                            /
                            {{ formatMoney(expenseTotals.execution_grosze) }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p
                            v-if="expenseCategories.length === 0"
                            class="text-muted-foreground text-sm"
                        >
                            {{ t.dashboard.no_categories }}
                        </p>
                        <div
                            v-for="category in expenseCategories"
                            :key="category.id"
                            class="space-y-2 rounded-md border p-3"
                            :class="category.is_archived ? 'opacity-70' : ''"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">
                                        {{ category.name }}
                                    </span>
                                    <Badge
                                        v-if="category.is_archived"
                                        variant="secondary"
                                    >
                                        {{ t.budget.archived_badge }}
                                    </Badge>
                                </div>
                                <Badge
                                    v-if="category.percent_complete === null"
                                    variant="secondary"
                                >
                                    {{ t.common.not_applicable }}
                                </Badge>
                                <Badge
                                    v-else-if="category.is_over_limit"
                                    variant="destructive"
                                >
                                    {{ t.dashboard.over_limit }}
                                </Badge>
                                <Badge
                                    v-else
                                    variant="secondary"
                                >
                                    {{ category.percent_complete }}%
                                </Badge>
                            </div>
                            <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                <dt class="text-muted-foreground">
                                    {{ t.dashboard.initial_plan }}
                                </dt>
                                <dd class="text-right">
                                    {{ formatMoney(category.initial_plan_grosze) }}
                                </dd>
                                <dt class="text-muted-foreground">
                                    {{ t.dashboard.current_plan }}
                                </dt>
                                <dd class="text-right">
                                    {{ formatMoney(category.current_plan_grosze) }}
                                </dd>
                                <dt class="text-muted-foreground">
                                    {{ t.dashboard.execution }}
                                </dt>
                                <dd class="text-right">
                                    {{ formatMoney(category.execution_grosze) }}
                                </dd>
                                <dt class="text-muted-foreground">
                                    {{ t.dashboard.remaining }}
                                </dt>
                                <dd
                                    class="text-right"
                                    :class="remainingClass(category)"
                                >
                                    {{ formatMoney(category.remaining_grosze) }}
                                </dd>
                            </dl>
                            <div class="space-y-1">
                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span class="text-muted-foreground">
                                        {{ t.dashboard.planned }}:
                                        {{ formatMoney(category.current_plan_grosze) }}
                                    </span>
                                    <span class="text-muted-foreground">
                                        {{ t.dashboard.executed }}:
                                        {{ formatMoney(category.execution_grosze) }}
                                    </span>
                                </div>
                                <div
                                    class="bg-muted h-2 overflow-hidden rounded-full"
                                >
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="progressBarClass(category)"
                                        :style="{ width: progressWidth(category) }"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>
    </div>
</template>
