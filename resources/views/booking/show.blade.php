@php
    $days_pl = ['niedz.', 'pon.', 'wt.', 'śr.', 'czw.', 'pt.', 'sob.'];
    $input = 'mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500';
    $link = fn (array $params) => route('booking.show', [$physiotherapist, ...$params]);
@endphp

<x-public-layout :title="'Zapisy — '.$physiotherapist->name">
    <div>
        <h1 class="text-2xl font-semibold">Zapisz się na wizytę</h1>
        <p class="text-gray-600">
            {{ $physiotherapist->name }}{{ $physiotherapist->practice_name ? ' · '.$physiotherapist->practice_name : '' }}
            @if ($physiotherapist->practice_address)
                <br><span class="text-sm">{{ $physiotherapist->practice_address }}</span>
            @endif
        </p>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-md p-3 text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- 1. Visit type --}}
    <section class="bg-white rounded-lg shadow-sm p-5">
        <h2 class="font-semibold">1. Rodzaj wizyty</h2>
        @if ($types === [])
            <p class="mt-2 text-sm text-gray-600">Zapisy online są chwilowo niedostępne.</p>
        @endif
        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ($types as $value => ['label' => $label, 'hint' => $hint])
                <a href="{{ $link(['typ' => $value]) }}"
                   @class(['rounded-md border p-4', 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600' => $type === $value, 'border-gray-300 hover:border-indigo-400' => $type !== $value])>
                    <div class="font-medium">{{ $label }}</div>
                    <div class="text-sm text-gray-600">{{ $hint }}</div>
                </a>
            @endforeach
        </div>
        @if ($minutes)
            <p class="mt-2 text-sm text-gray-500">Czas wizyty: {{ $minutes }} min.</p>
        @endif
    </section>

    {{-- 2. Day and time --}}
    @if ($type)
        <section class="bg-white rounded-lg shadow-sm p-5">
            <h2 class="font-semibold">2. Termin</h2>

            @if ($days->isEmpty())
                <p class="mt-2 text-sm text-gray-600">Brak wolnych terminów w najbliższym czasie. Prosimy o kontakt telefoniczny{{ $physiotherapist->practice_phone ? ': '.$physiotherapist->practice_phone : '' }}.</p>
            @else
                <div class="mt-3 flex gap-2 overflow-x-auto pb-2">
                    @foreach ($days as $day)
                        <a href="{{ $link(['typ' => $type, 'data' => $day->toDateString()]) }}"
                           @class(['shrink-0 w-16 rounded-md border py-2 text-center text-sm', 'border-indigo-600 bg-indigo-600 text-white' => $date && $day->isSameDay($date), 'border-gray-300 hover:border-indigo-400' => ! ($date && $day->isSameDay($date))])>
                            <div>{{ $days_pl[$day->dayOfWeek] }}</div>
                            <div class="font-semibold">{{ $day->format('d.m') }}</div>
                        </a>
                    @endforeach
                </div>

                @if ($date)
                    <div class="mt-3 grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-2">
                        @forelse ($starts as $start)
                            <a href="{{ $link(['typ' => $type, 'data' => $date->toDateString(), 'godzina' => $start->format('H:i')]) }}#dane"
                               @class(['rounded-md border py-2 text-center text-sm', 'border-indigo-600 bg-indigo-600 text-white' => $chosen && $start->equalTo($chosen), 'border-gray-300 hover:border-indigo-400' => ! ($chosen && $start->equalTo($chosen))])>
                                {{ $start->format('H:i') }}
                            </a>
                        @empty
                            <p class="col-span-full text-sm text-gray-600">Ten dzień jest już zajęty — wybierz inny.</p>
                        @endforelse
                    </div>
                @endif
            @endif
        </section>
    @endif

    {{-- 3. Details --}}
    @if ($chosen)
        <form id="dane" method="POST" action="{{ route('booking.request', $physiotherapist) }}" class="bg-white rounded-lg shadow-sm p-5 space-y-4">
            @csrf
            <input type="hidden" name="typ" value="{{ $type }}">
            <input type="hidden" name="starts_at" value="{{ $chosen->format('Y-m-d H:i') }}">
            <div class="hidden" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>

            <h2 class="font-semibold">3. Twoje dane</h2>
            <p class="text-sm text-gray-600">
                Wybrany termin: <strong>{{ $days_pl[$chosen->dayOfWeek] }} {{ $chosen->format('d.m.Y, H:i') }}</strong>.
                @if ($requiresSmsCode)
                    Na podany numer wyślemy SMS z kodem potwierdzającym.
                @endif
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="first_name" class="text-sm font-medium">Imię</label>
                    <input id="first_name" name="first_name" value="{{ old('first_name') }}" required maxlength="40" autocomplete="given-name"
                           pattern="[A-Za-zĄąĆćĘęŁłŃńÓóŚśŹźŻż]+" title="Tylko litery"
                           oninput="this.value = this.value.replace(/[^\p{L}]/gu, '')" class="{{ $input }}">
                </div>
                <div>
                    <label for="last_name" class="text-sm font-medium">Nazwisko</label>
                    <input id="last_name" name="last_name" value="{{ old('last_name') }}" required maxlength="60" autocomplete="family-name"
                           pattern="[A-Za-zĄąĆćĘęŁłŃńÓóŚśŹźŻż]+(-[A-Za-zĄąĆćĘęŁłŃńÓóŚśŹźŻż]+)?" title="Tylko litery; nazwisko dwuczłonowe połącz myślnikiem"
                           oninput="this.value = this.value.replace(/[^\p{L}-]/gu, '').replace(/-{2,}/g, '-')" class="{{ $input }}">
                </div>
                <div>
                    <label for="phone" class="text-sm font-medium">Telefon komórkowy</label>
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-100 text-gray-600 select-none">+48</span>
                        <input id="phone" name="phone" type="tel" inputmode="numeric" value="{{ old('phone') }}" required
                               maxlength="9" pattern="[4-8][0-9]{8}" title="9 cyfr numeru komórkowego" autocomplete="tel-national" placeholder="600123456"
                               oninput="this.value = this.value.replace(/\D/g, '').slice(0, 9)"
                               class="block w-full rounded-none rounded-r-md border-gray-300 focus:border-indigo-500">
                    </div>
                </div>
                <div>
                    <label for="email" class="text-sm font-medium">E-mail <span class="text-gray-400">(opcjonalnie)</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255"
                           pattern="[^@\s]+@[^@\s]+\.[A-Za-z]{2,}" title="Adres e-mail, np. jan.kowalski@gmail.com" class="{{ $input }}">
                </div>
            </div>

            @if ($firstVisit)
                <div>
                    <label for="reason" class="text-sm font-medium">Z jakim problemem się zgłaszasz? <span class="text-gray-400">(opcjonalnie)</span></label>
                    <textarea id="reason" name="reason" rows="4" maxlength="1000"
                              placeholder="Np. ból kolana od miesiąca, nasila się przy schodzeniu ze schodów; skierowanie od ortopedy."
                              class="{{ $input }}">{{ old('reason') }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">Kilka zdań wystarczy — pomoże fizjoterapeucie przygotować się do wizyty. Szczegóły omówicie na miejscu.</p>
                </div>
                <p class="text-xs text-gray-500">Pierwszą wizytę potwierdzi fizjoterapeuta — dostaniesz SMS.</p>
            @else
                <p class="text-xs text-gray-500">Jeśli byłeś(-aś) już u nas, podaj numer telefonu i nazwisko takie, jak przy wcześniejszych wizytach — wtedy wizyta zostanie potwierdzona od razu. Nowe osoby potwierdzamy SMS-em.</p>
            @endif

            <details class="text-sm text-gray-600">
                <summary class="cursor-pointer underline">Informacja o przetwarzaniu danych osobowych</summary>
                <div class="mt-2 whitespace-pre-line">{{ $privacyText ?? 'Administratorem danych jest '.($physiotherapist->practice_name ?: $physiotherapist->name).'. Dane podane w formularzu są przetwarzane w celu umówienia wizyty i kontaktu w jej sprawie (SMS, e-mail). Szczegółowe informacje otrzymasz w gabinecie.' }}</div>
            </details>

            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-gray-300 text-indigo-600" @checked(old('consent'))>
                <span>Zapoznałem(-am) się z informacją o przetwarzaniu danych osobowych.</span>
            </label>

            <input type="hidden" name="recaptcha_token" id="recaptcha_token">

            <button class="w-full py-3 rounded-md bg-brand-500 text-white font-semibold hover:bg-brand-600">{{ $requiresSmsCode ? 'Wyślij kod SMS' : 'Zapisz się' }}</button>

            @if ($recaptchaSiteKey)
                <p class="text-xs text-gray-400">Formularz chroni reCAPTCHA Google — obowiązują <a href="https://policies.google.com/privacy" class="underline" target="_blank" rel="noopener">Polityka prywatności</a> i <a href="https://policies.google.com/terms" class="underline" target="_blank" rel="noopener">Warunki</a> Google.</p>
            @endif
        </form>

        @if ($recaptchaSiteKey)
            <script src="https://www.google.com/recaptcha/api.js?render={{ urlencode($recaptchaSiteKey) }}"></script>
            <script>
                document.getElementById('dane').addEventListener('submit', function (event) {
                    const form = this;
                    if (form.dataset.ready) return;
                    event.preventDefault();
                    grecaptcha.ready(() => {
                        grecaptcha.execute(@js($recaptchaSiteKey), { action: @js(\App\Services\Booking\Recaptcha::ACTION) }).then((token) => {
                            document.getElementById('recaptcha_token').value = token;
                            form.dataset.ready = '1';
                            form.submit();
                        });
                    });
                });
            </script>
        @endif
    @endif
</x-public-layout>
