import * as Sentry from '@sentry/vue';
import type { App } from 'vue';

export type ErrorTrackingConfig = {
    dsn: string;
    release: string | undefined;
    environment: string | undefined;
    tunnel: string;
};

type SentryInitOptions = NonNullable<Parameters<typeof Sentry.init>[0]>;

/**
 * Integrations that would send more than an error: browser sessions (their envelope is not an `event`, so the
 * tunnel refuses it), the locale context and the conversation id. Everything else of the default set stays.
 */
const DROPPED_INTEGRATIONS = [
    'BrowserSession',
    'CultureContext',
    'ConversationId',
];

/**
 * Every privacy-relevant browser option, in one place. The SDK defaults are permissive (`attachProps` is true, an
 * unset `dataCollection` collects user info, cookies and bodies, and an omitted key flips to collecting), so each
 * value is explicit. v11 has no `sendDefaultPii`: `dataCollection.userInfo` replaces it.
 */
export function buildErrorTrackingOptions(
    config: ErrorTrackingConfig,
): SentryInitOptions {
    return {
        dsn: config.dsn,
        release: config.release,
        environment: config.environment,
        tunnel: config.tunnel,

        // Vue component props are form values and amounts: never attached.
        attachProps: false,
        dataCollection: {
            userInfo: false,
            cookies: false,
            httpHeaders: false,
            httpBodies: [],
            urlQueryParams: false,
            graphQL: { document: false, variables: false },
            genAI: { inputs: false, outputs: false },
            mcp: { inputs: false, outputs: false },
            databaseQueryData: false,
            queues: false,
            stackFrameVariables: false,
            frameContextLines: 0,
        },

        // Errors only: no tracing, replay, profiling or sessions (logs are off unless enabled).
        integrations: (defaults) =>
            defaults.filter(
                (integration) =>
                    !DROPPED_INTEGRATIONS.includes(integration.name),
            ),

        sendClientReports: false,
        beforeBreadcrumb: dropConsoleBreadcrumb,
        beforeSend: stripRequestData,
    };
}

export function dropConsoleBreadcrumb<
    T extends { category?: string | undefined },
>(breadcrumb: T): T | null {
    return breadcrumb.category === 'console' ? null : breadcrumb;
}

function withoutQuery(url: string): string {
    const end = url.search(/[?#]/);

    return end === -1 ? url : url.slice(0, end);
}

/**
 * Removes what the request context still carries: cookies, a body, the query string and fragment of the page URL
 * (and of the referrer, when a header survives).
 */
export function stripRequestData<
    T extends {
        request?: {
            url?: string | undefined;
            cookies?: unknown;
            data?: unknown;
            query_string?: unknown;
            headers?: Record<string, string> | undefined;
        };
    },
>(event: T): T {
    const request = event.request;

    if (request === undefined) {
        return event;
    }

    delete request.cookies;
    delete request.data;
    delete request.query_string;

    if (request.url !== undefined) {
        request.url = withoutQuery(request.url);
    }

    if (request.headers !== undefined) {
        for (const name of Object.keys(request.headers)) {
            if (name.toLowerCase() === 'referer') {
                request.headers[name] = withoutQuery(request.headers[name]);
            }
        }
    }

    return event;
}

/**
 * Reads the runtime config the server renders as `data-*` attributes on one element (see `app.blade.php`). The DSN
 * and release come from the running app, not from the image build. Returns null when the tracker is off.
 */
export function readErrorTrackingConfig(
    element: HTMLElement | null,
): ErrorTrackingConfig | null {
    const dsn = element?.dataset.errorTrackingDsn;
    const tunnel = element?.dataset.errorTrackingTunnel;

    if (!element || !dsn || !tunnel) {
        return null;
    }

    return {
        dsn,
        tunnel,
        release: element.dataset.errorTrackingRelease || undefined,
        environment: element.dataset.errorTrackingEnvironment || undefined,
    };
}

/** Initialises the SDK with the Vue app, before it mounts. Does nothing when the tracker is off. */
export function initErrorTracking(
    app: App,
    config: ErrorTrackingConfig | null,
): void {
    if (config === null) {
        return;
    }

    Sentry.init({ ...buildErrorTrackingOptions(config), app });
}

/**
 * Sends an error the UI handles itself (a caught 2FA or passkey failure) to the tracker. A no-op when the tracker
 * is not initialised, so callers need no check.
 */
export function reportClientError(error: unknown): void {
    if (!Sentry.isInitialized()) {
        return;
    }

    Sentry.captureException(toError(error));
}

function toError(error: unknown): Error {
    if (error instanceof Error) {
        return error;
    }

    if (
        typeof error === 'object' &&
        error !== null &&
        'message' in error &&
        typeof error.message === 'string'
    ) {
        return new Error(error.message);
    }

    return new Error(String(error));
}
