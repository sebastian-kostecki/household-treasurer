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
        class="border-border/60 flex flex-wrap items-center justify-center gap-0.5 border-t pt-4"
        aria-label="Pagination"
    >
        <template
            v-for="link in paginator.links"
            :key="`${link.label}-${link.active}`"
        >
            <Link
                v-if="link.url"
                :href="link.url"
                :preserve-scroll="true"
                class="pagination-link"
                :class="
                    link.active
                        ? 'bg-muted text-foreground font-medium'
                        : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'
                "
                :aria-current="link.active ? 'page' : undefined"
                v-html="link.label"
            />
            <span
                v-else
                class="pagination-link"
                :class="
                    link.active
                        ? 'bg-muted text-foreground font-medium'
                        : 'text-muted-foreground/50'
                "
                :aria-current="link.active ? 'page' : undefined"
                :aria-disabled="
                    link.active || link.label === '...' ? undefined : 'true'
                "
                v-html="link.label"
            />
        </template>
    </nav>
</template>
