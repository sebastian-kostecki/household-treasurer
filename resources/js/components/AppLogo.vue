<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import BrandMark from '@/components/BrandMark.vue';

const name = usePage().props.name;
</script>

<template>
    <BrandMark />
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span
            class="text-sidebar-strong mb-0.5 truncate leading-tight font-semibold"
            >{{ name }}</span
        >
    </div>
</template>
