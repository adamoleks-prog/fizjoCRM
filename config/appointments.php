<?php

return [
    // Wizyty są umawiane wyłącznie w równych slotach o tej długości.
    'slot_minutes' => env('APPOINTMENT_SLOT_MINUTES', 30),

    'default_duration_minutes' => env('APPOINTMENT_DEFAULT_DURATION', 30),

    'max_duration_minutes' => env('APPOINTMENT_MAX_DURATION', 240),

    // Godziny pracy gabinetu (format H:i), używane do generowania wolnych slotów.
    'working_hours' => [
        'start' => env('APPOINTMENT_DAY_START', '08:00'),
        'end' => env('APPOINTMENT_DAY_END', '18:00'),
    ],

    // Dni robocze wg ISO-8601 (1 = poniedziałek, 7 = niedziela).
    'working_days' => [1, 2, 3, 4, 5],
];
