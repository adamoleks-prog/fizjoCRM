<?php

use App\Http\Controllers\Admin\AccessLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\MessagingSettingsController;
use App\Http\Controllers\Admin\SecurityLogController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SystemStatusController;
use App\Http\Controllers\AiClinicalCaseController;
use App\Http\Controllers\AiRecommendationController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentReminderController;
use App\Http\Controllers\Auth\GoogleCalendarController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingSettingsController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\ConsentTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentAnonymizationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Icd10Controller;
use App\Http\Controllers\MeasurementTemplateController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TherapyCycleController;
use App\Http\Controllers\VisitCardController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Public online booking — no login; every step throttled.
Route::middleware('throttle:booking')->prefix('zapisy')->name('booking.')->group(function () {
    Route::get('/', [BookingController::class, 'index'])->name('index');
    Route::get('kod', [BookingController::class, 'code'])->name('code');
    Route::post('kod', [BookingController::class, 'confirm'])->middleware('throttle:booking-submit')->name('confirm');
    Route::get('gotowe', [BookingController::class, 'done'])->name('done');
    Route::get('odwolaj/{token}', [BookingController::class, 'cancelForm'])->name('cancel');
    Route::post('odwolaj/{token}', [BookingController::class, 'cancel'])->name('cancel.confirm');
    Route::get('{physiotherapist}', [BookingController::class, 'show'])->whereNumber('physiotherapist')->name('show');
    Route::post('{physiotherapist}', [BookingController::class, 'requestCode'])->whereNumber('physiotherapist')->middleware('throttle:booking-submit')->name('request');
});

Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::resource('patients', PatientController::class);

    Route::get('appointments/calendar-feed', [AppointmentController::class, 'calendarFeed'])
        ->name('appointments.calendar-feed');
    Route::get('appointments/slots', [AppointmentController::class, 'slots'])
        ->name('appointments.slots');
    Route::resource('appointments', AppointmentController::class);

    Route::get('icd10/search', [Icd10Controller::class, 'search'])->name('icd10.search');

    Route::get('measurements', [MeasurementTemplateController::class, 'index'])->name('measurements.index');
    Route::post('measurements', [MeasurementTemplateController::class, 'store'])->name('measurements.store');
    Route::delete('measurements/{measurement}', [MeasurementTemplateController::class, 'destroy'])->name('measurements.destroy');

    Route::post('patients/{patient}/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');

    Route::post('documents/{document}/anonymization', [DocumentAnonymizationController::class, 'store'])->name('anonymizations.store');
    Route::get('documents/{document}/anonymization', [DocumentAnonymizationController::class, 'show'])->name('anonymizations.show');
    Route::put('documents/{document}/anonymization', [DocumentAnonymizationController::class, 'update'])->name('anonymizations.update');
    Route::post('documents/{document}/anonymization/approve', [DocumentAnonymizationController::class, 'approve'])->name('anonymizations.approve');
    Route::delete('documents/{document}/anonymization/approval', [DocumentAnonymizationController::class, 'revoke'])->name('anonymizations.revoke');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('therapy-cycles', [TherapyCycleController::class, 'index'])->name('therapy-cycles.index');
    Route::post('appointments/{appointment}/assistant', [TherapyCycleController::class, 'fromAppointment'])->name('therapy-cycles.from-appointment');
    Route::get('therapy-cycles/{therapyCycle}', [TherapyCycleController::class, 'show'])->name('therapy-cycles.show');

    Route::post('therapy-cycles/{therapyCycle}/case', [AiClinicalCaseController::class, 'store'])->name('clinical-cases.store');
    Route::get('therapy-cycles/{therapyCycle}/case', [AiClinicalCaseController::class, 'show'])->name('clinical-cases.show');
    Route::put('therapy-cycles/{therapyCycle}/case', [AiClinicalCaseController::class, 'update'])->name('clinical-cases.update');
    Route::post('therapy-cycles/{therapyCycle}/case/approve', [AiClinicalCaseController::class, 'approve'])->name('clinical-cases.approve');
    Route::delete('therapy-cycles/{therapyCycle}/case/approval', [AiClinicalCaseController::class, 'revoke'])->name('clinical-cases.revoke');

    Route::post('therapy-cycles/{therapyCycle}/recommendations', [AiRecommendationController::class, 'store'])->name('recommendations.store');
    Route::get('recommendations/{recommendation}', [AiRecommendationController::class, 'show'])->name('recommendations.show');
    Route::patch('recommendations/{recommendation}/decision', [AiRecommendationController::class, 'decide'])->name('recommendations.decide');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::get('messaging', [MessagingSettingsController::class, 'edit'])->name('messaging.edit');
        Route::put('messaging', [MessagingSettingsController::class, 'update'])->name('messaging.update');
        Route::post('messaging/test-email', [MessagingSettingsController::class, 'testEmail'])->name('messaging.test-email');
        Route::post('messaging/test-sms', [MessagingSettingsController::class, 'testSms'])->name('messaging.test-sms');
        Route::get('system', [SystemStatusController::class, 'show'])->name('system.show');
        Route::put('system', [SystemStatusController::class, 'update'])->name('system.update');
        Route::post('system/test-alert', [SystemStatusController::class, 'testAlert'])->name('system.test-alert');
        Route::post('system/errors/{appError}/resolve', [SystemStatusController::class, 'resolveError'])->name('system.errors.resolve');
        Route::get('security', [SecurityLogController::class, 'index'])->name('security.index');
        Route::get('access-log', [AccessLogController::class, 'index'])->name('access-log.index');
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('backups/{name}', [BackupController::class, 'download'])->where('name', '[A-Za-z0-9._-]+')->name('backups.download');
    });

    Route::get('patients/{patient}/consents/create', [ConsentController::class, 'create'])->name('consents.create');
    Route::post('patients/{patient}/consents', [ConsentController::class, 'store'])->name('consents.store');
    Route::get('consent-templates', [ConsentTemplateController::class, 'index'])->name('consent-templates.index');
    Route::post('consent-templates', [ConsentTemplateController::class, 'store'])->name('consent-templates.store');
    Route::put('consent-templates/{consentTemplate}', [ConsentTemplateController::class, 'update'])->name('consent-templates.update');
    Route::delete('consent-templates/{consentTemplate}', [ConsentTemplateController::class, 'destroy'])->name('consent-templates.destroy');

    Route::get('appointments/{appointment}/card', [VisitCardController::class, 'show'])->name('visit-cards.show');
    Route::post('appointments/{appointment}/card/send', [VisitCardController::class, 'send'])->name('visit-cards.send');
    Route::post('appointments/{appointment}/reminder', [AppointmentReminderController::class, 'store'])->name('appointments.reminder');

    Route::get('reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');

    Route::get('settings/booking', [BookingSettingsController::class, 'edit'])->name('booking.settings');
    Route::put('settings/booking', [BookingSettingsController::class, 'update'])->name('booking.settings.update');
    Route::post('appointments/{appointment}/approve', [BookingSettingsController::class, 'approve'])->name('booking.approve');
    Route::post('appointments/{appointment}/reject', [BookingSettingsController::class, 'reject'])->name('booking.reject');
    Route::post('appointments/{appointment}/approve-new-patient', [BookingSettingsController::class, 'approveAsNewPatient'])->name('booking.approve-new');

    Route::get('settings/schedule', [WorkScheduleController::class, 'edit'])->name('schedule.edit');
    Route::put('settings/schedule', [WorkScheduleController::class, 'updatePattern'])->name('schedule.pattern');
    Route::post('settings/schedule/day', [WorkScheduleController::class, 'saveDay'])->name('schedule.day');
    Route::delete('settings/schedule/day', [WorkScheduleController::class, 'resetDay'])->name('schedule.day.reset');

    Route::get('settings/google-calendar', [GoogleCalendarController::class, 'edit'])->name('google-calendar.edit');
    Route::get('settings/google-calendar/redirect', [GoogleCalendarController::class, 'redirect'])->name('google-calendar.redirect');
    Route::get('settings/google-calendar/callback', [GoogleCalendarController::class, 'callback'])->name('google-calendar.callback');
    Route::put('settings/google-calendar/calendar', [GoogleCalendarController::class, 'selectCalendar'])->name('google-calendar.calendar');
    Route::post('settings/google-calendar/sync', [GoogleCalendarController::class, 'sync'])->name('google-calendar.sync');
    Route::delete('settings/google-calendar', [GoogleCalendarController::class, 'destroy'])->name('google-calendar.destroy');
});

require __DIR__.'/auth.php';
