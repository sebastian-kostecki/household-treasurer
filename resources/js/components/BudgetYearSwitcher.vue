<script setup lang="ts">
import { CalendarPlus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useShell, useTranslations } from '@/composables/useTranslations';

const shell = useShell();
const t = useTranslations();
</script>

<template>
    <div class="px-2 py-2">
        <p class="text-muted-foreground mb-1 px-2 text-xs font-medium">
            {{ t.shell.budget_year }}
        </p>
        <div
            v-if="shell.budgetYear === null"
            class="flex flex-col gap-2 rounded-md border border-dashed border-sidebar-border px-2 py-2"
        >
            <p class="text-muted-foreground text-xs">
                {{ t.shell.no_budget_year }}
            </p>
            <Button
                variant="outline"
                size="sm"
                class="h-8 w-full justify-start gap-2"
                disabled
            >
                <CalendarPlus class="size-4" />
                {{ t.shell.add_budget_year }}
            </Button>
        </div>
        <div v-else class="px-2 text-sm font-medium">
            {{ shell.budgetYear }}
        </div>
    </div>
</template>
