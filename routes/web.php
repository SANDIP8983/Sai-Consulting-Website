<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingsController;

Route::get('/', function () {
    return view('frontend.home');
});

use App\Http\Controllers\CustomerRequestController;

Route::get('/request', [CustomerRequestController::class, 'create'])
    ->name('request.create');

Route::post('/request', [CustomerRequestController::class, 'store'])
    ->name('request.store');

Route::get('/request/success', [CustomerRequestController::class, 'success'])
    ->name('request.success');

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->controller(LoginController::class)->group(function () {
        Route::get('/login', 'create')->name('login');
        Route::post('/login', 'store')->middleware('throttle:5,1')->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('/logout', [LoginController::class, 'destroy'])->name('admin.logout');
    });
});

Route::middleware('auth')
    ->prefix('admin/services')
    ->name('admin.services.')
    ->controller(ServiceController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{service}/edit', 'edit')->name('edit');
        Route::put('/{service}', 'update')->name('update');
        Route::delete('/{service}', 'destroy')->name('destroy');
    });

Route::middleware('auth')
    ->prefix('admin/settings')
    ->name('admin.settings.')
    ->controller(SettingsController::class)
    ->group(function () {
        Route::get('/website', 'website')->name('website');
        Route::put('/website', 'updateWebsite')->name('website.update');
        Route::get('/office', 'office')->name('office');
        Route::put('/office', 'updateOffice')->name('office.update');
        Route::get('/contact', 'contact')->name('contact');
        Route::put('/contact', 'updateContact')->name('contact.update');
        Route::get('/office-timings', 'officeTimings')->name('office-timings');
        Route::put('/office-timings', 'updateOfficeTimings')->name('office-timings.update');
        Route::get('/holidays', 'holidays')->name('holidays');
        Route::post('/holidays', 'storeHoliday')->name('holidays.store');
        Route::get('/holidays/{holiday}/edit', 'editHoliday')->name('holidays.edit');
        Route::put('/holidays/{holiday}', 'updateHoliday')->name('holidays.update');
        Route::delete('/holidays/{holiday}', 'destroyHoliday')->name('holidays.destroy');
    });
