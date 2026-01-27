<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountBalanceController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\MonthController;
use App\Http\Controllers\MonthEntryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('install')->name('install.')->middleware('notInstalled')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('/database', [InstallController::class, 'database'])->name('database');
    Route::post('/database/test', [InstallController::class, 'testDatabase'])->name('database.test');
    Route::get('/app', [InstallController::class, 'app'])->name('app');
    Route::post('/app', [InstallController::class, 'storeApp'])->name('app.store');
    Route::get('/migrate', [InstallController::class, 'migrate'])->name('migrate');
    Route::post('/migrate', [InstallController::class, 'runMigrations'])->name('migrate.run');
    Route::get('/admin', [InstallController::class, 'admin'])->name('admin');
    Route::post('/admin', [InstallController::class, 'storeAdmin'])->name('admin.store');
    Route::get('/finish', [InstallController::class, 'finish'])->name('finish');
});

Route::middleware('installed')->group(function () {
    Route::get('/', function () {
        return auth()->check()
            ? redirect()->route('months.index')
            : redirect()->route('login');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::resource('accounts', AccountController::class)
            ->except(['show']);

        Route::resource('months', MonthController::class);
        Route::post('months/next', [MonthController::class, 'createNext'])
            ->name('months.next');
        Route::post('months/{month}/import-templates', [MonthController::class, 'importTemplates'])
            ->name('months.import-templates');
        Route::patch('months/{month}/balances/{account}', [AccountBalanceController::class, 'update'])
            ->name('months.balances.update');
        Route::patch('months/{month}/balances/{account}/{direction}', [AccountBalanceController::class, 'move'])
            ->name('months.balances.move');

        Route::get('months/{month}/entries', [MonthEntryController::class, 'index'])
            ->name('months.entries.index');
        Route::post('months/{month}/entries', [MonthEntryController::class, 'store'])
            ->name('months.entries.store');
        Route::patch('months/{month}/entries/order', [MonthEntryController::class, 'updateOrder'])
            ->name('months.entries.order');
        Route::post('months/{month}/income-payments', [MonthEntryController::class, 'receivePayment'])
            ->name('months.income-payments.store');
        Route::post('months/{month}/transfers', [MonthEntryController::class, 'storeTransfer'])
            ->name('months.transfers.store');

        Route::get('entries/{entry}/edit', [EntryController::class, 'edit'])
            ->name('entries.edit');
        Route::put('entries/{entry}', [EntryController::class, 'update'])
            ->name('entries.update');
        Route::delete('entries/{entry}', [EntryController::class, 'destroy'])
            ->name('entries.destroy');
        Route::patch('entries/{entry}/toggle-paid', [EntryController::class, 'togglePaid'])
            ->name('entries.toggle-paid');
        Route::post('entries/{entry}/pay', [EntryController::class, 'payFixcost'])
            ->name('entries.pay');
        Route::patch('entries/{entry}/move-next-month', [EntryController::class, 'moveToNextMonth'])
            ->name('entries.move-next-month');
        Route::patch('entries/{entry}/move-prev-month', [EntryController::class, 'moveToPrevMonth'])
            ->name('entries.move-prev-month');

        Route::resource('recurring-templates', RecurringTemplateController::class)
            ->except(['show']);
        Route::patch('recurring-templates/order', [RecurringTemplateController::class, 'updateOrder'])
            ->name('recurring-templates.order');

        Route::middleware('can:viewAny,App\\Models\\User')->prefix('admin')->name('admin.')->group(function () {
            Route::resource('users', AdminUserController::class)->except(['show']);
        });

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    require __DIR__.'/auth.php';
});
