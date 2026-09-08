<script setup lang="ts">
import { formatDate, formatRelativeDate } from '@/lib/format';

defineProps<{
    date: string;
}>();
</script>

<template>
    <div class="min-w-0">
        <p class="text-sm font-medium">{{ formatDate(date) }}</p>
        <p class="text-muted-foreground text-xs">
            {{ formatRelativeDate(date) }}
        </p>
    </div>
</template>
