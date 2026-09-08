<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OwnTransferController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
    Route::post('dashboard/first-run-checklist/dismiss', [DashboardController::class, 'dismissFirstRunChecklist'])
        ->name('dashboard.first-run-checklist.dismiss');
    Route::get('budget', [BudgetController::class, 'show'])->name('budget');
    Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store');
    Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');
    Route::post('budgets/{budget}/start-execution', [BudgetController::class, 'startExecution'])->name('budgets.start-execution');
    Route::post('budget/year', [BudgetController::class, 'selectYear'])->name('budget.select-year');
    Route::post('budgets/{budget}/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('categories/{category}/archive', [CategoryController::class, 'archive'])->name('categories.archive');
    Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore');
    Route::resource('accounts', AccountController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('accounts/{account}/archive', [AccountController::class, 'archive'])->name('accounts.archive');
    Route::post('accounts/{account}/restore', [AccountController::class, 'restore'])->name('accounts.restore');
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
});

require __DIR__.'/settings.php';
