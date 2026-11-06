<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRightLeft,
    CalendarRange,
    Gauge,
    Landmark,
    Lock,
    Upload,
} from '@lucide/vue';
import { computed } from 'vue';
import BrandMark from '@/components/BrandMark.vue';
import LandingAppPreview from '@/components/LandingAppPreview.vue';
import LandingFeatureCard from '@/components/LandingFeatureCard.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard, login } from '@/routes';

const t = useTranslations();
const page = usePage();
const name = computed(() => page.props.name as string);
const isAuthenticated = computed(() => Boolean(page.props.auth.user));
const canRegister = computed(() => Boolean(page.props.canRegister));
const footerYear = new Date().getFullYear();

const features = [
    {
        icon: CalendarRange,
        titleKey: 'feature_budget_title' as const,
        descriptionKey: 'feature_budget_description' as const,
    },
    {
        icon: Landmark,
        titleKey: 'feature_liquidity_title' as const,
        descriptionKey: 'feature_liquidity_description' as const,
    },
    {
        icon: ArrowRightLeft,
        titleKey: 'feature_operations_title' as const,
        descriptionKey: 'feature_operations_description' as const,
    },
    {
        icon: Upload,
        titleKey: 'feature_import_title' as const,
        descriptionKey: 'feature_import_description' as const,
    },
    {
        icon: Gauge,
        titleKey: 'feature_month_title' as const,
        descriptionKey: 'feature_month_description' as const,
    },
    {
        icon: Lock,
        titleKey: 'feature_privacy_title' as const,
        descriptionKey: 'feature_privacy_description' as const,
    },
];

const steps = [
    {
        number: '1',
        titleKey: 'step_1_title' as const,
        descriptionKey: 'step_1_description' as const,
    },
    {
        number: '2',
        titleKey: 'step_2_title' as const,
        descriptionKey: 'step_2_description' as const,
    },
    {
        number: '3',
        titleKey: 'step_3_title' as const,
        descriptionKey: 'step_3_description' as const,
    },
    {
        number: '4',
        titleKey: 'step_4_title' as const,
        descriptionKey: 'step_4_description' as const,
    },
];

const heroPoints = [
    'hero_point_money',
    'hero_point_import',
    'hero_point_privacy',
] as const;
</script>

