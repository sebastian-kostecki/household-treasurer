---
paths:
    - 'app/Actions/*Budget*.php'
    - 'app/Actions/*Categor*.php'
    - 'app/Models/Category*.php'
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

ADR-0063 is decided but the code does not implement it yet: a Category still belongs to one Budget. The glossary calls the yearly record a Budget Line, so do not write „per-Budget” anything. Until S-18, years are linked by name and type in six places, and new code does not add a seventh; S-18 replaces them with a join by id.

- `app/Actions/CreateBudget.php:29-51` copies the previous Budget's Categories as new records;
- `app/Support/MonthPlanProposal.php:198` matches the previous year's Category by name and type;
- `app/Support/OperationsListQuery.php:144-145` filters Operations by `category_name` and `category_type`, fed by the request input `app/Http/Requests/IndexOperationsRequest.php:29-30`;
- `app/Support/OperationsFilterOptions.php:44` groups the filter options by type and name;
- `app/Support/CsvImport/ImportCategorySuggester.php:87,112,122-124` finds the target Category by year, type and name;
- `app/Support/CsvImport/ImportCategorySuggestionModel.php:61,133-160` counts votes per type and name.

## The name key is compared only in the migration, the snapshot conversion and the list's uniqueness

The name key is the name trimmed and case-insensitive (Unicode); diacritics count (ADR-0063, „Names”). Comparing by name stays in three places: the migration steps, the conversion of snapshots v1–v5 (ADR-0063, „Migration and snapshot”), and the uniqueness of (name key, type) on the list, archived Categories included. Everything else uses the Category id.

## Archive has two levels, and the year's plan lives on the Budget Line

The archive of the list Category only decides choice: operations, Month Plan, new Budget. Budget totals, Dashboard counters, the archive section of the Budget and the Month Plan Proposal read `archived_at` of the Budget Line. Initial Plan, Current Plan and Execution live on the Budget Line, and until F-05 so do the Paced / One-off flag and the Month Item Kind.
