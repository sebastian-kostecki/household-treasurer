<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check, Circle } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    useFirstRunChecklist,
    useTranslations,
} from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';
import { dashboard } from '@/routes';

const t = useTranslations();
const checklist = useFirstRunChecklist();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: t.nav.dashboard,
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t.dashboard.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-base">
                    {{ t.dashboard.checklist_title }}
                </CardTitle>
                <CardDescription>
                    {{ t.dashboard.checklist_description }}
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-2">
                <div
                    v-for="item in checklist"
                    :key="item.key"
                    class="flex items-center gap-3 rounded-md border px-3 py-2 text-sm"
                >
                    <Check
                        v-if="item.done"
                        class="text-success size-4 shrink-0"
                    />
                    <Circle
                        v-else
                        class="text-muted-foreground size-4 shrink-0"
                    />
                    <span
                        :class="
                            item.done
                                ? 'text-muted-foreground line-through'
                                : ''
                        "
                    >
                        {{ t.dashboard[item.labelKey] }}
                    </span>
                </div>
            </CardContent>
        </Card>

        <section class="space-y-3">
            <h2 class="text-sm font-semibold">
                {{ t.dashboard.liquidity_title }}
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>
                            {{ t.dashboard.total_funds }}
                        </CardDescription>
                        <CardTitle class="text-success text-2xl">
                            {{ formatMoney(0) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card class="sm:col-span-1 xl:col-span-2">
                    <CardHeader class="pb-2">
                        <CardDescription>
                            {{ t.dashboard.no_accounts }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-muted-foreground text-sm">
                            {{ t.accounts.empty }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-sm font-semibold">
                {{ t.dashboard.execution_title }}
            </h2>
            <div class="grid gap-3 lg:grid-cols-2">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">
                            {{ t.dashboard.income }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-muted-foreground text-sm">
                            {{ t.dashboard.no_categories }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">
                            {{ t.dashboard.expenses }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p class="text-muted-foreground text-sm">
                            {{ t.dashboard.no_categories }}
                        </p>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-muted-foreground">
                                    {{ t.dashboard.planned }}:
                                    {{ formatMoney(0) }}
                                </span>
                                <Badge variant="secondary">
                                    {{ t.common.not_applicable }}
                                </Badge>
                            </div>
                            <div
                                class="bg-muted h-2 overflow-hidden rounded-full"
                            >
                                <div
                                    class="bg-primary h-full w-0 rounded-full"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>
    </div>
</template>
