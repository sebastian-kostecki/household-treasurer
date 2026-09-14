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
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useTranslations } from '@/composables/useTranslations';
import { index as accounts } from '@/routes/accounts';
import { index as termDeposits } from '@/routes/term-deposits';
import { agenda, budget, dashboard, operations } from '@/routes';
import type { NavItem } from '@/types';

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
    {
        title: t.nav.agenda,
        href: agenda(),
        icon: CalendarDays,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
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
            <BudgetYearSwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" :label="t.nav.menu" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarSeparator />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
