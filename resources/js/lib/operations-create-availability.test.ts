import { describe, expect, it } from 'vite-plus/test';
import { operationsCreateAvailability } from './operations-create-availability';

describe('operationsCreateAvailability', () => {
    it.each([
        [0, 0, false, false],
        [1, 0, false, false],
        [1, 1, false, true],
        [2, 0, true, false],
        [2, 1, true, true],
    ])(
        'accounts %i, deposits %i -> transfer %s, capital movement %s',
        (accounts, deposits, transfer, capitalMovement) => {
            const result = operationsCreateAvailability(accounts, deposits);

            expect(result.transfer.enabled).toBe(transfer);
            expect(result.capitalMovement.enabled).toBe(capitalMovement);
        },
    );

    it('gives a reason key only when disabled', () => {
        const blocked = operationsCreateAvailability(0, 0);
        const open = operationsCreateAvailability(2, 1);

        expect(blocked.transfer.reasonKey).toBe(
            'transfer_requires_two_accounts',
        );
        expect(blocked.capitalMovement.reasonKey).toBe(
            'capital_movement_requires_account_and_term_deposit',
        );
        expect(open.transfer.reasonKey).toBeNull();
        expect(open.capitalMovement.reasonKey).toBeNull();
    });
});
