<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    LayoutGrid,
    PiggyBank,
    Settings,
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
import {
    budget,
    dashboard,
    operations,
} from '@/routes';
import { edit as editProfile } from '@/routes/profile';
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
        title: t.nav.operations,
        href: operations(),
        icon: ArrowLeftRight,
    },
    {
        title: t.nav.settings,
        href: editProfile(),
        icon: Settings,
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
