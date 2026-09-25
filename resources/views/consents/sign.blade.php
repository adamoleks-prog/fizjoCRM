<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Podpis zgody</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        #signature-pad { touch-action: none; cursor: crosshair; }
    </style>
</head>
{{-- No navigation on purpose: the tablet is handed to the patient. --}}
<body class="font-sans antialiased bg-gray-100 text-gray-900">
    <div class="max-w-3xl mx-auto px-4 py-6 space-y-5">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-gray-500">Pacjent: <strong class="text-gray-900">{{ $patient->first_name }} {{ $patient->last_name }}</strong></div>
            <a href="{{ route('patients.show', $patient) }}" class="text-sm text-gray-500 underline">Anuluj</a>
        </div>

        @if ($templates->isEmpty())
            <div class="bg-white rounded-lg p-6">Brak wzorów zgód. Dodaj je w menu: Wzory zgód.</div>
        @else
            @if ($templates->count() > 1)
                <form method="GET" class="flex flex-wrap gap-2">
                    @foreach ($templates as $option)
                        <button name="template" value="{{ $option->id }}"
                                @class(['px-3 py-2 rounded-md text-sm border',
                                        'bg-indigo-600 text-white border-indigo-600' => $option->is($template),
                                        'bg-white text-gray-700 border-gray-300' => ! $option->is($template)])>
                            {{ $option->name }}
                        </button>
                    @endforeach
                </form>
            @endif

            <div class="bg-white rounded-lg shadow-sm p-6">
                <h1 class="text-xl font-semibold">{{ $template->name }}</h1>
                <div class="mt-4 text-base leading-relaxed whitespace-pre-line">{{ $text }}</div>
            </div>

            <form method="POST" action="{{ route('consents.store', $patient) }}" id="consent-form"
                  class="bg-white rounded-lg shadow-sm p-6 space-y-4">
                @csrf
                <input type="hidden" name="consent_template_id" value="{{ $template->id }}">
                <input type="hidden" name="signature" id="signature-input">

                @if ($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-800 rounded-md p-3 text-sm">{{ $errors->first() }}</div>
                @endif

                <label class="flex items-start gap-3 text-base">
                    <input type="checkbox" name="read" value="1" class="mt-1 h-5 w-5 rounded border-gray-300 text-indigo-600" @checked(old('read'))>
                    <span>Zapoznałem(-am) się z powyższą treścią i ją akceptuję.</span>
                </label>

                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Podpisz się palcem lub rysikiem w ramce</span>
                        <button type="button" id="signature-clear" class="text-sm text-indigo-600 underline">Wyczyść</button>
                    </div>
                    <canvas id="signature-pad" class="mt-2 w-full h-48 bg-white border-2 border-dashed border-gray-400 rounded-md"></canvas>
                </div>

                <button class="w-full py-3 rounded-md bg-indigo-600 text-white text-lg font-semibold hover:bg-indigo-500">
                    Podpisuję
                </button>
            </form>
        @endif
    </div>

    <script>
        (() => {
            const canvas = document.getElementById('signature-pad');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            let drawing = false, dirty = false, last = null;

            // Sized in device pixels so the line stays sharp on retina tablets.
            const resize = () => {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const rect = canvas.getBoundingClientRect();
                canvas.width = rect.width * ratio;
                canvas.height = rect.height * ratio;
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.strokeStyle = '#111827';
                dirty = false;
            };

            const point = (e) => {
                const rect = canvas.getBoundingClientRect();
                return { x: e.clientX - rect.left, y: e.clientY - rect.top };
            };

            canvas.addEventListener('pointerdown', (e) => {
                drawing = true;
                last = point(e);
                canvas.setPointerCapture(e.pointerId);
            });
            canvas.addEventListener('pointermove', (e) => {
                if (!drawing) return;
                const p = point(e);
                ctx.beginPath();
                ctx.moveTo(last.x, last.y);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                last = p;
                dirty = true;
            });
            ['pointerup', 'pointercancel', 'pointerleave'].forEach((type) =>
                canvas.addEventListener(type, () => { drawing = false; }));

            document.getElementById('signature-clear').addEventListener('click', () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                dirty = false;
            });

            document.getElementById('consent-form').addEventListener('submit', (e) => {
                if (!dirty) {
                    e.preventDefault();
                    alert('Podpisz się w ramce.');
                    return;
                }
                document.getElementById('signature-input').value = canvas.toDataURL('image/png');
            });

            window.addEventListener('resize', resize);
            resize();
        })();
    </script>
</body>
</html>
