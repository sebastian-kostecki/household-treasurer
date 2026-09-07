<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';
import { operations } from '@/routes';

const t = useTranslations();

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.operations,
            href: operations(),
        },
    ],
});
</script>

<template>
    <Head :title="t.operations.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold">{{ t.operations.title }}</h1>
            <Button disabled :title="t.operations.import_hint">
                <Upload class="mr-2 size-4" />
                {{ t.operations.import_csv }}
            </Button>
        </div>

        <Card class="hidden md:block">
            <CardHeader>
                <CardTitle class="text-base">
                    {{ t.operations.title }}
                </CardTitle>
                <CardDescription>{{ t.operations.empty }}</CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="pb-2 font-medium">
                                {{ t.operations.date }}
                            </th>
                            <th class="pb-2 font-medium">
                                {{ t.operations.description }}
                            </th>
                            <th class="pb-2 font-medium">
                                {{ t.operations.type }}
                            </th>
                            <th class="pb-2 text-right font-medium">
                                {{ t.operations.amount }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td
                                colspan="4"
                                class="text-muted-foreground py-6 text-center"
                            >
                                {{ t.operations.empty }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div class="space-y-3 md:hidden">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>
                        {{ t.operations.empty }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="text-muted-foreground text-sm">
                    {{ formatMoney(0) }}
                </CardContent>
            </Card>
        </div>
    </div>
</template>
