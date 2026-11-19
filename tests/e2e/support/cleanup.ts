import { test } from '@playwright/test';

// Runs a spec's flow and then its cleanup, also after the flow failed. A
// cleanup that fails after a failed flow does not replace the flow's error (as
// a throw from `finally` would): the report and the CI annotation keep
// pointing at the cause, and the cleanup error is logged and added to the
// test's annotations. After a passing flow a failed cleanup fails the test.
export async function withCleanup(
    flow: () => Promise<void>,
    cleanup: () => Promise<void>,
): Promise<void> {
    try {
        await flow();
    } catch (flowError) {
        try {
            await test.step('cleanup after a failure', cleanup);
        } catch (cleanupError) {
            console.error(
                'Cleanup failed after the flow had failed:',
                cleanupError,
            );
            test.info().annotations.push({
                type: 'cleanup-error',
                description: String(cleanupError),
            });
        }

        throw flowError;
    }

    await test.step('cleanup', cleanup);
}
