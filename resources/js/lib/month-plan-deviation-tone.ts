export type DeviationTone =
    | 'text-muted-foreground'
    | 'text-success'
    | 'text-destructive'
    | '';

export type MonthSummaryKey = 'income' | 'expense' | 'set_aside';

/**
 * Tone of the Deviation in a Month Plan row (ADR-0092). The sign is actual
 * minus plan, the tone follows the benefit: the first matching case wins.
 */
export function monthItemDeviationTone(input: {
    deviation: number | null;
    overPlan: boolean;
    type: 'income' | 'expense';
    isArchived: boolean;
}): DeviationTone {
    if (input.deviation === null) {
        return 'text-muted-foreground';
    }

    if (input.isArchived) {
        return '';
    }

    if (input.overPlan) {
        return 'text-destructive';
    }

    if (input.type === 'expense') {
        return input.deviation < 0 ? 'text-success' : '';
    }

    return input.deviation > 0 ? 'text-success' : '';
}

/**
 * Tone of the difference in the past-month summary card (ADR-0092): less
 * expense is favourable, more income or set aside is favourable.
 */
export function monthSummaryDeviationTone(
    key: MonthSummaryKey,
    minorUnits: number,
): DeviationTone {
    if (minorUnits === 0) {
        return '';
    }

    const favourable = key === 'expense' ? minorUnits < 0 : minorUnits > 0;

    return favourable ? 'text-success' : 'text-destructive';
}
