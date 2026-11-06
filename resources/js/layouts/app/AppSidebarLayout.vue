<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import { Toaster } from '@/components/ui/sonner';
import { useShell } from '@/composables/useTranslations';

const shell = useShell();
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
        <Toaster />
    </AppShell>
</template>
