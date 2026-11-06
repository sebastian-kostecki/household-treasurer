<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import { Toaster } from '@/components/ui/sonner';
import { useAppViews } from '@/composables/useAppViews';
import { useCommandPalette } from '@/composables/useCommandPalette';
import { useGlobalShortcuts } from '@/composables/useGlobalShortcuts';
import { useShell } from '@/composables/useTranslations';

const shell = useShell();

const views = useAppViews();
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
        <Toaster />
    </AppShell>
</template>
