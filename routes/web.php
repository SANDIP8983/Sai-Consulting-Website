<?php

use App\Http\Controllers\Admin\AppointmentBlockController;
use App\Http\Controllers\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CommonRequiredDocumentController;
use App\Http\Controllers\Admin\CustomerNotificationLogController;
use App\Http\Controllers\Admin\CustomerRequestController as AdminCustomerRequestController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GovernmentChargeTypeController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RequestAssignmentController;
use App\Http\Controllers\Admin\RequestDispatchController;
use App\Http\Controllers\Admin\RequestDocumentController;
use App\Http\Controllers\Admin\RequestFinalDocumentController;
use App\Http\Controllers\Admin\RequestPdfController;
use App\Http\Controllers\Admin\RequestProcessingController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\ServiceRequiredDocumentController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffUserController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BrandingAssetController;
use App\Http\Controllers\CustomerRequestController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PublicInformationController;
use App\Http\Controllers\PublicRequestPdfController;
use App\Http\Controllers\PublicServiceController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SignedFinalDocumentController;
use App\Http\Controllers\TrackedFinalDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

Route::get('/services', [PublicServiceController::class, 'index'])->name('services.index');
Route::get('/services/{slug}', [PublicServiceController::class, 'show'])->name('services.show');

Route::controller(PublicInformationController::class)->group(function () {
    Route::get('/required-documents', 'requiredDocuments')->name('required-documents');
    Route::get('/about', 'about')->name('about');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/contact', 'contact')->name('contact');
    Route::get('/privacy-policy', 'privacyPolicy')->name('privacy-policy');
    Route::get('/terms', 'terms')->name('terms');
    Route::get('/refund-policy', 'refundPolicy')->name('refund-policy');
    Route::get('/disclaimer', 'disclaimer')->name('disclaimer');
});

Route::get('/request', [CustomerRequestController::class, 'create'])
    ->name('request.create');

Route::post('/request', [CustomerRequestController::class, 'store'])
    ->middleware('throttle:public-request-submission')
    ->name('request.store');

Route::get('/request/success', [CustomerRequestController::class, 'success'])
    ->name('request.success');

Route::get('/request/track', [CustomerRequestController::class, 'track'])
    ->name('request.track');

Route::post('/request/track', [CustomerRequestController::class, 'lookup'])
    ->middleware('throttle:10,1')
    ->name('request.track.lookup');

Route::get('/request/track/{customerRequest}/pdf/{documentType}', PublicRequestPdfController::class)
    ->middleware('throttle:10,1')
    ->name('request.track.pdf');

Route::get('/request/track/{customerRequest}/final-documents/{finalDocument}', TrackedFinalDocumentController::class)
    ->middleware('throttle:10,1')
    ->name('request.track.final-documents.download');

Route::get('/request/final-documents/{customerRequest}/{finalDocument}', SignedFinalDocumentController::class)
    ->middleware(['signed', 'throttle:20,1'])
    ->name('request.final-documents.signed');

Route::get('/branding/{asset}', [BrandingAssetController::class, 'publicAsset'])->name('branding.asset');
Route::get('/appointments', [AppointmentController::class, 'create'])->name('appointments.create');
Route::get('/appointments/availability', [AppointmentController::class, 'availability'])->middleware('throttle:60,1')->name('appointments.availability');
Route::post('/appointments', [AppointmentController::class, 'store'])->middleware('throttle:10,1')->name('appointments.store');
Route::get('/appointments/success', [AppointmentController::class, 'success'])->name('appointments.success');

Route::get('/admin', fn () => auth()->check()
    ? to_route('admin.dashboard')
    : to_route('login'))
    ->name('admin.index');

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->controller(LoginController::class)->group(function () {
        Route::get('/login', 'create')->name('login');
        Route::post('/login', 'store')->middleware('throttle:5,1')->name('login.store');
    });

    Route::middleware(['auth', 'active'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('/logout', [LoginController::class, 'destroy'])->name('admin.logout');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('admin.profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('admin.profile.update');
        Route::put('/profile/password', [ProfileController::class, 'password'])->name('admin.profile.password');

        Route::prefix('users')->name('admin.users.')->controller(StaffUserController::class)->middleware('can:users.manage')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{user}/edit', 'edit')->name('edit');
            Route::put('/{user}', 'update')->name('update');
            Route::put('/{user}/password', 'resetPassword')->name('password');
            Route::delete('/{user}', 'destroy')->name('destroy');
        });
    });
});

