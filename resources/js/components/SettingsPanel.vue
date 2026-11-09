<script setup lang="ts">
import { useId } from 'vue';

defineProps<{
    title: string;
    description?: string;
}>();

const titleId = useId();
</script>

<template>
    <section class="settings-panel" :aria-labelledby="titleId">
        <header class="settings-panel-head">
            <h2 :id="titleId" class="settings-panel-title">{{ title }}</h2>
            <p v-if="description" class="settings-panel-description">
                {{ description }}
            </p>
        </header>
        <div class="settings-panel-body">
            <slot />
        </div>
        <footer v-if="$slots.footer" class="settings-panel-foot">
            <slot name="footer" />
        </footer>
    </section>
</template>
