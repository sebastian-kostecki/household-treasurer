# Import: a row in a year without a Budget in execution

A statement can cross the year turn (29 December to 2 January) while the new Budget is still a draft, or contain a year that has no Budget at all (30 December 2025 before the first Budget). Income, expense and refund need a Budget in execution (ADR-0063, ADR-0067); the preview of ADR-0052 reported both rows as valid, and the store refused the whole set with a message that named neither the row nor the year. This ADR fixes how the Import treats such a row (change `import-across-draft-year`).

Rule. The set stays atomic (FR-IMP-20, ADR-0011: rows start unselected and a selected row that cannot be saved blocks the set). A year without a Budget in execution is not an invalid row: the preview gets the state of every year present in the rows (`budgetYearStates`: `execution`, `draft`, `missing`) and flags the row per type. Income, expense, refund and the interest split from a capital movement need the year in execution; an own transfer and a capital movement without interest do not.

Decisions:

1. The server computes the state of each year once per preview (`ImportBudgetYearStates`) and sends it as a page prop; the client decides per selected type whether the row needs a Budget in execution.
2. The store refuses the set as a whole when a selected income, expense, refund or interest row falls in a year that is a draft or has no Budget. The message gives the row date and the year, in the draft variant and in the missing variant, and comes before the category check.
3. The action next to the note is a link to the Budget of that year (or the create dialog when there is none). The Import never starts an execution: starting it is irreversible and stays on the Budget page.
4. A year loaded from a sheet (ADR-0085) always has a Budget in execution and keeps its own message.

We rejected:

- partial save (skipping the rows of a year without execution): it breaks FR-IMP-20;
- starting the execution from the preview;
- a new `ImportRowInvalidReason` that marks the whole row invalid: it would also block own transfers and capital movements, which a draft year accepts.
