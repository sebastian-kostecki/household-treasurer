/**
 * Whether a draw (a negative set-aside) is larger than today's spendable
 * liquidity. This exists only in TypeScript: the warning must react while the
 * form is edited, before approval, and the server blocks nothing.
 */
export function drawExceedsSpendableLiquidity(
    setAsideMinorUnits: number,
    spendableLiquidityMinorUnits: number | null,
): boolean {
    return (
        spendableLiquidityMinorUnits !== null &&
        setAsideMinorUnits < 0 &&
        -setAsideMinorUnits > spendableLiquidityMinorUnits
    );
}
