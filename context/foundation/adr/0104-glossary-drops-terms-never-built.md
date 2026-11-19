# The glossary drops terms that were designed but never built

Cash Expectation, Agenda and Settlement candidate (ADR 0017–0022, 0029) and Year Close, Reserve and Earmark (ADR 0030) leave the glossary, together with their rules in other entries and their names in the _Avoid_ lists. They were never on `main`: the glossary described a model that the code does not have, and agents built on terms that do not exist. Decided on 2026-11-19.

What stops holding. Account: the block on deleting an Account that a Cash Expectation points at, and the Cash Expectation in the opening date (the code `Account::earliestActivityDate()` stays). Category: the Cash Expectation in the condition for deleting and for changing the type (`CategoryTypeChange` stays). Budget Line: the Cash Expectation as a first use. Financial Operation, Import and Import pair candidate: the settlement of a Cash Expectation. Current Plan, Align, Budget, Available Limit and Year Result: Year Close, Reserve and Earmark. The clauses on Cash Expectation, Agenda, Year Close, Reserve and Earmark in ADR 0031, 0063, 0066, 0067, 0078, 0086 and 0091 (each carries an "Amended by ADR-0104" note).

Year Close. The direction left open in `development-directions.md` ("Rok 2027") is decided here: Year Close goes. A summary of the year, if it is built, will be a reading with its own ADR.

Return of a term. A feature in this area (liquidity over time, a summary of the year) starts with a new glossary entry and a new ADR (`AGENTS.md`), not with restoring the old rules. The old ADRs stay as the record of the design; each carries a "Superseded by ADR-0104" note.

We rejected a "Designed, not built" section in the glossary (the rules would stay where the agent reads them), marks in place (the rule would still stand in a live entry), leaving the names in the _Avoid_ lists as plain words (without a definition they do not say what they collide with), and removing only Agenda and Cash Expectation while leaving Year Close for the decision on the summary of the year (two ways of doing it in one change).
