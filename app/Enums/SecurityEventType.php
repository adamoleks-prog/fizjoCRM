<?php

namespace App\Enums;

enum SecurityEventType: string
{
    case LoginFailed = 'login_failed';
    case Lockout = 'lockout';
    case Login = 'login';
    case Logout = 'logout';
    case PasswordResetRequested = 'password_reset_requested';
    case PasswordReset = 'password_reset';
    case PasswordChanged = 'password_changed';
    case EmailChanged = 'email_changed';
    case AccessDenied = 'access_denied';
    case RecordNotFound = 'record_not_found';
    case Probe = 'probe';
    case Throttled = 'throttled';
    case CsrfMismatch = 'csrf_mismatch';
    case BookingHoneypot = 'booking_honeypot';
    case BookingRecaptchaFailed = 'booking_recaptcha_failed';
    case BookingCodeFailed = 'booking_code_failed';
    case SettingsChanged = 'settings_changed';
    case BackupDownloaded = 'backup_downloaded';
    case PatientErased = 'patient_erased';

    public function label(): string
    {
        return match ($this) {
            self::LoginFailed => 'Nieudane logowanie',
            self::Lockout => 'Blokada po zbyt wielu próbach logowania',
            self::Login => 'Logowanie',
            self::Logout => 'Wylogowanie',
            self::PasswordResetRequested => 'Prośba o reset hasła',
            self::PasswordReset => 'Hasło zresetowane',
            self::PasswordChanged => 'Zmiana hasła',
            self::EmailChanged => 'Zmiana adresu e-mail konta',
            self::AccessDenied => 'Odmowa dostępu',
            self::RecordNotFound => 'Próba otwarcia niedostępnego rekordu',
            self::Probe => 'Skanowanie (typowy adres ataku)',
            self::Throttled => 'Przekroczony limit żądań',
            self::CsrfMismatch => 'Nieważny token formularza',
            self::BookingHoneypot => 'Zapisy: bot (ukryte pole)',
            self::BookingRecaptchaFailed => 'Zapisy: odrzucone przez reCAPTCHA',
            self::BookingCodeFailed => 'Zapisy: błędny kod SMS',
            self::SettingsChanged => 'Zmiana ustawień',
            self::BackupDownloaded => 'Pobranie kopii zapasowej',
            self::PatientErased => 'Trwałe usunięcie danych pacjenta',
        };
    }

    /** info — normal activity; warning — worth a look; critical — likely an attack or a takeover. */
    public function severity(): string
    {
        return match ($this) {
            self::Login, self::Logout, self::PasswordResetRequested, self::CsrfMismatch,
            self::BookingCodeFailed, self::RecordNotFound => 'info',
            self::Lockout, self::BookingHoneypot, self::BackupDownloaded, self::PatientErased => 'critical',
            default => 'warning',
        };
    }
}
