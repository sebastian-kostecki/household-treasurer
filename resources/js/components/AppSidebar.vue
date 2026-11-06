<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import BudgetYearSwitcher from '@/components/BudgetYearSwitcher.vue';
import CreateBudgetDialog from '@/components/CreateBudgetDialog.vue';
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
import { useCreateBudgetDialog } from '@/composables/useCreateBudgetDialog';
import { useTranslations } from '@/composables/useTranslations';
import { paletteShortcutLabel } from '@/lib/command-palette-items';
import { dashboard } from '@/routes';

type Props = {
    budgetYears: number[];
    budgetYear: number | null;
};

defineProps<Props>();

const t = useTranslations();
const createBudgetDialog = useCreateBudgetDialog();
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

        <CreateBudgetDialog
            v-model:open="createBudgetDialog.open.value"
            :initial-year="createBudgetDialog.initialYear.value"
            year-input-id="shell-create-budget-year"
        />
    </Sidebar>
</template>
