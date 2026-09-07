<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OwnTransferController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('budget', [BudgetController::class, 'show'])->name('budget');
    Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store');
    Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');
    Route::post('budgets/{budget}/start-execution', [BudgetController::class, 'startExecution'])->name('budgets.start-execution');
    Route::post('budget/year', [BudgetController::class, 'selectYear'])->name('budget.select-year');
    Route::post('budgets/{budget}/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::resource('accounts', AccountController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('operations', [OperationsController::class, 'index'])->name('operations');
    Route::resource('own-transfers', OwnTransferController::class)->only(['store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
