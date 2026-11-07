import { computed } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { parseMoneyInputToMinorUnits } from '@/lib/format';

export type UseOperationAmountInputReturn = {
    amountInvalid: ComputedRef<boolean>;
    amountMinorUnits: ComputedRef<number>;
    showPreview: ComputedRef<boolean>;
};

/**
 * Operation dialogs treat an amount as wrong when it cannot be read or is
 * negative (ADR-0041). Zero is sent to the server, which rejects it.
 */
export function useOperationAmountInput(
    amountInput: Ref<string>,
): UseOperationAmountInputReturn {
    const parsedAmountMinorUnits = computed(() =>
        parseMoneyInputToMinorUnits(amountInput.value),
    );
    const amountInvalid = computed(
        () =>
            parsedAmountMinorUnits.value === null ||
            parsedAmountMinorUnits.value < 0,
    );
    const amountMinorUnits = computed(() =>
        amountInvalid.value ? 0 : (parsedAmountMinorUnits.value ?? 0),
    );
    const showPreview = computed(
        () => !amountInvalid.value && amountMinorUnits.value > 0,
    );

    return { amountInvalid, amountMinorUnits, showPreview };
}
