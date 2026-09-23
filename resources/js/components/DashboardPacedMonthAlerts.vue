<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import { computed, ref } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';

export type PacedCategoryMonthAlert = {
    id: number;
    name: string;
    icon: string;
    color: string;
    month_remaining_minor_units: number;
};

const VISIBLE_ALERT_LIMIT = 6;

const props = defineProps<{
    alerts: PacedCategoryMonthAlert[];
}>();

const t = useTranslations();
const isExpanded = ref(false);
const headingId = 'paced-month-alerts-heading';

const hiddenAlertCount = computed(() =>
    Math.max(props.alerts.length - VISIBLE_ALERT_LIMIT, 0),
);

const visibleAlerts = computed(() => {
    if (isExpanded.value || props.alerts.length <= VISIBLE_ALERT_LIMIT) {
        return props.alerts;
    }

    return props.alerts.slice(0, VISIBLE_ALERT_LIMIT);
});

const showExpander = computed(
    () => hiddenAlertCount.value > 0 && !isExpanded.value,
);

const showCollapse = computed(
    () => isExpanded.value && props.alerts.length > VISIBLE_ALERT_LIMIT,
);
</script>

<template>
    <section class="space-y-3" :aria-labelledby="headingId">
        <h2
            :id="headingId"
            class="flex items-center gap-2 text-sm font-semibold"
        >
            <AlertTriangle
                class="text-destructive size-4 shrink-0"
                aria-hidden="true"
            />
            <span>{{ t.dashboard.paced_month_alerts_title }}</span>
            <span class="text-muted-foreground tabular-nums">
                · {{ alerts.length }}
            </span>
        </h2>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Card v-for="alert in visibleAlerts" :key="alert.id">
                <CardHeader class="space-y-3 pb-2">
                    <CategoryChip
                        :name="alert.name"
                        :icon="alert.icon"
                        :color="alert.color"
                    />
                    <div class="space-y-1">
                        <p class="text-muted-foreground text-xs">
                            {{ t.dashboard.month_remaining }}
                        </p>
                        <CardTitle
                            class="text-destructive text-lg tabular-nums"
                        >
                            {{ formatMoney(alert.month_remaining_minor_units) }}
                        </CardTitle>
                    </div>
                </CardHeader>
            </Card>
        </div>

        <div v-if="showExpander || showCollapse">
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="isExpanded = !isExpanded"
            >
                {{
                    showCollapse
                        ? t.dashboard.show_fewer_categories
                        : t.dashboard.show_more_categories.replace(
                              ':count',
                              String(hiddenAlertCount),
                          )
                }}
            </Button>
        </div>
    </section>
</template>
