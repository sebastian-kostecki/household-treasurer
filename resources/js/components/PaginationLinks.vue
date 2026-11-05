<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

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

const { paginator } = defineProps<{
    paginator: PaginatedCollection<unknown>;
}>();

const t = useTranslations();

// Laravel puts "previous" first and "next" last; the page numbers are in between.
const previous = computed(() => paginator.links[0] ?? null);
const next = computed(
    () => paginator.links[paginator.links.length - 1] ?? null,
);
const pages = computed(() => paginator.links.slice(1, -1));
</script>

<template>
    <nav
        v-if="paginator.last_page > 1"
        class="flex flex-wrap items-center justify-center gap-1"
        :aria-label="t.common.pagination"
    >
        <component
            :is="previous?.url ? Link : 'span'"
            :href="previous?.url ?? undefined"
            :preserve-scroll="previous?.url ? true : undefined"
            class="pagination-link"
            :aria-label="t.common.pagination_previous"
            :aria-disabled="previous?.url ? undefined : 'true'"
        >
            <ChevronLeft class="size-4" aria-hidden="true" />
        </component>

        <template
            v-for="(link, index) in pages"
            :key="`${index}-${link.label}`"
        >
            <Link
                v-if="link.url && !link.active"
                :href="link.url"
                :preserve-scroll="true"
                class="pagination-link"
            >
                {{ link.label }}
            </Link>
            <span
                v-else
                class="pagination-link"
                :aria-current="link.active ? 'page' : undefined"
            >
                {{ link.label }}
            </span>
        </template>

        <component
            :is="next?.url ? Link : 'span'"
            :href="next?.url ?? undefined"
            :preserve-scroll="next?.url ? true : undefined"
            class="pagination-link"
            :aria-label="t.common.pagination_next"
            :aria-disabled="next?.url ? undefined : 'true'"
        >
            <ChevronRight class="size-4" aria-hidden="true" />
        </component>
    </nav>
</template>
