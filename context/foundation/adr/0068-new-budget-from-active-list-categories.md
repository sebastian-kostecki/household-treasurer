# A new Budget takes every active list Category, with the plan from the newest earlier Budget

ADR-0063 says a new Budget gets Budget Lines from the active Categories of the list, with the plan suggested from “the previous year’s Current Plan” (FR-005). It does not say what happens when the previous year has no Budget (a gap in the years, a Budget created for a year before every existing one), when the earlier Budget is a draft, or what a Category missing from the source gets. This ADR settles those. It clarifies the words “previous year” about creating a Budget in three places only: “Joining Budget Lines” and the new wording of ADR-0014 in ADR-0063, and the part of the new wording of ADR-0033 about Month Item Kind. It changes no other rule of ADR-0063, which stays as it is.

Set of Budget Lines. A new Budget gets one Budget Line for every active (not archived) Category of the User’s list, and none for an archived one. An empty list gives an empty draft. There is no choice of source and no option to create a Budget without Categories.

Source of the plan and the flags. The source is the newest earlier Budget of the User: the Budget with the highest year smaller than the year of the new Budget, a draft included. The Budget Line of the same Category in the source gives the plan and the flags (Paced / One-off, Month Item Kind):

- the plan is the Current Plan of that Budget Line; the new Initial Plan and Execution are 0 (the Initial Plan is frozen when execution starts, ADR-0063);
- the Category has no Budget Line in the source: plan 0 and the default flags (Paced, Running);
- the Budget Line in the source is archived: plan 0 and the flags of that Budget Line;
- there is no earlier Budget at all: plan 0 and the default flags for every Category. A year earlier than every existing Budget is no exception to the rule: it gets the active Categories with plan 0.

The newest earlier Budget is the source even when it has no Budget Line (an old empty draft): every active Category then gets plan 0 and the default flags, and no older Budget is consulted. So the year of the source follows from the years of the User’s Budgets alone, and the “Nowy budżet” dialog can show it from `budgetYears` without asking the server.

Existing Budgets. The rule applies only when a Budget is created. A draft that already exists is not filled up at deployment or when another Budget is created; a Category missing from it joins at its first use, as ADR-0063 says. The January Proposal of ADR-0033 still reads December of the same Category in the Budget of the year − 1; this ADR does not touch it.

Example on production data. Budget 2026 is in execution and the draft 2027 has 38 Budget Lines. Creating 2028 takes the source 2027: 40 active Categories give 40 Budget Lines, with the plans and flags of the 2027 draft. „Jedzenie poza domem” and „Eksploatacja domu” have no Budget Line in 2027 (their counterparts there are „Restauracje” and „Utrzymanie domu”, to be merged in S-21), so they get plan 0 and the default flags. The 2027 draft stays with its 38 Budget Lines and Budget 2026 does not change.

We rejected:

- strictly year − 1 as the source (a gap in the years would give nothing but zeros);
- only a Budget in execution as the source (it skips the corrections typed in the draft);
- taking the plan from a later Budget when creating a year backwards (a plan from the future is not a suggestion from the past);
- an empty draft for a year backwards (an exception to FR-005);
- a choice of the plan’s year in the dialog (one more decision with no data to make it on, and the dialog could no longer state the result in one sentence);
- an action “Uzupełnij z listy” for existing drafts (on production data it would double the limits of the pairs awaiting the merge in S-21).

Numbering. This ADR was written in parallel with ADR-0066 (Rhythm, F-05) and ADR-0067 (choice and archive on the list, S-19) and was first committed as ADR-0066; it was renumbered to ADR-0068 when the branches were joined, without a change to its rule. The flags it copies (Paced / One-off, Month Item Kind) are legacy until S-24 (ADR-0066); once S-24 replaces them with Rhythm, a new Budget stops copying them, and the set of Budget Lines and the source of the plan stay as this ADR says.
