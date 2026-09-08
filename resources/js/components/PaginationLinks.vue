<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedCollection<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

defineProps<{
    paginator: PaginatedCollection<unknown>;
}>();
</script>

<template>
    <nav
        v-if="paginator.last_page > 1"
        class="flex flex-wrap items-center justify-center gap-0.5 border-t border-border/60 pt-4"
        aria-label="Pagination"
    >
        <Link
            v-for="link in paginator.links"
            :key="`${link.label}-${link.active}`"
            :href="link.url ?? '#'"
            :preserve-scroll="true"
            class="inline-flex min-w-8 items-center justify-center rounded-lg px-2.5 py-1 text-xs transition-colors"
            :class="
                link.active
                    ? 'bg-muted font-medium text-foreground'
                    : link.url
                      ? 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'
                      : 'text-muted-foreground/50 pointer-events-none'
            "
            :aria-current="link.active ? 'page' : undefined"
            v-html="link.label"
        />
    </nav>
</template>
