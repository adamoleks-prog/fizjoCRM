@php
    $input = 'mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Ustawienia wysyłki
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-md p-3 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.messaging.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                {{-- Reminders --}}
                <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="font-semibold">Przypomnienia o wizytach</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Wysyłane automatycznie między 8:00 a 20:00 do pacjentów, którzy mają zaznaczoną zgodę na przypomnienia. Treść: nazwa gabinetu, termin, adres i telefon do odwołania — bez powodu wizyty.
                    </p>

                    <div class="mt-4 space-y-2">
                        @foreach (['reminders_sms_enabled' => ['reminders.sms_enabled', 'Wysyłaj SMS'], 'reminders_email_enabled' => ['reminders.email_enabled', 'Wysyłaj e-mail']] as $name => [$key, $label])
                            <input type="hidden" name="{{ $name }}" value="0">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="{{ $name }}" value="1" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600"
                                       @checked(old($name, $settings->bool($key)))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-4 max-w-xs">
                        <x-input-label for="reminders_hours_before" value="Ile godzin przed wizytą" />
                        <input id="reminders_hours_before" name="reminders_hours_before" type="number" min="3" max="72"
                               value="{{ old('reminders_hours_before', $settings->get('reminders.hours_before', 24)) }}" class="{{ $input }}">
                        <x-input-error :messages="$errors->get('reminders_hours_before')" class="mt-2" />
                    </div>
                </section>

                {{-- Mail --}}
                <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="font-semibold">Poczta (SMTP)</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Dane serwera poczty znajdziesz w panelu hostingu albo u dostawcy skrzynki. Używane do przypomnień i wysyłania karty wizyty.
                    </p>

                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <x-input-label for="mail_host" value="Serwer SMTP" />
                            <input id="mail_host" name="mail_host" value="{{ old('mail_host', $settings->get('mail.host')) }}" placeholder="np. mail.fizjoroom.pl" class="{{ $input }}">
                            <x-input-error :messages="$errors->get('mail_host')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="mail_port" value="Port" />
                            <input id="mail_port" name="mail_port" type="number" value="{{ old('mail_port', $settings->get('mail.port')) }}" placeholder="587" class="{{ $input }}">
                            <x-input-error :messages="$errors->get('mail_port')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="mail_encryption" value="Szyfrowanie" />
                            <select id="mail_encryption" name="mail_encryption" class="{{ $input }}">
                                @foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'Brak'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('mail_encryption', $settings->get('mail.encryption', 'tls')) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="mail_username" value="Login" />
                            <input id="mail_username" name="mail_username" value="{{ old('mail_username', $settings->get('mail.username')) }}" autocomplete="off" class="{{ $input }}">
                        </div>
                        <div>
                            <x-input-label for="mail_password" value="Hasło" />
                            <input id="mail_password" name="mail_password" type="password" autocomplete="new-password"
                                   placeholder="{{ $hasPassword ? '•••••• zapisane — zostaw puste' : '' }}" class="{{ $input }}">
                            @if ($hasPassword)
                                <input type="hidden" name="mail_password_clear" value="0">
                                <label class="mt-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                    <input type="checkbox" name="mail_password_clear" value="1" class="rounded border-gray-300 dark:border-gray-700"> usuń zapisane hasło
                                </label>
                            @endif
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="mail_from_address" value="Adres nadawcy" />
                            <input id="mail_from_address" name="mail_from_address" type="email" value="{{ old('mail_from_address', $settings->get('mail.from_address')) }}" placeholder="gabinet@fizjoroom.pl" class="{{ $input }}">
                            <x-input-error :messages="$errors->get('mail_from_address')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="mail_from_name" value="Nazwa nadawcy" />
                            <input id="mail_from_name" name="mail_from_name" value="{{ old('mail_from_name', $settings->get('mail.from_name')) }}" placeholder="FizjoRoom" class="{{ $input }}">
                        </div>
                    </div>
                </section>

                {{-- SMS --}}
                <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="font-semibold">SMS (SMSAPI.pl)</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Token OAuth wygenerujesz w panelu SMSAPI: Ustawienia API → Tokeny API (uprawnienie „SMS”). Nazwę nadawcy trzeba najpierw zarejestrować w SMSAPI; bez niej SMS przyjdzie z domyślnego numeru.
                    </p>

                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="sms_token" value="Token API" />
                            <input id="sms_token" name="sms_token" type="password" autocomplete="new-password"
                                   placeholder="{{ $hasToken ? '•••••• zapisany — zostaw puste' : '' }}" class="{{ $input }}">
                            @if ($hasToken)
                                <input type="hidden" name="sms_token_clear" value="0">
                                <label class="mt-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                    <input type="checkbox" name="sms_token_clear" value="1" class="rounded border-gray-300 dark:border-gray-700"> usuń zapisany token
                                </label>
                            @endif
                        </div>
                        <div>
                            <x-input-label for="sms_sender" value="Nazwa nadawcy (maks. 11 znaków)" />
                            <input id="sms_sender" name="sms_sender" maxlength="11" value="{{ old('sms_sender', $settings->get('sms.sender')) }}" placeholder="FizjoRoom" class="{{ $input }}">
                            <x-input-error :messages="$errors->get('sms_sender')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <x-primary-button>Zapisz ustawienia</x-primary-button>
            </form>

            {{-- Tests --}}
            <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <h3 class="font-semibold">Sprawdź, czy działa</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Najpierw zapisz ustawienia, potem wyślij wiadomość testową.</p>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <form method="POST" action="{{ route('admin.messaging.test-email') }}">
                        @csrf
                        <x-input-label for="test_email" value="Testowy e-mail na adres" />
                        <div class="flex gap-2">
                            <input id="test_email" name="test_email" type="email" value="{{ old('test_email', auth()->user()->email) }}" class="{{ $input }}">
                            <x-secondary-button type="submit" class="mt-1">Wyślij</x-secondary-button>
                        </div>
                        <x-input-error :messages="$errors->get('test_email')" class="mt-2" />
                    </form>

                    <form method="POST" action="{{ route('admin.messaging.test-sms') }}">
                        @csrf
                        <x-input-label for="test_phone" value="Testowy SMS na numer" />
                        <div class="flex gap-2">
                            <input id="test_phone" name="test_phone" value="{{ old('test_phone') }}" placeholder="600 000 000" class="{{ $input }}">
                            <x-secondary-button type="submit" class="mt-1">Wyślij</x-secondary-button>
                        </div>
                        <x-input-error :messages="$errors->get('test_phone')" class="mt-2" />
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
