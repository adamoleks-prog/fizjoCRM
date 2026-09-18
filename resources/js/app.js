

import Alpine from 'alpinejs';
import { Calendar } from '@fullcalendar/core';
import plLocale from '@fullcalendar/core/locales/pl';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { initDocumentScanner } from './document-scanner';
import slotPicker from './slot-picker';
import icd10Picker from './icd10-picker';

window.Alpine = Alpine;

Alpine.data('slotPicker', slotPicker);
Alpine.data('icd10Picker', icd10Picker);

Alpine.start();

window.FullCalendar = {
    Calendar,
    defaultPlugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    plLocale,
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-document-scanner]').forEach(initDocumentScanner);
});
