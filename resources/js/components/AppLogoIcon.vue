<script setup lang="ts">
import { Vault } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <Vault :class="className" v-bind="$attrs" />
</template>
