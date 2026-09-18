<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\GoogleCalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Icd10Controller;
use App\Http\Controllers\MeasurementTemplateController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

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
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');

    Route::get('settings/google-calendar', [GoogleCalendarController::class, 'edit'])->name('google-calendar.edit');
    Route::get('settings/google-calendar/redirect', [GoogleCalendarController::class, 'redirect'])->name('google-calendar.redirect');
    Route::get('settings/google-calendar/callback', [GoogleCalendarController::class, 'callback'])->name('google-calendar.callback');
    Route::delete('settings/google-calendar', [GoogleCalendarController::class, 'destroy'])->name('google-calendar.destroy');
});

require __DIR__.'/auth.php';
