<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney, formatSignedMoney } from '@/lib/format';
import type { MonthItemKind, MonthPlanItem } from '@/types/month-plan';

const props = defineProps<{
    row: MonthPlanItem;
    editable: boolean;
    deviation: number | null;
    overPlan: boolean;
}>();

const emit = defineEmits<{
    'change-kind': [kind: MonthItemKind];
}>();

const amountInput = defineModel<string>();

const t = useTranslations();

const kinds: MonthItemKind[] = ['bill', 'running'];

function kindLabel(kind: MonthItemKind): string {
    return kind === 'bill' ? t.month_plan.kind_bill : t.month_plan.kind_running;
}

const deviationText = computed(() =>
    props.deviation === null ? '—' : formatSignedMoney(props.deviation),
);

const deviationClass = computed(() => {
    if (props.deviation === null) {
        return 'text-muted-foreground';
    }

    if (props.overPlan) {
        return 'text-destructive';
    }

    if (props.row.type === 'income' && props.deviation > 0) {
        return 'text-success';
    }

    return '';
});
</script>

<template>
    <div
        class="border-border/60 grid grid-cols-[1fr_5.5rem_5.5rem] items-center gap-3 border-b py-3 last:border-b-0 sm:grid-cols-[1fr_9rem_9rem] lg:grid-cols-[1fr_9rem_9rem_9rem]"
    >
        <div class="flex min-w-0 flex-col gap-1">
            <CategoryChip
                :name="row.name"
                :icon="row.icon"
                :color="row.color"
            />
            <div
                v-if="
                    row.is_one_off ||
                    row.is_archived ||
                    !row.in_plan ||
                    row.is_settled
                "
                class="flex flex-wrap gap-1"
            >
                <Badge v-if="row.is_one_off" variant="outline">
                    {{ t.month_plan.one_off_badge }}
                </Badge>
                <Badge v-if="row.is_archived" variant="secondary">
                    {{ t.month_plan.archived_badge }}
                </Badge>
                <Badge v-if="!row.in_plan" variant="outline">
                    {{ t.month_plan.not_in_plan }}
                </Badge>
                <Badge
                    v-if="row.is_settled && row.type === 'expense'"
                    variant="secondary"
                    class="gap-1"
                >
                    <Check class="size-3" aria-hidden="true" />
                    {{ t.month_plan.settled_bill }}
                </Badge>
                <Badge
                    v-if="row.is_settled && row.type === 'income'"
                    variant="outline"
                    class="text-success border-success/40 gap-1"
                >
                    <Check class="size-3" aria-hidden="true" />
                    {{ t.month_plan.settled_income }}
                </Badge>
            </div>

            <p
                v-if="row.available_limit_minor_units !== null"
                class="text-muted-foreground text-xs"
            >
                {{ t.month_plan.available_limit }}:
                <span
                    class="tabular-nums"
                    :class="
                        row.available_limit_minor_units < 0
                            ? 'text-destructive'
                            : ''
                    "
                >
                    {{ formatMoney(row.available_limit_minor_units) }}
                </span>
            </p>

            <div>
                <div
                    v-if="row.month_item_kind !== null && row.can_change_kind"
                    role="group"
                    :aria-label="t.month_plan.kind"
                    class="border-border/60 bg-muted/40 inline-flex gap-1 rounded-xl border p-1"
                >
                    <Button
                        v-for="kind in kinds"
                        :key="kind"
                        type="button"
                        size="sm"
                        :variant="
                            row.month_item_kind === kind ? 'default' : 'ghost'
                        "
                        :aria-pressed="row.month_item_kind === kind"
                        class="h-7 rounded-lg px-2 text-xs"
                        @click="emit('change-kind', kind)"
                    >
                        {{ kindLabel(kind) }}
                    </Button>
                </div>
                <span
                    v-else-if="row.month_item_kind !== null"
                    class="text-muted-foreground text-sm"
                >
                    {{ kindLabel(row.month_item_kind) }}
                </span>
            </div>
        </div>

        <div class="text-right">
            <Input
                v-if="editable && (row.in_plan || !row.is_archived)"
                v-model="amountInput"
                inputmode="decimal"
                autocomplete="off"
                :aria-label="`${t.month_plan.plan}: ${row.name}`"
                class="text-right tabular-nums"
            />
            <span v-else class="text-sm tabular-nums">
                {{ formatMoney(row.plan_minor_units) }}
            </span>
            <p
                v-if="
                    row.approved_minor_units !== null &&
                    row.approved_minor_units !== row.plan_minor_units
                "
                class="text-muted-foreground text-xs tabular-nums"
            >
                {{ t.month_plan.approved_amount }}:
                {{ formatMoney(row.approved_minor_units) }}
            </p>
        </div>

        <div class="flex flex-col items-end">
            <span class="text-sm tabular-nums">
                {{ formatMoney(row.actual_minor_units) }}
            </span>
            <span
                class="text-xs tabular-nums lg:hidden"
                :class="deviationClass"
            >
                <span class="sr-only">{{ t.month_plan.deviation }}: </span>
                {{ deviationText }}
            </span>
        </div>

        <span
            class="hidden text-right text-sm tabular-nums lg:block"
            :class="deviationClass"
        >
            <span class="sr-only">{{ t.month_plan.deviation }}: </span>
            {{ deviationText }}
        </span>
    </div>
</template>
