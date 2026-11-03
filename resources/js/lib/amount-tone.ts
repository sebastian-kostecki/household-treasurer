export function amountToneClass(
    minorUnits: number,
): 'text-success' | 'text-destructive' | '' {
    if (minorUnits > 0) {
        return 'text-success';
    }

    if (minorUnits < 0) {
        return 'text-destructive';
    }

    return '';
}
