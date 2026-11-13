# Collected Coverage compares the Collected of Irregular Categories with Spendable Liquidity

Collected (ADR-0066, ADR-0073) is a reading of the plan: it moves no balance, and nothing checks whether that money lies on the Accounts. The User can mark a Category Irregular with an Opening Collected higher than the balance of the Accounts and read „zebrane 600,00 zł” in the plan while Spendable Liquidity is 500,00 zł. This ADR adds one reading, Collected Coverage, and one `warning` notice.

Rule. The sum C is the total of Collected at the end of the current month over the unarchived expense Budget Lines of the Budget of the current year in execution whose Rhythm in the current month is Irregular. Per line it is the figure of the Month Plan row (ADR-0073:17): the state as of today with the rate of the current month, computed by `CollectedCalculator::collectedAtEndMinorUnits` from the amounts of `MonthToSpend::rowAmounts()`. A line with no item in the Approved Month Plan counts with its Opening Collected and the actual amount alone. The shortfall is max(0, C − Spendable Liquidity). The Dashboard shows C in the caption under Spendable Liquidity („konta, bez lokat · w tym zebrane 600,00 zł”, also when C is 0) and, when Spendable Liquidity is lower than C, adds a notice `collected_not_covered` with severity `warning`, last in the list after `month_pace_ahead`. At equality and at a surplus there is no notice. There is no reading, the caption stays „konta, bez lokat” and there is no notice, when the Budget of the current year is not in execution or no unarchived expense line is Irregular in the current month. Archived Categories and Irregular Categories with no line in the Budget of the current year stay out of C.

Examples. Prąd 450,00 zł + 150,00 zł − 0 = 600,00 zł and Ubezpieczenie 1 100,00 zł + 100,00 zł − 1 200,00 zł = 0: C is 600,00 zł. With Spendable Liquidity 500,00 zł the notice reads „brakuje 100,00 zł · zebrane 600,00 zł”; with 600,00 zł there is no notice. A negative Spendable Liquidity with C = 0 gives no shortfall.

Relation to other ADRs. ADR-0073: the sum uses the same per-row formula, so it equals the sum of „zebrane” of the Irregular rows on the Month Plan page of the current month, to the grosz. ADR-0032: the notice is `warning`, because nothing is exceeded and the bill has not arrived. ADR-0050: the card has the label „Zebrane bez pokrycia”, the identity month and the value of two amounts; a new kind adds a label, not markup. ADR-0045: the caption belongs to the Spendable Liquidity measure of the stat panel. The reading changes no balance, Execution or To Spend, and is not stored: no migration, no new record, no copy of the calculation in TypeScript.

We rejected:

- the comparison with Total funds (with Term Deposits) and two thresholds (the User chose Spendable Liquidity: money in a Term Deposit is not at hand for a bill);
- Collected without the rate of the current month, up to the end of the previous one (the sum would differ from the rows of the Month Plan page);
- a notice without the caption (the User would see the shortfall only when it appears, never the sum it is measured against);
- severity `exceeded` (nothing is broken yet: the bill has not arrived);
- a block, a transfer or a Reserve for the Collected money (a reading, like To Spend, not a write).

Amends ADR-0032 and ADR-0050 (a third `warning` kind).
