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

const props = defineProps<{
    title?: string;
    description?: string;
    paginator?: PaginatedCollection<unknown>;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <Card :class="cn(props.class)">
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
            <PaginationLinks v-if="paginator" :paginator="paginator" />
            <slot name="footer" />
        </CardContent>
    </Card>
</template>
