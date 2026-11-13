# Align is „Wyrównaj” in the UI

The Budget row action that sets the Current Plan of a Budget Line to max(0, Execution) was shown as „Zakończ” with the question „Zakończyć kategorię?” (PRD FR-CAT-17, change `cancel-button-ghost`). That copy suggests ending the Category or the year, and „Zakończ” is on the _Avoid_ list of Year Close. The action only levels the Current Plan to Execution, as `mvp.md:43` and `prd-mvp.md:55,119` already describe it.

Rule. In the UI Align is „Wyrównaj”; the confirmation asks „Wyrównać plan do wykonania?”; the toast says „Plan bieżący wyrównany do wykonania.”. The glossary has an **Align** entry with this copy and an _Avoid_ list: Zakończ, Zakończyć kategorię, Close (for Align). The Year Close _Avoid_ list keeps „Zakończ”.

Consequences. Supersedes the copy of PRD FR-CAT-17 and of `context/archive/2026-11-06-cancel-button-ghost/`. Only copy changes: the keys `budget.align_current_plan` and `budget.align_current_plan_confirm_title` keep their names, there is no rule change and no prop change.

We rejected:

- „Zakończ” kept with a clearer body (the verb itself reads as closing the Category or the year);
- „Zamknij” (collides with Year Close and with closing a dialog).
