<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('budget', 'Budget')->name('budget');
    Route::inertia('accounts', 'Accounts')->name('accounts');
    Route::inertia('operations', 'Operations')->name('operations');
});

require __DIR__.'/settings.php';
