# Category choice, archive and description on the list

ADR-0063 moves the Category to the User, gives it an archive that only decides choice, and says when a Budget Line joins a Budget. S-19 makes the „Kategorie” page writable (PRD v3, FR-002, FR-003, FR-019, FR-022) and moves the selectors of operations, Import and the Month Plan to the list, and ADR-0063 does not say what the selectors offer in a Budget where the Category has no Budget Line yet, what saving an old operation on an archived Category means, when the Month Plan creates a Budget Line, or what the description is. This ADR settles those. It supplements ADR-0063 and changes none of its rules; ADR-0065 (the Budget Line is what an operation points at) and ADR-0066 (Rhythm) stay as they are.

Choice in operations and Import. The selector offers the active Categories of the list, for the Budget in execution of the year of the operation’s date, whether or not that Budget has a Budget Line of the Category. A Category without one joins the Budget at the save (ADR-0063, „Joining Budget Lines”) with plan 0 and with the Paced / One-off flag and the Month Item Kind of the Category’s newest Budget Line, or Paced and Running when it has none. A Budget Line archived in year Y is not offered in Y, even when the list Category is active; it is offered in the other years where the Budget Line is active. Archive of the list and archive of the Budget Line both only decide choice, as in ADR-0063.

Saving an existing operation. An edit of a Financial Operation that keeps its Category passes when that Category is archived on the list or its Budget Line is archived, as long as the Budget of the year of the date has a Budget Line of the Category; nothing is created, and an archived Category never joins a Budget. Changing the date to a year without a Budget Line of an archived Category is rejected as before. Changing the Category to another one follows the choice rule above.

Month Plan. An amount above 0 for an active Category without a Budget Line in the Budget of the plan creates the Budget Line with plan 0, at the approval and at a change of the approved plan; an amount of 0 creates nothing. An archived Category is not offered and does not join.

Archive and delete. Archiving is available for every active Category, with no condition on a Budget Line in a Budget in execution. Restoring and the effect on Budget Lines are as in ADR-0063. Deleting from the list follows the condition of ADR-0063 without the clause on Cash Expectation, which is added with Cash Expectation itself: no Financial Operation in any year and no Budget Line in a Budget in execution; otherwise the answer is archive.

Description. An optional plain text of up to 500 characters on the list Category (`categories.description`), shown and edited only on „Kategorie”. The selectors, the Month Plan, Import and the reports do not show it. The snapshot carries it from version 7; older snapshots restore with no description.

Rejected:
- the selector offering only Categories with a Budget Line, and the Month Plan creating nothing (a Category created in November would be missing from the selectors until someone typed a plan in the Budget);
- no joining at all, a Budget Line created only in the Budget (the first expense on a new Category would fail);
- blocking the edit of an operation on an archived Category (the history could not be corrected);
- saving an archived Category only in the year it was archived (the rule would depend on a date nobody stores);
- archive allowed only with a Budget Line in a Budget in execution (a Category with only a draft line could be neither archived nor deleted);
- archive without delete (a Category created by mistake would stay on the list for good);
- the description in the selectors (it would turn a choice list into a reading list and lengthen every row).
