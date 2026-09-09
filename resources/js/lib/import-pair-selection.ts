export type PairCandidate = {
    kind: 'financial_operation' | 'own_transfer';
    id: number;
    account_id: number;
    account_name: string;
    date: string;
    description: string;
    converts_financial_operation: boolean;
    reason: string;
};

export type PairRowSelection = {
    selected: boolean;
    type: 'income' | 'expense' | 'refund' | 'own_transfer' | 'capital_movement';
    other_account_id: number | '';
    pair_first_leg_kind: 'financial_operation' | 'own_transfer' | '';
    pair_first_leg_id: number | '';
};

export function pairCandidateKey(candidate: PairCandidate): string {
    return `${candidate.kind}:${candidate.id}`;
}

export function firstLegKey(
    kind: PairRowSelection['pair_first_leg_kind'],
    id: PairRowSelection['pair_first_leg_id'],
): string | null {
    if (kind === '' || id === '') {
        return null;
    }

    return `${kind}:${id}`;
}

export function consumedFirstLegKeys(
    selections: Record<number, PairRowSelection>,
): Set<string> {
    const consumed = new Set<string>();

    for (const selection of Object.values(selections)) {
        if (!selection.selected) {
            continue;
        }

        const key = firstLegKey(
            selection.pair_first_leg_kind,
            selection.pair_first_leg_id,
        );

        if (key !== null) {
            consumed.add(key);
        }
    }

    return consumed;
}

export function availablePairCandidates(
    candidates: PairCandidate[],
    consumedKeys: Set<string>,
    currentSelection?: PairRowSelection,
): PairCandidate[] {
    return candidates.filter((candidate) => {
        const key = pairCandidateKey(candidate);

        if (!consumedKeys.has(key)) {
            return true;
        }

        if (currentSelection === undefined) {
            return false;
        }

        return (
            currentSelection.pair_first_leg_kind === candidate.kind &&
            currentSelection.pair_first_leg_id === candidate.id
        );
    });
}

export function candidatesForAccount(
    candidates: PairCandidate[],
    accountId: number | '',
): PairCandidate[] {
    if (accountId === '') {
        return candidates;
    }

    return candidates.filter((candidate) => candidate.account_id === accountId);
}

export function ownTransferCandidates(
    candidates: PairCandidate[],
): PairCandidate[] {
    return candidates.filter((candidate) => candidate.kind === 'own_transfer');
}

export function locksTypeToOwnTransfer(candidates: PairCandidate[]): boolean {
    return ownTransferCandidates(candidates).length > 0;
}

export function uniqueFinancialOperationCandidate(
    candidates: PairCandidate[],
): PairCandidate | null {
    const financialOperationCandidates = candidates.filter(
        (candidate) => candidate.kind === 'financial_operation',
    );

    if (financialOperationCandidates.length !== 1) {
        return null;
    }

    return financialOperationCandidates[0] ?? null;
}

export function uniqueOwnTransferCandidate(
    candidates: PairCandidate[],
): PairCandidate | null {
    const ownTransferOnlyCandidates = ownTransferCandidates(candidates);

    if (ownTransferOnlyCandidates.length !== 1) {
        return null;
    }

    return ownTransferOnlyCandidates[0] ?? null;
}

export function uniquePairCandidate(
    candidates: PairCandidate[],
): PairCandidate | null {
    if (locksTypeToOwnTransfer(candidates)) {
        return uniqueOwnTransferCandidate(candidates);
    }

    return uniqueFinancialOperationCandidate(candidates);
}

export function allowedPairKinds(
    candidates: PairCandidate[],
): PairCandidate['kind'][] {
    if (locksTypeToOwnTransfer(candidates)) {
        return ['own_transfer'];
    }

    return ['financial_operation', 'own_transfer'];
}

export function matchingPairCandidate(
    candidates: PairCandidate[],
    selection: PairRowSelection,
): PairCandidate | null {
    if (
        selection.type !== 'own_transfer' ||
        selection.other_account_id === '' ||
        selection.pair_first_leg_id === ''
    ) {
        return null;
    }

    return (
        candidates.find(
            (candidate) =>
                candidate.kind === selection.pair_first_leg_kind &&
                candidate.id === selection.pair_first_leg_id &&
                candidate.account_id === selection.other_account_id,
        ) ?? null
    );
}

export function syncPairSelection(
    candidates: PairCandidate[],
    selection: PairRowSelection,
    consumedKeys: Set<string>,
): void {
    if (
        selection.type !== 'own_transfer' ||
        selection.other_account_id === ''
    ) {
        selection.pair_first_leg_kind = '';
        selection.pair_first_leg_id = '';

        return;
    }

    const available = availablePairCandidates(
        candidates,
        consumedKeys,
        selection,
    );
    const accountCandidates = candidatesForAccount(
        available,
        selection.other_account_id,
    ).filter((candidate) =>
        allowedPairKinds(candidates).includes(candidate.kind),
    );

    if (accountCandidates.length === 0) {
        selection.pair_first_leg_kind = '';
        selection.pair_first_leg_id = '';

        return;
    }

    if (accountCandidates.length === 1) {
        const candidate = accountCandidates[0];
        selection.pair_first_leg_kind = candidate.kind;
        selection.pair_first_leg_id = candidate.id;

        return;
    }

    const stillValid = accountCandidates.some(
        (candidate) =>
            candidate.kind === selection.pair_first_leg_kind &&
            candidate.id === selection.pair_first_leg_id,
    );

    if (!stillValid) {
        selection.pair_first_leg_kind = '';
        selection.pair_first_leg_id = '';
    }
}

export function pairRecordPickerCandidates(
    candidates: PairCandidate[],
    selection: PairRowSelection,
    consumedKeys: Set<string>,
): PairCandidate[] {
    const available = availablePairCandidates(
        candidates,
        consumedKeys,
        selection,
    );

    if (available.length <= 1) {
        return [];
    }

    if (selection.type !== 'own_transfer') {
        return [];
    }

    if (selection.other_account_id !== '') {
        const forAccount = candidatesForAccount(
            available,
            selection.other_account_id,
        ).filter((candidate) =>
            allowedPairKinds(candidates).includes(candidate.kind),
        );

        return forAccount.length > 1 ? forAccount : [];
    }

    return matchingPairCandidate(available, selection) === null
        ? available.filter((candidate) =>
              allowedPairKinds(candidates).includes(candidate.kind),
          )
        : [];
}

export function showsPairCandidateBadge(
    importPairCandidate: boolean,
    candidates: PairCandidate[],
    consumedKeys: Set<string>,
    selection: PairRowSelection,
): boolean {
    if (!importPairCandidate) {
        return false;
    }

    return (
        availablePairCandidates(candidates, consumedKeys, selection).length > 0
    );
}

export function applyPairCandidateSelection(
    selection: PairRowSelection,
    candidate: PairCandidate,
): void {
    selection.other_account_id = candidate.account_id;
    selection.pair_first_leg_kind = candidate.kind;
    selection.pair_first_leg_id = candidate.id;
}

export function selectedPairCandidateKey(selection: PairRowSelection): string {
    if (selection.pair_first_leg_id === '') {
        return '';
    }

    return `${selection.pair_first_leg_kind}:${selection.pair_first_leg_id}`;
}
