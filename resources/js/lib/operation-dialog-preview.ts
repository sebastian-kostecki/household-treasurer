import type { ToSpendReading } from './entry-to-spend-warning';

export type FormPreviewRow = {
    key: string;
    label: string;
    beforeMinorUnits: number;
    afterMinorUnits: number;
    hint?: string;
    warnings: string[];
};

export type OperationPreviewReading = {
    label: string;
    beforeMinorUnits: number | null;
    afterMinorUnits: number | null;
    hint?: string;
    warning: string | null;
};

export type OperationPreviewTexts = {
    toSpendLabel: string; // "Do wydania w :month :year"
    toSpendWarning: string;
    monthNamesLocative: string[]; // January ... December
};

export type OperationPreviewInput = {
    toSpendReadings: ToSpendReading[];
    limit?: OperationPreviewReading | null;
    accountBalance?: OperationPreviewReading | null;
    termDepositBalance?: OperationPreviewReading | null;
    monthRemainingWarnings?: string[];
    texts: OperationPreviewTexts;
};

export type OperationPreview = {
    rows: FormPreviewRow[];
    warnings: string[];
};

export function toSpendRowLabel(
    template: string,
    monthNamesLocative: string[],
    year: number,
    month: number,
): string {
    return template
        .replace(':month', monthNamesLocative[month - 1] ?? String(month))
        .replace(':year', String(year));
}

function readingRow(
    key: string,
    reading: OperationPreviewReading | null | undefined,
): FormPreviewRow | null {
    if (
        reading === null ||
        reading === undefined ||
        reading.beforeMinorUnits === null ||
        reading.afterMinorUnits === null
    ) {
        return null;
    }

    return {
        key,
        label: reading.label,
        beforeMinorUnits: reading.beforeMinorUnits,
        afterMinorUnits: reading.afterMinorUnits,
        hint: reading.hint,
        warnings: reading.warning === null ? [] : [reading.warning],
    };
}

/**
 * Rows of the before → after block: To Spend (ascending by month), category
 * limit, account balance, term deposit balance. Each warning sits under the
 * reading it concerns; Month Remaining warnings have no reading and go to the
 * block.
 */
export function buildOperationPreview(
    input: OperationPreviewInput,
): OperationPreview {
    const { texts } = input;
    const rows: FormPreviewRow[] = [];

    const toSpendReadings = [...input.toSpendReadings].sort(
        (a, b) => a.year * 12 + a.month - (b.year * 12 + b.month),
    );

    for (const reading of toSpendReadings) {
        const negative =
            reading.afterMinorUnits < 0 &&
            reading.afterMinorUnits < reading.beforeMinorUnits;

        rows.push({
            key: `to-spend-${reading.year}-${reading.month}`,
            label: toSpendRowLabel(
                texts.toSpendLabel,
                texts.monthNamesLocative,
                reading.year,
                reading.month,
            ),
            beforeMinorUnits: reading.beforeMinorUnits,
            afterMinorUnits: reading.afterMinorUnits,
            warnings: negative ? [texts.toSpendWarning] : [],
        });
    }

    for (const row of [
        readingRow('limit', input.limit),
        readingRow('account-balance', input.accountBalance),
        readingRow('term-deposit-balance', input.termDepositBalance),
    ]) {
        if (row !== null) {
            rows.push(row);
        }
    }

    return { rows, warnings: [...(input.monthRemainingWarnings ?? [])] };
}

/** The twelve locative month names (budget.month_name_locative_1 … _12), January first. */
export function monthNamesLocativeFrom(
    budgetTranslations: Record<string, string>,
): string[] {
    return Array.from(
        { length: 12 },
        (_, index) =>
            budgetTranslations[`month_name_locative_${index + 1}`] ?? '',
    );
}
