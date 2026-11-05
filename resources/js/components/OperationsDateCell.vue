<script setup lang="ts">
import { formatDate } from '@/lib/format';

defineProps<{
    date: string;
}>();
</script>

<template>
    <p class="text-muted-foreground font-mono text-sm tabular-nums">
        {{ formatDate(date) }}
    </p>
</template>