Route::middleware(['auth', 'active', 'can:requests.view', 'request.assigned'])
    ->prefix('admin/requests')
    ->name('admin.requests.')
    ->controller(AdminCustomerRequestController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->middleware('can:requests.manage')->name('create');
        Route::post('/', 'store')->middleware('can:requests.manage')->name('store');
        Route::get('/{customerRequest}', 'show')->name('show');
        Route::patch('/{customerRequest}/status', 'transition')->middleware('can:requests.manage')->name('transition');
        Route::patch('/{customerRequest}/services/{requestService}', 'decideService')->middleware('can:requests.approve')->name('services.decision');
        Route::patch('/{customerRequest}/case-planning', 'saveCasePlanning')->middleware('can:requests.approve')->name('case-planning.save');
        Route::post('/{customerRequest}/services', 'addService')->middleware('can:requests.approve')->name('services.add');
        Route::patch('/{customerRequest}/services/{requestService}/fee', 'updateServiceFee')->middleware('can:billing.manage')->name('services.fee.update');
        Route::delete('/{customerRequest}/services/{requestService}', 'removeService')->middleware('can:requests.approve')->name('services.remove');
        Route::patch('/{customerRequest}/case-planning/reject', 'rejectCase')->middleware('can:requests.approve')->name('case-planning.reject');
        Route::patch('/{customerRequest}/case-planning/complete', 'completePlanned')->middleware('can:requests.approve')->name('case-planning.complete');
        Route::patch('/{customerRequest}/work-scopes/{workScope}', 'updateWorkScope')->middleware('can:processing.manage')->name('work-scopes.update');
        Route::patch('/{customerRequest}/billing/finalize', 'finalizeBilling')->middleware('can:billing.manage')->name('billing.finalize');
        Route::patch('/{customerRequest}/billing/unlock', 'unlockBilling')->middleware('can:billing.manage')->name('billing.unlock');
        Route::patch('/{customerRequest}/estimate', 'estimate')->middleware('can:billing.manage')->name('estimate');
        Route::patch('/{customerRequest}/contact', 'updateContact')->middleware('can:requests.manage')->name('contact.update');
        Route::post('/{customerRequest}/remarks', 'remark')->middleware('can:requests.manage')->name('remarks.store');
        Route::post('/{customerRequest}/payments', 'payment')->middleware('can:payments.manage')->name('payments.store');
        Route::patch('/{customerRequest}/fee', 'fee')->middleware('can:billing.manage')->name('fee.update');
        Route::put('/{customerRequest}/assignment', RequestAssignmentController::class)->middleware('can:requests.assign')->name('assignment.update');
        Route::get('/{customerRequest}/documents/{document}', RequestDocumentController::class)->name('documents.download');
        Route::post('/{customerRequest}/final-documents', [RequestFinalDocumentController::class, 'store'])->middleware('can:requests.manage')->name('final-documents.store');
        Route::post('/{customerRequest}/final-documents/send', [RequestFinalDocumentController::class, 'send'])->middleware('can:requests.manage')->name('final-documents.send');
        Route::delete('/{customerRequest}/final-documents/{finalDocument}', [RequestFinalDocumentController::class, 'destroy'])->middleware('can:requests.manage')->name('final-documents.destroy');
        Route::get('/{customerRequest}/final-documents/{finalDocument}', [RequestFinalDocumentController::class, 'download'])->name('final-documents.download');
        Route::get('/{customerRequest}/pdf/{documentType}', RequestPdfController::class)->name('pdf.download');
    });

Route::middleware(['auth', 'active', 'can:settings.manage'])->get('/admin/settings/branding/{asset}', [BrandingAssetController::class, 'privateAsset'])->name('admin.settings.branding.asset');
Route::middleware(['auth', 'active', 'can:notifications.manage'])->group(function () {
    Route::get('/admin/settings/customer-notifications', [SettingsController::class, 'customerNotifications'])->name('admin.settings.customer-notifications');
    Route::put('/admin/settings/customer-notifications', [SettingsController::class, 'updateCustomerNotifications'])->name('admin.settings.customer-notifications.update');
});
Route::get('/admin/notifications', CustomerNotificationLogController::class)->middleware(['auth', 'active', 'can:notifications.view'])->name('admin.notifications.index');
Route::middleware(['auth', 'active', 'can:appointments.manage'])->prefix('admin/appointments')->name('admin.appointments.')->controller(AdminAppointmentController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{appointment}', 'show')->name('show');
    Route::patch('/{appointment}/status', 'transition')->name('transition');
});
Route::middleware(['auth', 'active', 'can:appointments.manage'])->prefix('admin/appointment-blocks')->name('admin.appointment-blocks.')->controller(AppointmentBlockController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{appointmentBlock}', 'update')->name('update');
    Route::delete('/{appointmentBlock}', 'destroy')->name('destroy');
});

