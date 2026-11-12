# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Actions/*Budget*.php, app/Actions/*Categor*.php, app/Models/Category*.php, app/Models/BudgetLine.php, app/Support/BudgetLineResolver.php, app/Support/CategoryList/**/*.php, app/Support/MonthPlanProposal.php, app/Support/Operations*.php, app/Support/CsvImport/ImportCategory*.php, app/Http/Requests/*Categor*.php, app/Http/Requests/IndexOperationsRequest.php, app/Http/Controllers/CategoryController.php, app/Http/Controllers/CategoryMergeController.php, app/Concerns/CategoryValidationRules.php | .ai/rules/categories.md |
| resources/css/app.css | .ai/rules/css.md |
| resources/js/**/*.test.ts, vite.config.ts, package.json, tsconfig.json | .ai/rules/frontend-toolchain.md |
| app/Http/Controllers/**/*.php, resources/js/pages/**/*.vue | .ai/rules/inertia-props.md |
| resources/js/pages/auth/**, resources/js/layouts/auth/**, resources/js/layouts/AuthLayout.vue, resources/js/components/PasskeyVerify.vue, resources/js/components/PasswordInput.vue, resources/js/components/TextLink.vue, resources/js/lib/auth-*.ts | .ai/rules/js-auth.md |
| resources/js/components/*Dialog.vue, resources/js/components/FormField.vue, resources/js/components/FormPreview.vue, resources/js/components/ui/dialog/**, resources/js/lib/operation-dialog-preview*.ts, resources/js/composables/useOperationAmountInput*.ts | .ai/rules/js-dialogs.md |
| resources/js/pages/OperationsImport*.vue, resources/js/components/ImportPreview*.vue, resources/js/lib/import-*.ts | .ai/rules/js-import.md |
| resources/js/pages/Welcome.vue, resources/js/components/Landing*.vue | .ai/rules/js-landing.md |
| resources/js/pages/MonthPlan.vue, resources/js/components/MonthPlan*.vue, resources/js/components/Month*.vue, resources/js/components/BudgetMonthPicker.vue, resources/js/lib/month-plan-*.ts, tests/Unit/MonthPlanHardcodedUiTest.php | .ai/rules/js-month-plan.md |
| resources/js/pages/Operations.vue, resources/js/components/Operation*.vue, resources/js/components/Operations*.vue, resources/js/components/FinancialOperation*.vue, resources/js/components/CapitalMovement*.vue, resources/js/lib/operations-*.ts | .ai/rules/js-operations.md |
| resources/js/components/AppSidebar*.vue, resources/js/components/AppMobileBar.vue, resources/js/components/BudgetYear*.vue, resources/js/components/CommandPalette*.vue, resources/js/components/ui/sidebar/**, resources/js/layouts/**/*.vue, resources/js/lib/nav-active*.ts, resources/js/lib/global-shortcuts*.ts, resources/js/lib/command-palette*.ts, resources/js/lib/budget-year-segments*.ts | .ai/rules/js-sidebar.md |
| resources/js/**/*.vue | .ai/rules/js.md |
| database/migrations/**/*.php, tests/Feature/Migrations/** | .ai/rules/migrations.md |
| app/Support/MonthRemainingCalculator.php, app/Support/ToSpendCalculator.php, app/Support/MonthItemDeviation.php, app/Support/MonthPaceCalculator.php, app/Support/CollectedCalculator.php, resources/js/lib/month-remaining-calculator*.ts, resources/js/lib/to-spend-calculator*.ts, resources/js/lib/month-item-deviation*.ts, resources/js/lib/month-pace-calculator*.ts, resources/js/lib/collected-calculator*.ts, resources/js/lib/hand-entry-month-remaining-preview.ts, resources/js/lib/import-month-remaining-warnings.ts, resources/js/lib/entry-to-spend-warning*.ts, resources/js/lib/import-to-spend-warnings*.ts, tests/Unit/MonthRemainingCalculatorTest.php, tests/Unit/ToSpendCalculatorTest.php, tests/Unit/MonthItemDeviationTest.php, tests/Unit/MonthPaceCalculatorTest.php, tests/Unit/CollectedCalculatorTest.php | .ai/rules/month-calculators.md |
| app/Support/**/*.php, resources/js/pages/**/*.vue, resources/js/components/ui/chart/**, tests/Feature/Nfr/*.php, resources/js/components/Report*.vue, resources/js/lib/format-share*.ts, resources/js/lib/report-chart*.ts, resources/js/lib/report-trend*.ts, resources/js/lib/report-window*.ts, resources/js/lib/reports-fixtures.ts, tests/Unit/ReportsHardcodedUiTest.php | .ai/rules/reports.md |
| app/**/*.php, .sloppy-baseline.json | .ai/rules/sloppy.md |
| app/Actions/BackupUserData.php, app/Actions/RestoreUserData.php, tests/Feature/UserSnapshotCommandTest.php, tests/Feature/ApplicationBackupRestoreTest.php | .ai/rules/snapshot.md |
| app/Support/DashboardNotices.php | .ai/rules/support.md |
| routes/*.php, app/Http/Controllers/**/*.php, resources/js/**/*.vue | .ai/rules/wayfinder.md |
