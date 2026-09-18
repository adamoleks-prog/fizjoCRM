<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Kalendarz</h2>
            <a href="{{ route('appointments.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">
                Umów wizytę
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <div id="calendar" class="text-gray-900 dark:text-gray-100"></div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const calendarEl = document.getElementById('calendar');

                const calendar = new FullCalendar.Calendar(calendarEl, {
                    plugins: FullCalendar.defaultPlugins,
                    initialView: 'timeGridWeek',
                    locale: FullCalendar.plLocale,
                    firstDay: 1,
                    slotMinTime: @json(config('appointments.working_hours.start') . ':00'),
                    slotMaxTime: @json(config('appointments.working_hours.end') . ':00'),
                    slotDuration: @json(sprintf('00:%02d:00', config('appointments.slot_minutes'))),
                    slotLabelInterval: @json(sprintf('00:%02d:00', config('appointments.slot_minutes'))),
                    businessHours: {
                        daysOfWeek: @json(config('appointments.working_days')),
                        startTime: @json(config('appointments.working_hours.start')),
                        endTime: @json(config('appointments.working_hours.end')),
                    },
                    allDaySlot: false,
                    height: 'auto',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'timeGridWeek,dayGridMonth',
                    },
                    buttonText: {
                        today: 'Dziś',
                        week: 'Tydzień',
                        month: 'Miesiąc',
                    },
                    events: @json(route('appointments.calendar-feed')),
                    dateClick: (info) => {
                        const params = new URLSearchParams({ date: info.dateStr.slice(0, 10) });
                        window.location = @json(route('appointments.create')) + '?' + params.toString();
                    },
                });

                calendar.render();
            });
        </script>
    @endpush
</x-app-layout>