Route::middleware(['auth', 'active', 'can:dispatch.manage', 'request.assigned'])->prefix('admin/requests/{customerRequest}/dispatches')->name('admin.requests.dispatches.')->controller(RequestDispatchController::class)->group(function () {
    Route::post('/', 'store')->name('store');
    Route::patch('/{dispatch}', 'update')->name('update');
    Route::patch('/{dispatch}/status', 'transition')->name('status');
    Route::patch('/{dispatch}/reopen', 'reopen')->name('reopen');
    Route::post('/{dispatch}/proofs', 'uploadProof')->name('proofs.store');
    Route::get('/{dispatch}/proofs/{proof}', 'downloadProof')->name('proofs.download');
});
Route::middleware(['auth', 'active', 'can:dispatch.manage', 'request.assigned'])->prefix('admin/requests/{customerRequest}/closure')->name('admin.requests.closure.')->controller(RequestDispatchController::class)->group(function () {
    Route::patch('/', 'close')->name('close');
    Route::patch('/reopen', 'reopenCase')->name('reopen');
});

Route::middleware(['auth', 'active', 'can:processing.manage', 'request.assigned'])->prefix('admin/requests/{customerRequest}/processing')->name('admin.requests.processing.')->controller(RequestProcessingController::class)->group(function () {
    Route::patch('/work-items/{workScope}', 'updateWorkItem')->name('work-items.update');
    Route::patch('/work-items/{workScope}/reopen', 'reopenWorkItem')->name('work-items.reopen');
    Route::patch('/work-items', 'bulkWorkItems')->name('work-items.bulk');
    Route::patch('/complete', 'completeCase')->name('complete');
    Route::patch('/reopen', 'reopenCase')->name('reopen');
    Route::post('/open', 'open')->name('open');
    Route::patch('/file', 'updateFile')->name('file.update');
    Route::patch('/drafting', 'updateDrafting')->name('drafting.update');
    Route::patch('/stage', 'transition')->name('stage.update');
    Route::patch('/registration', 'updateRegistration')->name('registration.update');
    Route::patch('/post-registration', 'updatePostRegistration')->name('post-registration.update');
    Route::post('/registered-scan', 'storeRegisteredScan')->name('registered-scan.store');
});

Route::middleware(['auth', 'active', 'can:services.manage'])
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

Route::middleware(['auth', 'active', 'can:services.manage'])->prefix('admin/government-charge-types')->name('admin.government-charge-types.')->controller(GovernmentChargeTypeController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{governmentChargeType}', 'update')->name('update');
});

Route::middleware(['auth', 'active', 'can:documents.manage'])->prefix('admin/required-documents')->name('admin.required-documents.')->controller(ServiceRequiredDocumentController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::patch('/service/{service}/reorder', 'reorder')->name('reorder');
    Route::put('/service/{service}/mappings', 'updateMappings')->name('mappings.update');
    Route::get('/{requiredDocument}/edit', 'edit')->name('edit');
    Route::put('/{requiredDocument}', 'update')->name('update');
    Route::delete('/{requiredDocument}', 'destroy')->name('destroy');
});
Route::middleware(['auth', 'active', 'can:documents.manage'])->prefix('admin/required-document-master')->name('admin.required-document-master.')->controller(CommonRequiredDocumentController::class)->group(function () {
    Route::post('/', 'store')->name('store');
    Route::get('/{masterDocument}/edit', 'edit')->name('edit');
    Route::put('/{masterDocument}', 'update')->name('update');
});

Route::middleware(['auth', 'active', 'can:settings.manage'])
    ->prefix('admin/settings')
    ->name('admin.settings.')
    ->controller(SettingsController::class)
    ->group(function () {
        Route::get('/', 'companyBranding')->name('company-branding');
        Route::put('/', 'updateCompanyBranding')->name('company-branding.update');
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
