@php
    $views = \App\Enums\BodyView::cases();

    $existing = old('pain_points', $appointment->painPoints->map(fn ($p) => [
        'body_view' => $p->body_view->value,
        'position_x' => (float) $p->position_x,
        'position_y' => (float) $p->position_y,
        'note' => $p->note,
    ])->all());
@endphp

<div class="rounded-md border border-gray-200 dark:border-gray-700 p-4"
     x-data="{
        view: 'front',
        points: @js(array_values($existing)),
        place(event) {
            const box = event.currentTarget.getBoundingClientRect();
            this.points.push({
                body_view: this.view,
                position_x: +(((event.clientX - box.left) / box.width) * 100).toFixed(2),
                position_y: +(((event.clientY - box.top) / box.height) * 100).toFixed(2),
                note: '',
            });
        },
        remove(index) { this.points.splice(index, 1) },
        inView() { return this.points.filter(p => p.body_view === this.view) },
        numberOf(point) { return this.points.indexOf(point) + 1 },
     }">

    <h3 class="font-medium text-gray-900 dark:text-gray-100">Wizualizacja dolegliwości</h3>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        Kliknij na sylwetce, aby oznaczyć miejsce dolegliwości. Do każdego punktu możesz dopisać opis.
    </p>

    <div class="mt-3 flex gap-2">
        @foreach ($views as $bodyView)
            <button type="button" @click="view = @js($bodyView->value)"
                    x-bind:class="view === @js($bodyView->value)
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300'"
                    class="px-3 py-1.5 text-sm border rounded-md">
                {{ $bodyView->label() }}
            </button>
        @endforeach
    </div>

    <div class="mt-3 flex flex-col sm:flex-row gap-4">
        <div class="relative w-40 shrink-0 mx-auto sm:mx-0 cursor-crosshair select-none"
             @click="place($event)">
            <x-body-silhouette class="text-gray-200 dark:text-gray-700" />

            <template x-for="(point, index) in points" :key="index">
                <div x-show="point.body_view === view"
                     class="absolute -translate-x-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-rose-600 text-white text-xs font-semibold flex items-center justify-center ring-2 ring-white dark:ring-gray-800"
                     x-bind:style="`left: ${point.position_x}%; top: ${point.position_y}%`"
                     x-text="index + 1"></div>
            </template>
        </div>

        <div class="flex-1 space-y-2">
            <template x-for="(point, index) in points" :key="index">
                <div class="flex items-center gap-2" x-show="point.body_view === view">
                    <span class="shrink-0 w-6 h-6 rounded-full bg-rose-600 text-white text-xs font-semibold flex items-center justify-center"
                          x-text="index + 1"></span>

                    <input type="hidden" x-bind:name="`pain_points[${index}][body_view]`" x-bind:value="point.body_view">
                    <input type="hidden" x-bind:name="`pain_points[${index}][position_x]`" x-bind:value="point.position_x">
                    <input type="hidden" x-bind:name="`pain_points[${index}][position_y]`" x-bind:value="point.position_y">

                    <input type="text" x-model="point.note" x-bind:name="`pain_points[${index}][note]`"
                           placeholder="np. ból promieniujący do pośladka"
                           class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm text-sm">

                    <button type="button" @click="remove(index)"
                            class="px-2 py-1 text-sm text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400">
                        Usuń
                    </button>
                </div>
            </template>

            {{-- Rows of the other view are only hidden, not removed, so their marks
                 are still submitted. --}}

            <p class="text-sm text-gray-500 dark:text-gray-400" x-show="inView().length === 0">
                Brak oznaczeń na tej stronie sylwetki.
            </p>
        </div>
    </div>

    <x-input-error :messages="$errors->get('pain_points')" class="mt-2" />
</div>
