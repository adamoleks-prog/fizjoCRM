<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Dane gabinetu</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Widoczne dla pacjentów: w przypomnieniach o wizytach i na karcie wizyty.</p>

            <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="practice_name" value="Nazwa gabinetu" />
                    <x-text-input id="practice_name" name="practice_name" type="text" class="mt-1 block w-full" :value="old('practice_name', $user->practice_name)" placeholder="np. FizjoRoom" />
                    <x-input-error class="mt-2" :messages="$errors->get('practice_name')" />
                </div>
                <div>
                    <x-input-label for="practice_phone" value="Telefon do odwołania wizyty" />
                    <x-text-input id="practice_phone" name="practice_phone" type="text" class="mt-1 block w-full" :value="old('practice_phone', $user->practice_phone)" />
                    <x-input-error class="mt-2" :messages="$errors->get('practice_phone')" />
                </div>
                <div class="md:col-span-2">
                    <x-input-label for="practice_address" value="Adres gabinetu" />
                    <x-text-input id="practice_address" name="practice_address" type="text" class="mt-1 block w-full" :value="old('practice_address', $user->practice_address)" />
                    <x-input-error class="mt-2" :messages="$errors->get('practice_address')" />
                </div>
            </div>
        </div>

        <x-form-textarea name="competency_profile" label="Profil kompetencji" :value="$user->competency_profile" :rows="4"
                         hint="Staż i ukończone kursy, np. „15 lat doświadczenia. Terapia Cyriax, masaż tkanek głębokich, suche igłowanie.” Asystent terapii proponuje metody specjalistyczne tylko z tej listy." />

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
