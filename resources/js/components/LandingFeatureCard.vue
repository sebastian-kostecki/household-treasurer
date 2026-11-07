<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    icon: Component;
    title: string;
    description: string;
}>();
</script>

<template>
    <article class="landing-feature">
        <div class="landing-icon">
            <component :is="icon" class="size-4" aria-hidden="true" />
        </div>
        <h3 class="card-title">{{ title }}</h3>
        <p class="text-muted-foreground text-sm">{{ description }}</p>
    </article>
</template>