<template>
    <Head :title="name">
        <meta
            head-key="description"
            name="description"
            :content="t.landing.meta_description"
        />
    </Head>

    <div class="bg-background text-foreground min-h-screen">
        <header
            class="border-border/60 bg-background/80 sticky top-0 z-40 border-b backdrop-blur"
        >
            <div
                class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6"
            >
                <a href="#top" class="flex items-center gap-2">
                    <BrandMark />
                    <span class="text-sm font-semibold">{{ name }}</span>
                </a>

                <nav class="hidden items-center gap-6 text-sm md:flex">
                    <a
                        href="#features"
                        class="text-muted-foreground hover:text-foreground transition-colors"
                    >
                        {{ t.landing.nav_features }}
                    </a>
                    <a
                        href="#how"
                        class="text-muted-foreground hover:text-foreground transition-colors"
                    >
                        {{ t.landing.nav_how }}
                    </a>
                </nav>

                <div class="flex items-center gap-2">
                    <Button v-if="isAuthenticated" as-child size="sm">
                        <Link :href="dashboard()">
                            {{ t.landing.cta_dashboard }}
                        </Link>
                    </Button>
                    <template v-else>
                        <Button as-child size="sm" variant="outline">
                            <Link :href="login()">
                                {{ t.landing.cta_login }}
                            </Link>
                        </Button>
                        <Button v-if="canRegister" as-child size="sm">
                            <Link href="/register">
                                {{ t.landing.cta_register }}
                            </Link>
                        </Button>
                    </template>
                </div>
            </div>
        </header>

        <main id="top">
            <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-24">
                <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                    <div class="space-y-6">
                        <p
                            class="bg-accent text-accent-foreground inline-flex rounded-xl px-3 py-1 text-xs font-medium"
                        >
                            {{ t.landing.hero_badge }}
                        </p>
                        <h1
                            class="text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl"
                        >
                            {{ t.landing.hero_title }}
                        </h1>
                        <p
                            class="text-muted-foreground max-w-xl text-base leading-relaxed sm:text-lg"
                        >
                            {{ t.landing.hero_lead }}
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <Button v-if="isAuthenticated" as-child size="lg">
                                <Link :href="dashboard()">
                                    {{ t.landing.cta_dashboard }}
                                </Link>
                            </Button>
                            <template v-else>
                                <Button as-child size="lg">
                                    <Link :href="login()">
                                        {{ t.landing.cta_login }}
                                    </Link>
                                </Button>
                                <Button
                                    v-if="canRegister"
                                    as-child
                                    size="lg"
                                    variant="outline"
                                >
                                    <Link href="/register">
                                        {{ t.landing.cta_register }}
                                    </Link>
                                </Button>
                            </template>
                        </div>
                        <ul
                            class="text-muted-foreground grid gap-2 text-sm sm:grid-cols-1"
                        >
                            <li
                                v-for="point in heroPoints"
                                :key="point"
                                class="flex items-start gap-2"
                            >
                                <span
                                    class="bg-primary mt-1.5 size-1.5 shrink-0 rounded-full"
                                />
                                <span>{{ t.landing[point] }}</span>
                            </li>
                        </ul>
                    </div>

                    <LandingAppPreview />
                </div>
            </section>

            <section
                id="features"
                class="border-border/60 border-t py-16 sm:py-20"
            >
                <div class="mx-auto max-w-6xl space-y-10 px-4 sm:px-6">
                    <div class="max-w-2xl space-y-3">
                        <h2
                            class="text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            {{ t.landing.features_title }}
                        </h2>
                        <p class="text-muted-foreground leading-relaxed">
                            {{ t.landing.features_lead }}
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <LandingFeatureCard
                            v-for="feature in features"
                            :key="feature.titleKey"
                            :icon="feature.icon"
                            :title="t.landing[feature.titleKey]"
                            :description="t.landing[feature.descriptionKey]"
                        />
                    </div>
                </div>
            </section>

            <section id="how" class="border-border/60 border-t py-16 sm:py-20">
                <div class="mx-auto max-w-6xl space-y-10 px-4 sm:px-6">
                    <div class="max-w-2xl space-y-3">
                        <h2
                            class="text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            {{ t.landing.how_title }}
                        </h2>
                        <p class="text-muted-foreground leading-relaxed">
                            {{ t.landing.how_lead }}
                        </p>
                    </div>

                    <ol class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <li
                            v-for="step in steps"
                            :key="step.number"
                            class="bg-card border-border/60 rounded-2xl border p-5"
                        >
                            <span
                                class="bg-accent text-accent-foreground mb-4 flex size-8 items-center justify-center rounded-xl text-sm font-semibold"
                            >
                                {{ step.number }}
                            </span>
                            <h3 class="text-sm font-semibold">
                                {{ t.landing[step.titleKey] }}
                            </h3>
                            <p
                                class="text-muted-foreground mt-1.5 text-sm leading-relaxed"
                            >
                                {{ t.landing[step.descriptionKey] }}
                            </p>
                        </li>
                    </ol>
                </div>
            </section>

            <section class="border-border/60 border-t py-16 sm:py-20">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div
                        class="bg-accent text-accent-foreground flex flex-col items-start justify-between gap-6 rounded-2xl px-6 py-8 sm:flex-row sm:items-center sm:px-10"
                    >
                        <div class="space-y-2">
                            <h2
                                class="text-xl font-semibold tracking-tight sm:text-2xl"
                            >
                                {{ t.landing.cta_band_title }}
                            </h2>
                            <p
                                class="text-accent-foreground/80 text-sm leading-relaxed sm:text-base"
                            >
                                {{ t.landing.cta_band_lead }}
                            </p>
                        </div>
                        <Button
                            v-if="isAuthenticated"
                            as-child
                            size="lg"
                            class="shrink-0"
                        >
                            <Link :href="dashboard()">
                                {{ t.landing.cta_dashboard }}
                            </Link>
                        </Button>
                        <Button v-else as-child size="lg" class="shrink-0">
                            <Link :href="login()">
                                {{ t.landing.cta_login }}
                            </Link>
                        </Button>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-border/60 border-t py-8">
            <div
                class="text-muted-foreground mx-auto flex max-w-6xl flex-col items-start justify-between gap-4 px-4 text-sm sm:flex-row sm:items-center sm:px-6"
            >
                <div class="flex items-center gap-2">
                    <BrandMark />
                    <div>
                        <p class="text-foreground font-medium">{{ name }}</p>
                        <p>{{ t.landing.footer_tagline }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <Link
                        v-if="!isAuthenticated"
                        :href="login()"
                        class="hover:text-foreground transition-colors"
                    >
                        {{ t.landing.cta_login }}
                    </Link>
                    <span>© {{ footerYear }}</span>
                </div>
            </div>
        </footer>
    </div>
</template>
