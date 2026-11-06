<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import BrandMark from '@/components/BrandMark.vue';

const name = usePage().props.name;
</script>

<template>
    <BrandMark class="group-data-[collapsible=icon]:mx-auto" />
    <div
        class="ml-1 grid flex-1 text-left text-sm group-data-[collapsible=icon]:sr-only"
    >
        <span
            class="text-sidebar-strong mb-0.5 truncate leading-tight font-semibold"
            >{{ name }}</span
        >
    </div>
</template>
