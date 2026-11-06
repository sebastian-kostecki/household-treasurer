<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    CalendarDays,
    Landmark,
    LayoutGrid,
    PiggyBank,
    Wallet,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import BudgetYearSwitcher from '@/components/BudgetYearSwitcher.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useTranslations } from '@/composables/useTranslations';
import { index as accounts } from '@/routes/accounts';
import { index as termDeposits } from '@/routes/term-deposits';
import { budget, dashboard, monthPlan, operations } from '@/routes';
import type { NavItem } from '@/types';

type Props = {
    budgetYears: number[];
    budgetYear: number | null;
};

defineProps<Props>();

const t = useTranslations();

const mainNavItems: NavItem[] = [
    {
        title: t.nav.dashboard,
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: t.nav.budget,
        href: budget(),
        icon: PiggyBank,
    },
    {
        title: t.nav.month_plan,
        href: monthPlan(),
        icon: CalendarDays,
    },
    {
        title: t.nav.accounts,
        href: accounts(),
        icon: Wallet,
    },
    {
        title: t.nav.term_deposits,
        href: termDeposits(),
        icon: Landmark,
    },
    {
        title: t.nav.operations,
        href: operations(),
        icon: ArrowLeftRight,
    },
];
</script>

<template>
    <Sidebar collapsible="offcanvas" variant="sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarGroupLabel>{{ t.shell.budget_year }}</SidebarGroupLabel>
            <BudgetYearSwitcher
                :budget-years="budgetYears"
                :budget-year="budgetYear"
            />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" :label="t.nav.menu" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarSeparator />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
