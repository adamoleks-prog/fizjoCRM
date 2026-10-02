<x-public-layout title="Potwierdź kod">
    <h1 class="text-2xl font-semibold">Wpisz kod z SMS-a</h1>

    <form method="POST" action="{{ route('booking.confirm') }}" class="bg-white rounded-lg shadow-sm p-5 space-y-4">
        @csrf

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-md p-3 text-sm">{{ $errors->first() }}</div>
        @endif

        <p class="text-sm text-gray-600">Wysłaliśmy 6-cyfrowy kod na podany numer. Kod jest ważny 10 minut.</p>

        <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus
               class="block w-full text-center text-3xl tracking-[0.5em] border-gray-300 rounded-md shadow-sm focus:border-indigo-500">

        <button class="w-full py-3 rounded-md bg-indigo-600 text-white font-semibold hover:bg-indigo-500">Potwierdź wizytę</button>

        <a href="{{ route('booking.index') }}" class="block text-center text-sm text-gray-500 underline">Wybierz inny termin</a>
    </form>
</x-public-layout>
