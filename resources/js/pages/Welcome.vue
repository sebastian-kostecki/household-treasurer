<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRightLeft,
    CalendarRange,
    Check,
    Gauge,
    Landmark,
    Lock,
    Upload,
} from '@lucide/vue';
import { computed } from 'vue';
import BrandMark from '@/components/BrandMark.vue';
import LandingAppPreview from '@/components/LandingAppPreview.vue';
import LandingFeatureCard from '@/components/LandingFeatureCard.vue';
import { Badge } from '@/components/ui/badge';
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
        number: '01',
        titleKey: 'step_1_title' as const,
        descriptionKey: 'step_1_description' as const,
    },
    {
        number: '02',
        titleKey: 'step_2_title' as const,
        descriptionKey: 'step_2_description' as const,
    },
    {
        number: '03',
        titleKey: 'step_3_title' as const,
        descriptionKey: 'step_3_description' as const,
    },
    {
        number: '04',
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
        <header class="landing-bar">
            <div class="landing-wrap flex items-center justify-between gap-4">
                <a href="#top" class="flex items-center gap-2">
                    <BrandMark />
                    <span class="card-title">{{ name }}</span>
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
                    <Button v-if="isAuthenticated" as-child>
                        <Link :href="dashboard()">
                            {{ t.landing.cta_dashboard }}
                        </Link>
                    </Button>
                    <template v-else>
                        <Button as-child variant="outline">
                            <Link :href="login()">
                                {{ t.landing.cta_login }}
                            </Link>
                        </Button>
                        <Button v-if="canRegister" as-child>
                            <Link href="/register">
                                {{ t.landing.cta_register }}
                            </Link>
                        </Button>
                    </template>
                </div>
            </div>
        </header>

        <main id="top">
            <section class="landing-wrap landing-hero">
                <div class="space-y-5">
                    <Badge variant="ok">{{ t.landing.hero_badge }}</Badge>
                    <h1 class="landing-hero-title">
                        {{ t.landing.hero_title }}
                    </h1>
                    <p class="lead text-muted-foreground max-w-xl">
                        {{ t.landing.hero_lead }}
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <Button
                            v-if="isAuthenticated"
                            as-child
                            class="h-control-lg"
                        >
                            <Link :href="dashboard()">
                                {{ t.landing.cta_dashboard }}
                            </Link>
                        </Button>
                        <template v-else>
                            <Button as-child class="h-control-lg">
                                <Link :href="login()">
                                    {{ t.landing.cta_login }}
                                </Link>
                            </Button>
                            <Button
                                v-if="canRegister"
                                as-child
                                variant="outline"
                                class="h-control-lg"
                            >
                                <Link href="/register">
                                    {{ t.landing.cta_register }}
                                </Link>
                            </Button>
                        </template>
                    </div>
                    <ul class="landing-points">
                        <li v-for="point in heroPoints" :key="point">
                            <Check aria-hidden="true" />
                            <span>{{ t.landing[point] }}</span>
                        </li>
                    </ul>
                </div>

                <LandingAppPreview />
            </section>

            <section id="features" class="landing-section">
                <div class="landing-wrap space-y-8">
                    <div class="max-w-2xl space-y-2">
                        <p class="label-caps text-muted-foreground">
                            {{ t.landing.nav_features }}
                        </p>
                        <h2 class="heading">
                            {{ t.landing.features_title }}
                        </h2>
                        <p class="lead text-muted-foreground">
                            {{ t.landing.features_lead }}
                        </p>
                    </div>

                    <div class="landing-grid">
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

            <section id="how" class="landing-section">
                <div class="landing-wrap space-y-8">
                    <div class="max-w-2xl space-y-2">
                        <p class="label-caps text-muted-foreground">
                            {{ t.landing.nav_how }}
                        </p>
                        <h2 class="heading">{{ t.landing.how_title }}</h2>
                        <p class="lead text-muted-foreground">
                            {{ t.landing.how_lead }}
                        </p>
                    </div>

                    <ol class="landing-steps">
                        <li v-for="step in steps" :key="step.number">
                            <span class="landing-step-no">
                                {{ step.number }}
                            </span>
                            <h3 class="card-title mt-1.5">
                                {{ t.landing[step.titleKey] }}
                            </h3>
                            <p class="text-muted-foreground mt-1 text-sm">
                                {{ t.landing[step.descriptionKey] }}
                            </p>
                        </li>
                    </ol>
                </div>
            </section>

            <section class="landing-section">
                <div class="landing-wrap">
                    <div class="landing-cta">
                        <div class="space-y-1.5">
                            <h2>{{ t.landing.cta_band_title }}</h2>
                            <p class="text-sm">
                                {{ t.landing.cta_band_lead }}
                            </p>
                        </div>
                        <Button
                            as-child
                            variant="brand"
                            class="h-control-lg shrink-0"
                        >
                            <Link
                                :href="isAuthenticated ? dashboard() : login()"
                            >
                                {{
                                    isAuthenticated
                                        ? t.landing.cta_dashboard
                                        : t.landing.cta_login
                                }}
                            </Link>
                        </Button>
                    </div>
                </div>
            </section>
        </main>

        <footer class="landing-foot py-6">
            <div
                class="landing-wrap text-muted-foreground flex flex-col items-start justify-between gap-4 text-sm sm:flex-row sm:items-center"
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
