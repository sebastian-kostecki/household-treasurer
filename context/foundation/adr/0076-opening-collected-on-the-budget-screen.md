# „Zebrane startowe” on the Budget screen: a reading on 1 January, in every Budget

ADR-0066 and ADR-0073 carry the Collected through 31 December without a step, and the glossary says the „zebrane startowe” of a new Budget is the Collected reading on 31 December, not the amount typed at marking. No document says in which Budgets that number stands, what it shows before the year ends, or where the row carries it. S-27 (FR-012) needs these answered before the code.

Meaning. „Zebrane startowe” on the Budget screen is the Collected at the start of January of the Budget year, which equals the Collected at the end of December of the year before. It is a reading, never a stored number: a December bill, a correction or a plan change recomputes it. It is read only through `IrregularCollected::collectedAtStartOfMonth(category, year, 1)` (ADR-0073). It is not Opening Collected, the amount typed at marking, though the two meet when a Category is marked Irregular in January: then the period starts in January and the number is the Opening Collected of that entry.

Where it stands. In every Budget of the User, a draft and one in execution; „new Budget” in FR-012 means the Budget of any year, not only a draft. A row has the number when January of the Budget year lies in an Irregular period (`CategoryRhythms::irregularPeriodIn(category, year, 1)` is not null), whatever the amount. It has none when January lies in no period: a Category marked during the year (electricity marked in November has none in the Budget of that year), a Category whose Irregular Rhythm was removed from January on, an income Category, the other Rhythms, and a Category created as Irregular after January (ADR-0073, F9 (c)). Zero in a period is a reading and shows 0,00; only the lack of a period shows nothing. A year before the Budget year without a Budget leaves the amount typed at marking standing (the rate there is 0, ADR-0073, F9 (a)).

Before the year begins. While the Budget year is later than the year of today (UTC, as the rest of the page), the number is a forecast from the bills and plans that exist so far, and the row says so with „prognoza”. From 1 January of the Budget year the word is gone; the phase of the Budget does not enter the condition. Example: electricity marked in November 2026 with Opening Collected 300,00 and a plan of 1 800,00 for 2026 (rate 150,00), with no approved plan of a month. The draft of 2027 seen on 11 November 2026 shows „zebrane startowe 600,00 · prognoza”. After a bill of 400,00 on 20 December it shows 200,00. The Budget of 2027 in execution seen on 10 March 2027 shows „zebrane startowe 200,00”, and a bill of March does not change it.

Place. The caption stands under the name of the Category, as „zebrane X” does in the Month Plan. A row with a caption uses `CategoryChip` in size `lg` with the `#subtitle` slot, which changes the clause of ADR-0060 that the Budget keeps the small chip only for such rows; every other row keeps the small chip. The archived row has the caption in the tone of the rest of the row.

ADR-0037 removed monthly readings from the Budget. This caption is the state at the boundary of the year, one number per Category, and brings none of them back.

Not on the Budget screen: a rate (the plan of the year divided by 12), which the Month Plan shows; a sum of the Collected in the group rows, in „Razem” and in the measures panel; the Collected on the Dashboard, beside Account balances and on the Category list (PRD v3, structural decision 6). Nothing is written when a Budget is created or its execution starts, and the rules of the chain from ADR-0066 and ADR-0073 do not change.

Amended: ADR-0073 (the sentence about 31 December and S-27), ADR-0060 (the small chip in the Budget).

We rejected:

- a rate on the Budget screen, also in the draft only (the Month Plan shows it, and the Budget would grow a second place for one number);
- a separate column „Zebrane startowe”, or the number in the line of the name (the caption under the name is the pattern of the Month Plan and costs no width);
- the caption in the draft only (the number is still true after the execution starts);
- a note under the table instead of the word „prognoza”, or no mark of a forecast (a forecast looks like a fact without it);
- storing the number at creating a Budget or at starting its execution (it would go stale at the first December bill or correction);
- deciding whether the caption stands by the amount (zero is a reading, and an empty caption would hide a Category with a period).
