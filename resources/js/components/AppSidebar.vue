<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
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
import { openCommandPalette } from '@/composables/useCommandPalette';
import { useAppViews } from '@/composables/useAppViews';
import { useShell, useTranslations } from '@/composables/useTranslations';
import { appDayYear } from '@/lib/app-day';
import { paletteShortcutLabel } from '@/lib/command-palette-items';
import { dashboard } from '@/routes';
import type { BudgetYearEntry } from '@/types/shell';

type Props = {
    budgetYears: BudgetYearEntry[];
    budgetYear: number | null;
};

defineProps<Props>();

const shell = useShell();
const currentYear = computed(() => appDayYear(shell.value.today));
const t = useTranslations();
const views = useAppViews();

const paletteShortcut = ref<'⌘K' | 'Ctrl+K'>('Ctrl+K');

onMounted(() => {
    paletteShortcut.value = paletteShortcutLabel(navigator.platform);
});

const shortcutsHint = t.shell.shortcuts_hint.replace(
    ':last',
    String(views.length),
);
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
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="sidebar-search"
                        aria-keyshortcuts="Control+K Meta+K"
                        @click="openCommandPalette"
                    >
                        <Search />
                        <span>{{ t.shell.search_and_go }}</span>
                        <kbd class="kbd" aria-hidden="true">{{
                            paletteShortcut
                        }}</kbd>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <BudgetYearSwitcher
                :budget-years="budgetYears"
                :budget-year="budgetYear"
                :current-year="currentYear"
            />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="views" :label="t.nav.menu" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarSeparator />
            <p class="sidebar-hint">{{ shortcutsHint }}</p>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
