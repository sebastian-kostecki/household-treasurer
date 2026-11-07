<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import type { Component } from 'vue';
import BrandMark from '@/components/BrandMark.vue';
import { useTranslations } from '@/composables/useTranslations';
import { authYearProgress } from '@/lib/auth-year-progress';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
    icon?: Component;
}>();

const t = useTranslations();
const name = usePage().props.name;
const yearProgress = authYearProgress(new Date());
const points = [
    t.auth.aside.point_plan,
    t.auth.aside.point_operations,
    t.auth.aside.point_to_spend,
];
</script>

<template>
    <div class="auth-shell">
        <aside class="auth-aside">
            <Link :href="home()" class="flex items-center gap-2">
                <BrandMark />
                <strong class="font-semibold">{{ name }}</strong>
            </Link>

            <div class="auth-pitch">
                <h2>{{ t.auth.aside.pitch }}</h2>
                <p>{{ t.auth.aside.lead }}</p>
            </div>

            <div class="auth-year">
                <div class="auth-year-head">
                    <span>{{ t.auth.aside.year_label }}</span>
                    <strong
                        >{{ yearProgress.year }} ·
                        {{ yearProgress.elapsedPercent }}%</strong
                    >
                </div>
                <div class="auth-months">
                    <span
                        v-for="(state, index) in yearProgress.months"
                        :key="index"
                        :data-state="state"
                        aria-hidden="true"
                    />
                </div>
                <span class="sr-only">
                    {{ t.auth.aside.progress_label }}:
                    {{ yearProgress.elapsedPercent }}%
                </span>
            </div>

            <ul class="auth-points">
                <li v-for="point in points" :key="point">
                    <Check class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <span>{{ point }}</span>
                </li>
            </ul>

            <p class="auth-legal">
                {{
                    t.auth.aside.legal.replace(
                        ':year',
                        String(yearProgress.year),
                    )
                }}
            </p>
        </aside>

        <main class="auth-main">
            <div class="auth-card">
                <div class="auth-card-head">
                    <div v-if="icon" class="auth-card-icon">
                        <component
                            :is="icon"
                            class="size-5"
                            aria-hidden="true"
                        />
                    </div>
                    <h1 class="page-title">{{ title }}</h1>
                    <p class="meta">{{ description }}</p>
                </div>
                <div class="auth-card-body">
                    <slot />
                </div>
                <div id="auth-card-foot" class="auth-card-foot empty:hidden" />
            </div>
        </main>
    </div>
</template>
