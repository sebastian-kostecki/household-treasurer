import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import {
    initErrorTracking,
    readErrorTrackingConfig,
    reportClientError,
} from '@/lib/error-tracking';
import { initializeFlashToast } from '@/lib/flashToast';
import { defaultVisitOptions } from '@/lib/visit-scroll';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// The DSN and release are runtime values the server renders in `app.blade.php`, not build-time ones.
const errorTracking = readErrorTrackingConfig(
    document.getElementById('error-tracking'),
);

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // The SDK attaches its Vue error handler to the app, so it starts before the app mounts.
    withApp(app) {
        initErrorTracking(app, errorTracking);
    },
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    defaults: {
        visitOptions: (_href, options) =>
            defaultVisitOptions(options, window.location.pathname),
    },
    progress: {
        color: 'var(--brand-text)',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// Server 5xx are reported server-side already (`httpException`), so only a request that got no answer is reported here.
router.on('networkError', (event) => {
    reportClientError(event.detail.error);
});
