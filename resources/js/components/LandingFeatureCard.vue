<script setup lang="ts">
import type { Component } from 'vue';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineProps<{
    icon: Component;
    title: string;
    description: string;
}>();
</script>

<template>
    <Card class="h-full">
        <CardHeader class="gap-4">
            <div
                class="bg-accent text-accent-foreground flex size-10 items-center justify-center rounded-xl"
            >
                <component :is="icon" class="size-5" />
            </div>
            <div class="space-y-1.5">
                <CardTitle class="text-base">{{ title }}</CardTitle>
                <CardDescription class="text-sm leading-relaxed">
                    {{ description }}
                </CardDescription>
            </div>
        </CardHeader>
    </Card>
</template>
