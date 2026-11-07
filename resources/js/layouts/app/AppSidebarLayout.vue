<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import CreateBudgetDialog from '@/components/CreateBudgetDialog.vue';
import { Toaster } from '@/components/ui/sonner';
import { useAppViews } from '@/composables/useAppViews';
import { useCommandPalette } from '@/composables/useCommandPalette';
import { useCreateBudgetDialog } from '@/composables/useCreateBudgetDialog';
import { useGlobalShortcuts } from '@/composables/useGlobalShortcuts';
import { useShell } from '@/composables/useTranslations';

const shell = useShell();

const views = useAppViews();
const createBudgetDialog = useCreateBudgetDialog();
const { open, categories, select } = useCommandPalette(views);

useGlobalShortcuts();
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar
            :budget-years="shell.budgetYears"
            :budget-year="shell.budgetYear"
        />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <slot />
        </AppContent>
        <CommandPalette
            v-model:open="open"
            :views="views"
            :categories="categories"
            @select="select"
        />
        <CreateBudgetDialog
            v-model:open="createBudgetDialog.open.value"
            :initial-year="createBudgetDialog.initialYear.value"
            year-input-id="shell-create-budget-year"
        />
        <Toaster />
    </AppShell>
</template>
