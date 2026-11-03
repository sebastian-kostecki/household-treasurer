<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import InputError from '@/components/InputError.vue';
import MonthYearSummary from '@/components/MonthYearSummary.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney, formatSignedMoney } from '@/lib/format';
import type { MonthItemKind, MonthPlanItem } from '@/types/month-plan';

const props = withDefaults(
    defineProps<{
        row: MonthPlanItem;
        editable: boolean;
        deviation: number | null;
        overPlan: boolean;
        yearStale?: boolean;
        kindPending?: boolean;
        kindError?: string;
        invalid?: boolean;
    }>(),
    { yearStale: false, kindPending: false, invalid: false },
);

const emit = defineEmits<{
    'change-kind': [kind: MonthItemKind];
    'amount-blur': [];
}>();

const amountErrorId = computed(
    () => `month-plan-amount-error-${props.row.category_id}`,
);

const amountInput = defineModel<string>();

const t = useTranslations();

const kindOptions = computed<{ value: MonthItemKind; label: string }[]>(() => [
    { value: 'bill', label: t.month_plan.kind_bill },
    { value: 'running', label: t.month_plan.kind_running },
]);

const hasKindColumn = computed(() => props.row.type === 'expense');

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

function onKindUpdate(kind: MonthItemKind): void {
    emit('change-kind', kind);
}
</script>

<template>
    <div
        class="month-plan-grid border-border/60 items-center border-b py-3 last:border-b-0"
        :class="hasKindColumn ? 'month-plan-grid-kind' : ''"
    >
        <div class="flex min-w-0 flex-col gap-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <CategoryChip
                    :name="row.name"
                    :icon="row.icon"
                    :color="row.color"
                />
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
                v-if="row.year_comparison !== null && !row.is_idle_one_off"
                class="text-muted-foreground text-xs"
            >
                <span :class="yearStale ? 'opacity-60' : ''">
                    {{ t.month_plan.year_line }}:
                    <MonthYearSummary
                        :comparison="row.year_comparison"
                        :type="row.type"
                    />
                </span>
                <span v-if="yearStale">
                    · {{ t.month_plan.year_stale_hint }}
                </span>
            </p>

            <p
                v-if="
                    row.available_limit_minor_units !== null &&
                    (row.year_comparison === null || row.is_idle_one_off)
                "
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

            <div v-if="row.month_item_kind !== null" class="sm:hidden">
                <div v-if="row.can_change_kind" class="grid gap-1">
                    <div class="flex items-center gap-2">
                        <SegmentedControl
                            :model-value="row.month_item_kind"
                            :options="kindOptions"
                            :ariaLabel="t.month_plan.kind"
                            tone="neutral"
                            :disabled="kindPending"
                            @update:model-value="onKindUpdate"
                        />
                        <Spinner v-if="kindPending" />
                    </div>
                    <InputError :message="kindError" />
                </div>
                <span v-else class="text-muted-foreground text-sm">
                    {{ kindLabel(row.month_item_kind) }}
                </span>
            </div>
        </div>

        <div v-if="hasKindColumn" class="hidden sm:block">
            <template v-if="row.month_item_kind !== null">
                <div v-if="row.can_change_kind" class="grid gap-1">
                    <div class="flex items-center gap-2">
                        <SegmentedControl
                            :model-value="row.month_item_kind"
                            :options="kindOptions"
                            :ariaLabel="t.month_plan.kind"
                            tone="neutral"
                            :disabled="kindPending"
                            @update:model-value="onKindUpdate"
                        />
                        <Spinner v-if="kindPending" />
                    </div>
                    <InputError :message="kindError" />
                </div>
                <span v-else class="text-muted-foreground text-sm">
                    {{ kindLabel(row.month_item_kind) }}
                </span>
            </template>
        </div>

        <div class="text-right">
            <Input
                v-if="editable && (row.in_plan || !row.is_archived)"
                v-model="amountInput"
                inputmode="decimal"
                autocomplete="off"
                :aria-label="`${t.month_plan.plan}: ${row.name}`"
                :aria-invalid="invalid ? 'true' : undefined"
                :aria-describedby="invalid ? amountErrorId : undefined"
                class="text-right tabular-nums"
                @blur="emit('amount-blur')"
            />
            <span v-else class="text-sm tabular-nums">
                {{ formatMoney(row.plan_minor_units) }}
            </span>
            <InputError
                v-if="editable && invalid"
                :id="amountErrorId"
                class="text-right"
                :message="t.month_plan.amount_invalid"
            />
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
