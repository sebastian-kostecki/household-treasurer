<script setup lang="ts">
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';

const t = useTranslations();

const spendableLiquidity = 24_850_00;
const totalFunds = 42_350_00;

const meters = [
    {
        key: 'food',
        labelKey: 'preview_category_food' as const,
        remaining: 420_00,
        percent: 72,
        over: false,
    },
    {
        key: 'transport',
        labelKey: 'preview_category_transport' as const,
        remaining: -85_00,
        percent: 100,
        over: true,
    },
    {
        key: 'salary',
        labelKey: 'preview_category_salary' as const,
        remaining: 3_200_00,
        percent: 58,
        over: false,
    },
];
</script>

<template>
    <div
        aria-hidden="true"
        class="bg-card border-border/60 overflow-hidden rounded-2xl border shadow-sm"
    >
        <div class="flex min-h-[22rem]">
            <aside
                class="border-border/60 bg-sidebar hidden w-14 shrink-0 flex-col gap-2 border-r p-3 sm:flex"
            >
                <div class="bg-sidebar-primary mb-2 size-7 rounded-md" />
                <div
                    v-for="dot in 5"
                    :key="dot"
                    class="size-2 rounded-full"
                    :class="
                        dot === 1
                            ? 'bg-sidebar-primary'
                            : 'bg-sidebar-foreground/60'
                    "
                />
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <div
                    class="border-border/60 flex items-center justify-between border-b px-4 py-3"
                >
                    <span class="text-sm font-semibold">
                        {{ t.nav.dashboard }}
                    </span>
                    <span
                        class="bg-accent text-accent-foreground rounded-xl px-2.5 py-1 text-xs font-medium"
                    >
                        {{ t.landing.preview_year }}
                    </span>
                </div>

                <div class="flex flex-1 flex-col gap-3 p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div
                            class="bg-background border-border/60 rounded-2xl border p-3"
                        >
                            <p class="text-muted-foreground text-xs">
                                {{ t.dashboard.spendable_liquidity }}
                            </p>
                            <p
                                class="text-success mt-1 text-xl font-semibold tabular-nums"
                            >
                                {{ formatMoney(spendableLiquidity) }}
                            </p>
                        </div>
                        <div
                            class="bg-background border-border/60 rounded-2xl border p-3"
                        >
                            <p class="text-muted-foreground text-xs">
                                {{ t.dashboard.total_funds }}
                            </p>
                            <p
                                class="text-success mt-1 text-xl font-semibold tabular-nums"
                            >
                                {{ formatMoney(totalFunds) }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="bg-background border-border/60 flex-1 space-y-3 rounded-2xl border p-3"
                    >
                        <p class="text-xs font-semibold">
                            {{ t.dashboard.expenses }}
                        </p>
                        <div
                            v-for="meter in meters"
                            :key="meter.key"
                            class="space-y-1.5"
                        >
                            <div
                                class="flex items-center justify-between gap-2 text-xs"
                            >
                                <span class="truncate font-medium">
                                    {{ t.landing[meter.labelKey] }}
                                </span>
                                <span
                                    class="hidden tabular-nums sm:inline"
                                    :class="
                                        meter.over
                                            ? 'text-destructive'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ formatMoney(meter.remaining) }}
                                </span>
                            </div>
                            <div
                                class="bg-muted h-1.5 overflow-hidden rounded-full"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :class="
                                        meter.over
                                            ? 'bg-destructive'
                                            : 'bg-primary'
                                    "
                                    :style="{ width: `${meter.percent}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
