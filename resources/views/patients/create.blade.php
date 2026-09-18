<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Nowy pacjent</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('patients.store') }}">
                    @csrf

                    @include('patients._form', ['operators' => $operators])

                    <div class="flex items-center gap-4 mt-6">
                        <x-primary-button>Zapisz</x-primary-button>
                        <a href="{{ route('patients.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Anuluj</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
