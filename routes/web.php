<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CapitalMovementController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dev\AccountsKitchenSinkController;
use App\Http\Controllers\Dev\DashboardKitchenSinkController;
use App\Http\Controllers\Dev\DialogsKitchenSinkController;
use App\Http\Controllers\Dev\ImportPreviewKitchenSinkController;
use App\Http\Controllers\Dev\ImportStartKitchenSinkController;
use App\Http\Controllers\Dev\SettingsKitchenSinkController;
use App\Http\Controllers\Dev\SidebarKitchenSinkController;
use App\Http\Controllers\Dev\TermDepositsKitchenSinkController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\MonthPlanController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OwnTransferController;
use App\Http\Controllers\TermDepositController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
    Route::get('dev/ui/dashboard', [DashboardKitchenSinkController::class, 'show'])->name('dev.ui.dashboard');
    Route::get('dev/ui/accounts', [AccountsKitchenSinkController::class, 'show'])->name('dev.ui.accounts');
    Route::get('dev/ui/import', [ImportStartKitchenSinkController::class, 'show'])->name('dev.ui.import');
    Route::get('dev/import-preview', [ImportPreviewKitchenSinkController::class, 'show'])->name('dev.import-preview');
    Route::get('dev/ui/dialogs', [DialogsKitchenSinkController::class, 'show'])->name('dev.ui.dialogs');
    Route::post('dev/ui/dialogs/validate', [DialogsKitchenSinkController::class, 'validate'])->name('dev.ui.dialogs.validate');
    Route::get('dev/ui/settings', [SettingsKitchenSinkController::class, 'show'])->name('dev.ui.settings');
    Route::get('dev/ui/sidebar', [SidebarKitchenSinkController::class, 'show'])->name('dev.ui.sidebar');
    Route::get('dev/ui/term-deposits', [TermDepositsKitchenSinkController::class, 'show'])->name('dev.ui.term-deposits');
    Route::post('dashboard/first-run-checklist/dismiss', [DashboardController::class, 'dismissFirstRunChecklist'])
        ->name('dashboard.first-run-checklist.dismiss');
    Route::get('budget', [BudgetController::class, 'show'])->name('budget');

    if (app()->isLocal()) {
        Route::inertia('dev/kitchen-sink/budget', 'dev/BudgetKitchenSink');
    }

    Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store');
    Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');
    Route::post('budgets/{budget}/start-execution', [BudgetController::class, 'startExecution'])->name('budgets.start-execution');
    Route::post('budget/year', [BudgetController::class, 'selectYear'])->name('budget.select-year');
    Route::get('month-plan', [MonthPlanController::class, 'show'])->name('month-plan');
    if (app()->environment('local')) {
        Route::inertia('dev/month-plan', 'dev/MonthPlanSink');
        Route::inertia('dev/operations', 'dev/OperationsSink');
    }
    Route::post('budgets/{budget}/month-plans/{month}/approve', [MonthPlanController::class, 'approve'])
        ->whereNumber('month')
        ->name('month-plans.approve');
    Route::patch('budgets/{budget}/month-plans/{month}', [MonthPlanController::class, 'update'])
        ->whereNumber('month')
        ->name('month-plans.update');
    Route::post('budgets/{budget}/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('budgets/{budget}/categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::patch('categories/{category}/month-item-kind', [CategoryController::class, 'updateMonthItemKind'])->name('categories.month-item-kind');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('categories/{category}/archive', [CategoryController::class, 'archive'])->name('categories.archive');
    Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore');
    Route::post('categories/{category}/align-current-plan', [CategoryController::class, 'alignCurrentPlan'])->name('categories.align-current-plan');
    Route::resource('accounts', AccountController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('accounts/{account}/archive', [AccountController::class, 'archive'])->name('accounts.archive');
    Route::post('accounts/{account}/restore', [AccountController::class, 'restore'])->name('accounts.restore');
    Route::resource('term-deposits', TermDepositController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('term-deposits/{term_deposit}/archive', [TermDepositController::class, 'archive'])->name('term-deposits.archive');
    Route::post('term-deposits/{term_deposit}/restore', [TermDepositController::class, 'restore'])->name('term-deposits.restore');
    Route::get('operations', [OperationsController::class, 'index'])->name('operations');
    Route::get('operations/import', [CsvImportController::class, 'create'])->name('operations.import.create');
    Route::post('operations/import/preview', [CsvImportController::class, 'preview'])->name('operations.import.preview.store');
    Route::get('operations/import/preview', [CsvImportController::class, 'showPreview'])->name('operations.import.preview');
    Route::post('operations/import', [CsvImportController::class, 'store'])->name('operations.import.store');
    Route::post('incomes', [IncomeController::class, 'store'])->name('incomes.store');
    Route::patch('incomes/{financial_operation}', [IncomeController::class, 'update'])->name('incomes.update');
    Route::delete('incomes/{financial_operation}', [IncomeController::class, 'destroy'])->name('incomes.destroy');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::patch('expenses/{financial_operation}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('expenses/{financial_operation}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::resource('own-transfers', OwnTransferController::class)->only(['store', 'update', 'destroy']);
    Route::resource('capital-movements', CapitalMovementController::class)->only(['store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
