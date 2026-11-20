import { describe, expect, it } from 'vite-plus/test';
import {
    buildErrorTrackingOptions,
    dropConsoleBreadcrumb,
    readErrorTrackingConfig,
    reportClientError,
    stripRequestData,
} from './error-tracking';

const config = {
    dsn: 'http://key@bugsink:8000/1',
    release: 'abc123',
    environment: 'production',
    tunnel: '/error-tracking/tunnel',
};

describe('buildErrorTrackingOptions', () => {
    const options = buildErrorTrackingOptions(config);

    it('passes the identity and the same-origin tunnel through', () => {
        expect(options).toMatchObject(config);
    });

    it('attaches no props and collects nothing beyond the error', () => {
        expect(options).toMatchObject({
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
        });
    });

    it('sends no client reports, logs or traces', () => {
        expect(options.sendClientReports).toBe(false);
        expect(options.tracesSampleRate).toBeUndefined();
    });

    it('drops the session and context integrations and keeps the rest', () => {
        const names = ['GlobalHandlers', 'BrowserSession', 'CultureContext'];
        const filter = options.integrations as (
            defaults: { name: string }[],
        ) => { name: string }[];

        expect(filter(names.map((name) => ({ name })))).toEqual([
            { name: 'GlobalHandlers' },
        ]);
        expect(names).toHaveLength(3);
    });

    it('wires the breadcrumb and event filters', () => {
        expect(options.beforeBreadcrumb).toBe(dropConsoleBreadcrumb);
        expect(options.beforeSend).toBe(stripRequestData);
    });
});

describe('dropConsoleBreadcrumb', () => {
    it('drops console breadcrumbs and keeps the others', () => {
        expect(dropConsoleBreadcrumb({ category: 'console' })).toBeNull();
        expect(dropConsoleBreadcrumb({ category: 'ui.click' })).toEqual({
            category: 'ui.click',
        });
    });
});

describe('stripRequestData', () => {
    it('removes cookies, body, query string and the query of the url', () => {
        const event = stripRequestData({
            request: {
                url: 'https://app.test/operations?q=secret#frag',
                cookies: { session: 'x' },
                data: { amount: '12,00' },
                query_string: 'q=secret',
                headers: { Referer: 'https://app.test/a?b=1', Accept: '*/*' },
            },
        });

        expect(event.request).toEqual({
            url: 'https://app.test/operations',
            headers: { Referer: 'https://app.test/a', Accept: '*/*' },
        });
    });

    it('leaves an event without a request alone', () => {
        expect(stripRequestData({})).toEqual({});
    });
});

describe('readErrorTrackingConfig', () => {
    it('returns null when the element or the dsn is missing', () => {
        expect(readErrorTrackingConfig(null)).toBeNull();
        expect(
            readErrorTrackingConfig({ dataset: {} } as HTMLElement),
        ).toBeNull();
    });

    it('reads the dsn, tunnel, release and environment', () => {
        const element = {
            dataset: {
                errorTrackingDsn: config.dsn,
                errorTrackingTunnel: config.tunnel,
                errorTrackingRelease: 'abc123',
                errorTrackingEnvironment: 'production',
            },
        } as unknown as HTMLElement;

        expect(readErrorTrackingConfig(element)).toEqual(config);
    });
});

describe('reportClientError', () => {
    it('is a no-op while the tracker is not initialised', () => {
        expect(() => reportClientError(new Error('x'))).not.toThrow();
    });
});
