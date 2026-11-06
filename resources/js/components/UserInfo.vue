<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
    variant?: 'default' | 'sidebar';
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    variant: 'default',
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);
</script>

<template>
    <Avatar
        class="overflow-hidden"
        :class="
            variant === 'sidebar'
                ? 'size-7 group-data-[collapsible=icon]:mx-auto'
                : 'size-8'
        "
    >
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback
            :class="
                variant === 'sidebar'
                    ? 'bg-sidebar-accent text-sidebar-accent-foreground meta font-semibold'
                    : 'text-foreground'
            "
        >
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div
        class="grid flex-1 text-left text-sm leading-tight"
        :class="variant === 'sidebar' && 'group-data-[collapsible=icon]:sr-only'"
    >
        <span class="truncate font-medium">{{ user.name }}</span>
        <span
            v-if="variant === 'sidebar'"
            class="meta text-sidebar-foreground truncate"
            >{{ user.email }}</span
        >
        <span
            v-else-if="showEmail"
            class="text-muted-foreground truncate text-xs"
            >{{ user.email }}</span
        >
    </div>
</template>
