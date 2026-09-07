<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { budget } from '@/routes';

const t = useTranslations();

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.budget,
            href: budget(),
        },
    ],
});
</script>

<template>
    <Head :title="t.budget.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold">{{ t.budget.title }}</h1>
                <Badge variant="secondary">{{ t.budget.phase_draft }}</Badge>
            </div>
            <Button disabled :title="t.budget.start_execution_hint">
                {{ t.budget.start_execution }}
            </Button>
        </div>

        <div class="flex items-center gap-2">
            <Checkbox id="show-archived-budget" disabled />
            <Label
                for="show-archived-budget"
                class="text-muted-foreground text-sm"
            >
                {{ t.budget.show_archived }}
            </Label>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ t.budget.income_table }}
                    </CardTitle>
                    <CardDescription>{{ t.budget.empty }}</CardDescription>
                </CardHeader>
                <CardContent class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="pb-2 font-medium">
                                    {{ t.budget.category }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ t.budget.initial_plan }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ t.budget.current_plan }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ t.budget.execution }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td
                                    colspan="4"
                                    class="text-muted-foreground py-6 text-center"
                                >
                                    {{ t.budget.empty }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ t.budget.expense_table }}
                    </CardTitle>
                    <CardDescription>{{ t.budget.empty }}</CardDescription>
                </CardHeader>
                <CardContent class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="pb-2 font-medium">
                                    {{ t.budget.category }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ t.budget.initial_plan }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ t.budget.current_plan }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ t.budget.execution }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td
                                    colspan="4"
                                    class="text-muted-foreground py-6 text-center"
                                >
                                    {{ t.budget.empty }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
