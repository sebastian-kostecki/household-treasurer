<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import PaginationLinks, {
    type PaginatedCollection,
} from '@/components/PaginationLinks.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * `flush`: the rows reach the edges of the card and carry their own horizontal
 * padding; the header slot sits in a bordered bar above them (Operations list).
 */
const props = defineProps<{
    title?: string;
    description?: string;
    paginator?: PaginatedCollection<unknown>;
    flush?: boolean;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <Card v-if="flush" :class="cn('gap-0 overflow-hidden py-0', props.class)">
        <div v-if="$slots.header" class="border-b px-4 py-3">
            <slot name="header" />
        </div>
        <slot />
        <!-- The last row's bottom line separates the rows from the pagination and the footer. -->
        <div v-if="paginator && paginator.last_page > 1" class="px-4 py-3">
            <PaginationLinks :paginator="paginator" />
        </div>
        <div v-if="$slots.footer" class="px-4 py-3">
            <slot name="footer" />
        </div>
    </Card>

    <Card v-else :class="cn(props.class)">
        <CardHeader v-if="title || description || $slots.header" class="pb-4">
            <slot name="header">
                <CardTitle v-if="title" class="text-base">
                    {{ title }}
                </CardTitle>
                <CardDescription v-if="description">
                    {{ description }}
                </CardDescription>
            </slot>
        </CardHeader>
        <CardContent
            :class="title || description || $slots.header ? '' : 'pt-6'"
        >
            <slot />
            <div
                v-if="paginator && paginator.last_page > 1"
                class="border-t pt-4"
            >
                <PaginationLinks :paginator="paginator" />
            </div>
            <slot name="footer" />
        </CardContent>
    </Card>
</template>
