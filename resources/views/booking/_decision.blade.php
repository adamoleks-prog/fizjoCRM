@php($otherPhone = $appointment->bookedFromOtherPhone())

<div class="flex flex-wrap items-center gap-2">
    <form method="POST" action="{{ route('booking.approve', $appointment) }}" class="flex flex-wrap items-center gap-2">
        @csrf
        <button class="px-3 py-1.5 rounded-md bg-brand-500 text-white text-xs font-semibold hover:bg-brand-600">
            {{ $otherPhone ? 'Potwierdź — to ten pacjent' : 'Potwierdź' }}
        </button>
        @if ($otherPhone)
            <label class="flex items-center gap-1 text-xs text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="update_phone" value="1" class="rounded border-gray-300 text-indigo-600">
                zmień numer w karcie na {{ \App\Services\Messaging\PhoneNumber::format($appointment->booking_phone) }}
            </label>
        @endif
    </form>

    @if ($otherPhone && $appointment->booking_details)
        <form method="POST" action="{{ route('booking.approve-new', $appointment) }}"
              onsubmit="return confirm(@js('Założyć nową kartę dla: '.$appointment->booking_details['first_name'].' '.$appointment->booking_details['last_name'].' i przenieść na nią wizytę?'))">
            @csrf
            <button class="px-3 py-1.5 rounded-md bg-indigo-500 text-white text-xs font-semibold hover:bg-indigo-600">To inna osoba — nowa karta</button>
        </form>
    @endif

    <form method="POST" action="{{ route('booking.reject', $appointment) }}" onsubmit="return confirm('Odrzucić tę wizytę? Pacjent dostanie SMS, a termin się zwolni.')">
        @csrf
        <button class="px-3 py-1.5 rounded-md bg-white border border-red-300 text-red-700 text-xs font-semibold hover:bg-red-50">Odrzuć</button>
    </form>
</div>
