# The Month Plan shows Categories in collapsible Category Group rows that sum the shown Plan and Actual and flag „ponad plan”

ADR-0069 put Category Groups in „Kategorie”, the Budget and the reports and left the Month Plan flat; S-22 did that on purpose. S-25 (FR-008) brings the groups to the Month Plan: each type section (Dochody, Wydatki) shows its Categories in collapsible groups, and a collapsed group must not hide that one of its Categories is over plan. This ADR adds to ADR-0042 (the layout of the Month Plan), ADR-0060 (Approved stays empty in sum rows), ADR-0066 (the over-plan signal by Rhythm), ADR-0069 (the order and the group row in the Budget) and ADR-0072 (the section of idle Even Annual rows). A group has no plan and no rule of its own (PRD v3 Non-Goals); everything below is a reading of its rows.

Order. Categories without a group stand in the one order of their type, between the groups, exactly as in „Kategorie”, the Budget and the reports (ADR-0069): `sort_order`, a group before a Category on a tie, then the order of the flat list. There is no „Bez grupy” block at the start or the end of a section. A Category whose group is unknown to the page stands as a Category without a group. The Categories of a group stand in its body, indented.

What the group row shows. The name in the toggle button, the count of Categories, the sum of Plan and the sum of Actual of the rows it holds, and the badge „ponad plan” in the Status column when any of those rows is over plan. Approved and Deviation stay empty. Each shown sum is the literal sum of that column over the shown rows of the group. Deviation is not summed because the rows do not add up to „Actual − Plan”: an Irregular row contributes only its excess over Collected and an unpaid fixed bill has no deviation (ADR-0066). Example: group „Mieszkanie” holds „Czynsz” (fixed, plan 2 500, actual 2 500) and „Prąd” (Irregular, rate 150, bill 600, excess 150); the row shows Plan 2 650 and Actual 3 100, and the sum of the row deviations (150) is not 3 100 − 2 650 (450). A sum of Deviation next to those two numbers would contradict them, so the cell is empty.

The signal. „Ponad plan” of the group is „any of its rows is over plan”, where each row is judged by its own Rhythm (ADR-0066): a fixed or current row against the month, an Even Annual row against the year, an Irregular row by the excess over Collected. The group adds no rule. It shows also when the group is collapsed; that is the reason for it. Income is never over plan, so an income group has no badge.

Which rows a group holds. Active Categories that are not idle Even Annual rows. Archived rows stand at the end of their type section without a group, also when the Category still has one, and they are in no sum, count or signal. Idle Even Annual rows stay in their own collapsed section (ADR-0072), outside the groups. Join candidates (change mode and an editable proposal) stand in their group and count in its sums like any shown row. A group without a shown row has no row.

Where the sums come from. The client computes them from the reading of each shown row (`rowState` of the page), not from the server, so in edit mode they follow the typed plan. A row with an unreadable plan field reads as „—”, so the group Plan is „—” while the field is unreadable, and a collapsed group with such a field opens by itself, like the section of idle rows. While the field is unreadable the group cannot be collapsed (its toggle is disabled), because an invalid amount blocks approval and the field that causes it must stay in view. The server sends only each row's `group_id` and `sort_order` and the lists of groups of both types.

State. Groups start expanded. A collapse is kept for the visit only: it is reset by a reload, a change of month or of Budget, and it is never stored in the browser or on the server.

We rejected:

- the sum of the row deviations in the group row, and „Actual − Plan” of the group (neither is the other, and the number would disagree with the row next to it);
- a „Bez grupy” block (ADR-0069 rejected it for the same reason);
- archived and idle Even Annual rows inside the group (the group sums would count a row the User cannot act on, and a second idle section would appear inside every group);
- groups that start collapsed (the over-plan Category would be hidden on entry), and remembering the collapse between visits (a stale state would hide a Category the User has forgotten about);
- sums computed on the server as in the Budget (they would follow the typed plan only in read mode);
- a status other than „ponad plan” on the group row;
- letting the User collapse a group with an unreadable field (the red field would leave the view while „Zatwierdź” stays blocked).
