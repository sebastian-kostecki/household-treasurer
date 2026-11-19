# Year turn notices name the deadline, the draft Budget and the Budget that waits for its year

Three things around the year turn are invisible until they hurt. The notice „Plan niezatwierdzony” (ADR-0050) does not say by when the plan can be approved, and after midnight of the last day the month is past, has no Proposal and cannot be approved (ADR-0103: `MonthPlanProposal::isPast`). A Budget of a year that Import must accept can be a draft or not exist, and Import then refuses income, expense and refund rows dated in that year (ADR-0106), but the Dashboard says nothing in „Wymaga uwagi”. A Budget started before 1 January is in execution while its year has not begun, and the year menu and the Dashboard line do not tell it apart from a running year. This ADR adds a deadline to one notice, one `warning` notice and one year-menu mark.

Rule. `month_plan_not_approved` carries `approve_by`, the last day of its month (`YYYY-MM-DD`), in every month, not only in December. The card shows it as „zatwierdź do DD.MM, 23:59” in the value slot; 23:59 is the last minute of that App Day (ADR-0103). The server sends only the date, and the browser never computes a deadline from its clock. The condition of the notice (from the 3rd day of the month, Proposal) does not change.

`budget_not_in_execution` is a `warning` notice with `{year, state: 'draft' | 'missing'}`. It is raised for the current year when its Budget exists and is a draft. In December, while the current year has a Budget, it is also raised for the next year when that Budget is a draft (`draft`) or does not exist (`missing`). A missing Budget of the current year gives no notice (the Dashboard empty state covers it), and no notice for the next year is raised while the current year has no Budget. The kind is appended last in the list, after `collected_not_covered` (ADR-0032: severity first, then kind); with two items they follow year order. The card reads „Budżet w szkicu” or „Brak budżetu”, identity „Budżet :year”, value „Import nie przyjmie wierszy z :year”. A draft opens the Budget screen of that year; a missing Budget opens the „Dodaj rok” dialog for it.

„od 1.01” is a year-menu mark for a Budget in execution whose year is later than the App Day year. It uses the outline style of „bieżący” (`sidebar-border`, `sidebar-foreground`), no new token. The Dashboard status line names it: „budżet 2027 od 1 stycznia”. The mark and the line disappear at 00:00 Polish time on 1 January, when the year becomes current. There is no notice for this state.

Examples. On 27.11.2026 with November in Proposal the card reads „Plan niezatwierdzony · Listopad · zatwierdź do 30.11, 23:59”. At 22:59:59 UTC on 30.11 there is no card for 2027; at 23:00:00 UTC (1.12 00:00 in Poland) with 2027 a draft the card „Budżet w szkicu · Budżet 2027” appears, and with no 2027 Budget it reads „Brak budżetu”. On 30.12 after „Rozpocznij realizację” of 2027 there is no card; the year menu shows 2027 „od 1.01” and the status line says „budżet 2027 od 1 stycznia”. At 00:00 Polish time on 1.01.2027 both are gone and 2027 is „bieżący”. On 2.01.2027 with 2027 still a draft (the start was forgotten) the card shows for the current year. A first Budget left in draft in March shows the same card for the current year: the rule is not limited to the year turn.

Relation to other ADRs. ADR-0106: the notice states the consequence that ADR already enforces and changes no validation. ADR-0103: the deadline and the mark use the App Day. ADR-0090 is the precedent: a new `warning` kind adds a label, not markup.

We rejected:

- approving a past month after midnight (the month has no Proposal, and the plan of a past month is not a plan);
- a separate deadline card (the deadline belongs to the card that already says the plan is not approved);
- a deadline only in December (the rule is the same every month);
- a window from 15.12 or 28.12 for the next-year card (December is one simple threshold, and the draft stays visible from the first day);
- a warning card for „waits for 1 January” (nothing needs the User; the menu and the status line say it);
- the new card first among warnings (it would cover the warnings of the Month Plan);
- blocking or warning about approving January while it is still December (a candidate in `development-directions.md`; the README „Przełom roku” keeps the warning).

Amends ADR-0032 (a new `warning` kind, last in the order), ADR-0050 (label table: a new row, and the value slot of `month_plan_not_approved`) and ADR-0098 (a new mark; „a year in execution that is not the current one has no mark” now holds only for past years).
