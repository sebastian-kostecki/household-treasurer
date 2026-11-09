<script setup lang="ts">
/**
 * Content of the card footer (`.auth-card-foot`) of the entry layout. The page
 * renders inside the layout's body slot, so it cannot fill a named slot of the
 * layout; it teleports into the footer element instead.
 */
</script>

<template>
    <Teleport defer to="#auth-card-foot">
        <slot />
    </Teleport>
</template>
