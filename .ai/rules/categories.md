---
paths:
    - 'app/Actions/*Budget*.php'
    - 'app/Actions/*Categor*.php'
    - 'app/Models/Category*.php'
    - 'app/Models/BudgetLine.php'
    - 'app/Support/BudgetLineResolver.php'
    - 'app/Support/CategoryList/**/*.php'
    - 'app/Support/MonthPlanProposal.php'
    - 'app/Support/Operations*.php'
    - 'app/Support/CsvImport/ImportCategory*.php'
    - 'app/Http/Requests/*Categor*.php'
    - 'app/Http/Requests/IndexOperationsRequest.php'
    - 'app/Http/Controllers/CategoryController.php'
    - 'app/Concerns/CategoryValidationRules.php'
---

# Categories

## A Category belongs to the User for all years; the year's record is a Budget Line

A Category is a row of the User's list (`categories`: `user_id`, `name`, `name_key`, `icon`, `color`, `description`, `sort_order`, `type`, `archived_at`; ADR-0063, ADR-0067). Its record in one Budget is a Budget Line (`budget_lines`: `budget_id`, `category_id`, Initial Plan, Current Plan, Execution, `archived_at`; `unique(budget_id, category_id)`). The Rhythm of an expense Category is a history on the list Category (`category_rhythms`, ADR-0066, ADR-0072), not on the Budget Line: read it through `App\Support\CategoryRhythms` (one query for a set of Categories) for the month the reading is about. Financial Operations and Month Plan items point at the Budget Line (`budget_line_id`, ADR-0065). The glossary calls the yearly record a Budget Line, so do not write „per-Budget” anything.

- On the HTTP boundary and in Inertia props `category_id` is always the id of the list Category. The server finds the Budget Line itself: an operation is checked by `CategoryList/OperationCategoryChoice::check()` and written through `ManageFinancialOperation::lockLine()` (`BudgetLineResolver::forBudget()`, a Category without a line joins through `JoinBudgetAtFirstUse`); Month Plan amounts are split by `BudgetLineResolver::partitionAmounts()` into lines and Categories to join. A missing Budget, a draft Budget, an archived line on a changed Category and an archived Category without a line give the same errors as before (ADR-0067).
- Years are linked by the Category id, never by name and type: not in Create Budget, the January proposal, the Operations filter and its options, the Import suggestions, nor the Dashboard and palette links. New code does not add a join by name.
- `description` is optional plain text of up to 500 characters, shown only on „Kategorie”, never in a selector (ADR-0067). A selector takes its options from `CategoryList/CategoryChoiceOptions` (operations, Import) or `MonthPlanJoinCandidates` (Month Plan join rows), and a warning reading adds Categories that will join through `CategoryChoiceOptions::joiningEntries()`, never from a query of its own.
- Name, icon, colour, type and order are written on the list Category (all years at once); the plan on the Budget Line; the Rhythm on the list Category, from the current month and only through `ChangeCategoryRhythm` (ADR-0072). Read the description through `$line->category` with eager loading or a join (Sloppy flags a query in a loop).
- The opening Collected of an irregular Category (`category_rhythms.opening_collected_minor_units`, ADR-0073) is written only by `ChangeCategoryRhythm` (marking, correcting the amount on the entry that started the current period, removing, marking again), and the Collected at the start of a month is read only through `App\Support\IrregularCollected` (a fixed number of queries for all Categories, months and years). A new reading of „zebrane” never walks the operations itself.
- A Category type does not change once it has a Financial Operation in any year or a Budget Line in a Budget in execution: `app/Support/CategoryList/CategoryTypeChange.php`.

## `CategoryNameKey` is the only comparison of names

`App\Support\CategoryList\CategoryNameKey::of()` is the name trimmed and lower-cased (Unicode); diacritics count (ADR-0063, „Names”). It is stored in `categories.name_key` (PostgreSQL `lower()` depends on the collation, so the key is computed in PHP) and backs `unique(user_id, type, name_key)`, archived Categories included. Create, rename and type change go through it (`CreateCategory`, `UpdateCategory`), and so do the frozen migration and snapshot conversion (`LegacyCategoryConversion`). Nothing else compares names; a new place that needs „the same Category” uses the id.

## Archive has two levels, and the year's plan lives on the Budget Line

The archive of the list Category only decides choice: operations, Month Plan, new Budget. A query for choosable lines (selector options) uses `BudgetLine::openForChoice()`, which checks both levels. Create Budget takes the active Categories of the list (`categories.archived_at IS NULL`), with the plan of their Budget Line in the newest earlier Budget (ADR-0068); the Rhythm is not copied, and neither is it when a Category joins a Budget. Budget totals, Dashboard counters, the archive section of the Budget and the Month Plan Proposal read `archived_at` of the Budget Line. Initial Plan, Current Plan and Execution live on the Budget Line.
