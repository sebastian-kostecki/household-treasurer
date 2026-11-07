<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import BrandMark from '@/components/BrandMark.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard } from '@/routes';

const t = useTranslations();
const name = usePage().props.name;
</script>

<template>
    <header
        class="border-border bg-background flex h-12 shrink-0 items-center gap-2 border-b px-4"
        data-slot="mobile-bar"
    >
        <SidebarTrigger class="-ml-1" :aria-label="t.shell.open_menu" />
        <Link
            :href="dashboard()"
            class="text-foreground flex min-w-0 items-center gap-2 font-semibold"
        >
            <BrandMark />
            <span class="truncate">{{ name }}</span>
        </Link>
    </header>
</template>
