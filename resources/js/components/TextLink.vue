<script setup lang="ts">
import type { LinkComponentBaseProps, Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

type Props = {
    href?: LinkComponentBaseProps['href'];
    method?: Method;
    as?: string;
};

const props = defineProps<Props>();

/**
 * `as="button"` with an href is an Inertia Link rendered as a button (a POST
 * such as logout); without an href it is a plain button (a mode switch).
 */
const isPlainButton = props.as === 'button' && props.href === undefined;

const linkClass =
    'text-brand-text focus-visible:ring-ring rounded-xs font-medium outline-none hover:underline focus-visible:ring-2 underline-offset-4';
</script>

<template>
    <button v-if="isPlainButton" type="button" :class="linkClass">
        <slot />
    </button>
    <Link
        v-else
        :href="href as LinkComponentBaseProps['href']"
        :method="method"
        :as="as"
        :class="linkClass"
    >
        <slot />
    </Link>
</template>
