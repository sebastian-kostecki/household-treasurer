<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney, formatSignedMoney } from '@/lib/format';
import type { MonthSummary } from '@/types/month-plan';

const props = defineProps<{
    summary: MonthSummary;
}>();

const t = useTranslations();

const totalsRows = [
    { key: 'income', label: t.month_plan.summary_income },
    { key: 'expense', label: t.month_plan.summary_expense },
] as const;

function setAsideLabel(minorUnits: number): string {
    const verb =
        minorUnits < 0
            ? t.month_plan.set_aside_draw
            : t.month_plan.set_aside_save;

    return `${verb} ${formatMoney(Math.abs(minorUnits))}`;
}
</script>

<template>
    <Card>
        <CardContent class="grid gap-5 pt-6">
            <p
                class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
            >
                {{ t.month_plan.summary_title }}
            </p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm tabular-nums">
                    <thead>
                        <tr class="text-muted-foreground text-xs">
                            <th class="py-1 pr-2 text-left font-medium"></th>
                            <th class="px-2 py-1 text-right font-medium">
                                {{ t.month_plan.summary_approved }}
                            </th>
                            <th class="px-2 py-1 text-right font-medium">
                                {{ t.month_plan.summary_final }}
                            </th>
                            <th class="px-2 py-1 text-right font-medium">
                                {{ t.month_plan.summary_actual }}
                            </th>
                            <th class="py-1 pl-2 text-right font-medium">
                                {{ t.month_plan.summary_deviation }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in totalsRows"
                            :key="row.key"
                            class="border-border/60 border-t"
                        >
                            <th class="py-2 pr-2 text-left font-medium">
                                {{ row.label }}
                            </th>
                            <td class="px-2 py-2 text-right">
                                {{
                                    formatMoney(
                                        props.summary[row.key]
                                            .approved_minor_units,
                                    )
                                }}
                            </td>
                            <td class="px-2 py-2 text-right">
                                {{
                                    formatMoney(
                                        props.summary[row.key]
                                            .final_minor_units,
                                    )
                                }}
                            </td>
                            <td class="px-2 py-2 text-right">
                                {{
                                    formatMoney(
                                        props.summary[row.key]
                                            .actual_minor_units,
                                    )
                                }}
                            </td>
                            <td class="py-2 pl-2 text-right">
                                {{
                                    formatSignedMoney(
                                        props.summary[row.key]
                                            .deviation_minor_units,
                                    )
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-2">
                <p
                    class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                >
                    {{ t.month_plan.summary_set_aside }}
                </p>
                <dl class="grid gap-1 text-sm tabular-nums">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">
                            {{ t.month_plan.summary_set_aside_approved }}
                        </dt>
                        <dd>
                            {{
                                setAsideLabel(
                                    props.summary.set_aside
                                        .approved_minor_units,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">
                            {{ t.month_plan.summary_set_aside_final }}
                        </dt>
                        <dd>
                            {{
                                setAsideLabel(
                                    props.summary.set_aside.final_minor_units,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">
                            {{ t.month_plan.summary_set_aside_real }}
                        </dt>
                        <dd>
                            {{
                                setAsideLabel(
                                    props.summary.set_aside.real_minor_units,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 font-medium">
                        <dt>{{ t.month_plan.summary_set_aside_difference }}</dt>
                        <dd>
                            {{
                                formatSignedMoney(
                                    props.summary.set_aside
                                        .difference_minor_units,
                                )
                            }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="flex justify-between gap-4 text-sm tabular-nums">
                <span class="text-muted-foreground">
                    {{ t.month_plan.summary_year_draw }}
                </span>
                <span class="font-medium">
                    {{ formatMoney(props.summary.year_draw_minor_units) }}
                </span>
            </div>

            <p class="text-muted-foreground text-xs">
                {{ t.month_plan.summary_hint }}
            </p>
        </CardContent>
    </Card>
</template>
