<script setup lang="ts">
import CreateBudgetDialog from '@/components/CreateBudgetDialog.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';

const open = defineModel<boolean>('open', { required: true });

const t = useTranslations();
</script>

<template>
    <div class="flex flex-col gap-4">
        <h1 class="text-lg font-semibold">{{ t.budget.title }}</h1>
        <IndexEmptyState
            :title="t.budget.no_budget"
            :description="t.budget.no_budget_description"
        >
            <template #actions>
                <CreateBudgetDialog
                    v-model:open="open"
                    year-input-id="budget-page-create-budget-year"
                >
                    <template #trigger>
                        <Button>{{ t.budget.create_budget }}</Button>
                    </template>
                </CreateBudgetDialog>
            </template>
        </IndexEmptyState>
    </div>
</template>
