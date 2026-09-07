<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
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
import { accounts } from '@/routes';

const t = useTranslations();

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.accounts,
            href: accounts(),
        },
    ],
});
</script>

<template>
    <Head :title="t.accounts.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold">{{ t.accounts.title }}</h1>
            <Button disabled :title="t.accounts.add_hint">
                {{ t.accounts.add }}
            </Button>
        </div>

        <div class="flex items-center gap-2">
            <Checkbox id="show-archived-accounts" disabled />
            <Label
                for="show-archived-accounts"
                class="text-muted-foreground text-sm"
            >
                {{ t.accounts.show_archived }}
            </Label>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{ t.accounts.title }}</CardTitle>
                <CardDescription>{{ t.accounts.empty }}</CardDescription>
            </CardHeader>
            <CardContent>
                <p class="text-muted-foreground text-sm">
                    {{ t.accounts.empty }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
