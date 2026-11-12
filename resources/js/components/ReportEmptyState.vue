<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { budget as budgetRoute } from '@/routes';

/**
 * The Reports page without a report: no Budget for the year, or a Budget still
 * in draft. Shared by the page and its kitchen sink, so both show the same
 * markup with the way back to the Budget.
 */
const props = defineProps<{
    kind: 'no-budget' | 'draft';
}>();

const t = useTranslations();

const copy = computed(() =>
    props.kind === 'no-budget'
        ? {
              title: t.reports.no_budget_title,
              description: t.reports.no_budget_description,
          }
        : {
              title: t.reports.draft_title,
              description: t.reports.draft_description,
          },
);
</script>

<template>
    <IndexEmptyState :title="copy.title" :description="copy.description">
        <template #actions>
            <Button as-child>
                <Link :href="budgetRoute()">
                    {{ t.reports.go_to_budget }}
                </Link>
            </Button>
        </template>
    </IndexEmptyState>
</template>
