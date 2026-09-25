<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMessageMail;
use App\Services\Messaging\AppSettings;
use App\Services\Messaging\MessagingNotConfigured;
use App\Services\Messaging\OutgoingMail;
use App\Services\Messaging\SmsFailed;
use App\Services\Messaging\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Mail server and SMS gateway, set by the administrator. Secrets are write-only:
 * the form shows whether one is stored, never its value, and an empty field
 * keeps the stored one.
 */
class MessagingSettingsController extends Controller
{
    public function __construct(private readonly AppSettings $settings) {}

    public function edit(): View
    {
        return view('admin.messaging-settings', [
            'settings' => $this->settings,
            'hasPassword' => $this->settings->has('mail.password'),
            'hasToken' => $this->settings->has('sms.token'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'mail_encryption' => ['required', 'in:tls,ssl,none'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_password_clear' => ['boolean'],
            'mail_from_address' => ['nullable', 'email', 'max:255', 'required_with:mail_host'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
            'sms_token' => ['nullable', 'string', 'max:255'],
            'sms_token_clear' => ['boolean'],
            'sms_sender' => ['nullable', 'string', 'max:11', 'regex:/^[A-Za-z0-9 .\-]*$/'],
            'reminders_email_enabled' => ['boolean'],
            'reminders_sms_enabled' => ['boolean'],
            'reminders_hours_before' => ['required', 'integer', 'between:3,72'],
        ], [
            'sms_sender.regex' => 'Nazwa nadawcy SMS: tylko litery bez polskich znaków, cyfry, spacja, kropka i myślnik.',
            'mail_from_address.required_with' => 'Podaj adres nadawcy.',
        ]);

        $values = [
            'mail.host' => $data['mail_host'] ?? null,
            'mail.port' => $data['mail_port'] ?? null,
            'mail.encryption' => $data['mail_encryption'],
            'mail.username' => $data['mail_username'] ?? null,
            'mail.from_address' => $data['mail_from_address'] ?? null,
            'mail.from_name' => $data['mail_from_name'] ?? null,
            'sms.sender' => $data['sms_sender'] ?? null,
            'reminders.email_enabled' => $request->boolean('reminders_email_enabled') ? '1' : '0',
            'reminders.sms_enabled' => $request->boolean('reminders_sms_enabled') ? '1' : '0',
            'reminders.hours_before' => $data['reminders_hours_before'],
        ];

        // Secrets change only when something was typed in, or when explicitly cleared.
        if (filled($data['mail_password'] ?? null)) {
            $values['mail.password'] = $data['mail_password'];
        } elseif ($request->boolean('mail_password_clear')) {
            $values['mail.password'] = null;
        }

        if (filled($data['sms_token'] ?? null)) {
            $values['sms.token'] = $data['sms_token'];
        } elseif ($request->boolean('sms_token_clear')) {
            $values['sms.token'] = null;
        }

        $this->settings->put($values);

        return redirect()->route('admin.messaging.edit')->with('status', 'Zapisano ustawienia wysyłki.');
    }

    public function testEmail(Request $request, OutgoingMail $mail): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['required', 'email']]);

        try {
            $mail->send($data['test_email'], new TestMessageMail);
        } catch (MessagingNotConfigured $e) {
            return back()->withErrors(['test_email' => $e->getMessage()]);
        } catch (Throwable $e) {
            // Shown to the administrator only — the SMTP error is what makes it fixable.
            return back()->withErrors(['test_email' => 'Nie udało się wysłać: '.mb_substr($e->getMessage(), 0, 300)]);
        }

        return back()->with('status', 'Wysłano testowy e-mail na '.$data['test_email'].'. Sprawdź skrzynkę (także spam).');
    }

    public function testSms(Request $request, SmsGateway $sms): RedirectResponse
    {
        $data = $request->validate(['test_phone' => ['required', 'string', 'max:32']]);

        try {
            $sms->send($data['test_phone'], 'Test bramki SMS z panelu '.config('app.name').'. Wiadomosc testowa.');
        } catch (MessagingNotConfigured|SmsFailed $e) {
            return back()->withErrors(['test_phone' => $e->getMessage()]);
        }

        return back()->with('status', 'Wysłano testowy SMS na '.$data['test_phone'].'.');
    }
}
