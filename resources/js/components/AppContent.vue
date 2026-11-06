<script setup lang="ts">
import { computed } from 'vue';
import AppMobileBar from '@/components/AppMobileBar.vue';
import { SidebarInset, useSidebar } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
    class?: string;
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});
const className = computed(() => props.class);

// The sidebar variant always sits inside SidebarProvider (AppShell); the phone
// bar follows the provider's own breakpoint, so it never disagrees with the sheet.
const sidebar = props.variant === 'sidebar' ? useSidebar() : null;
</script>

<template>
    <SidebarInset v-if="props.variant === 'sidebar'" :class="className">
        <AppMobileBar v-if="sidebar?.isMobile.value" />
        <slot />
    </SidebarInset>
    <main
        v-else
        class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl"
        :class="className"
    >
        <slot />
    </main>
</template>
