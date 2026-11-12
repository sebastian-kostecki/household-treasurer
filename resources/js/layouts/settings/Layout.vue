<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useTranslations } from '@/composables/useTranslations';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editMonthPlanSettings } from '@/routes/month-plan-settings';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const t = useTranslations();

const sidebarNavItems: NavItem[] = [
    {
        title: t.settings.profile,
        href: editProfile(),
    },
    {
        title: t.settings.security,
        href: editSecurity(),
    },
    {
        title: t.settings.appearance,
        href: editAppearance(),
    },
    {
        title: t.settings.month_plan,
        href: editMonthPlanSettings(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="space-y-1">
            <h1 class="page-title">{{ t.settings.title }}</h1>
            <p class="text-muted-foreground text-sm">
                {{ t.settings.description }}
            </p>
        </div>

        <div class="settings-layout">
            <nav class="settings-subnav" :aria-label="t.settings.title">
                <Link
                    v-for="item in sidebarNavItems"
                    :key="toUrl(item.href)"
                    :href="item.href"
                    class="settings-subnav-link"
                    :aria-current="
                        isCurrentOrParentUrl(item.href) ? 'page' : undefined
                    "
                >
                    {{ item.title }}
                </Link>
            </nav>

            <div class="settings-content">
                <slot />
            </div>
        </div>
    </div>
</template>
