export default function slotPicker(config) {
    return {
        date: config.date,
        slots: config.slots,
        workingDay: config.workingDay,
        slotMinutes: config.slotMinutes,
        maxDuration: config.maxDuration,
        selected: config.selected,
        duration: config.duration,
        serviceDuration: config.serviceDuration ?? null,

        async loadSlots() {
            const url = new URL(config.slotsUrl, window.location.origin);
            url.searchParams.set('date', this.date);

            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();

            this.workingDay = data.working_day;
            this.slotMinutes = data.slot_minutes ?? this.slotMinutes;
            this.slots = data.slots;
            this.selected = null;
        },

        select(slot) {
            this.selected = slot.value;
            this.duration = this.preferredDuration();
        },

        /** The chosen kind of visit's length, or the longest that still fits. */
        preferredDuration() {
            const options = this.durationOptions();

            if (!this.serviceDuration) {
                return options[0];
            }

            const fitting = options.filter((minutes) => minutes <= this.serviceDuration);

            return fitting.length ? fitting[fitting.length - 1] : options[0];
        },

        /**
         * Only offer durations that fit into the consecutive free slots following
         * the chosen one, so the form cannot propose a visit over a booked slot.
         */
        durationOptions() {
            const index = this.slots.findIndex((slot) => slot.value === this.selected);

            if (index === -1) {
                return [this.slotMinutes];
            }

            const options = [];

            const at = (slot) => new Date(slot.value.replace(' ', 'T')).getTime();

            for (let i = index; i < this.slots.length; i++) {
                if (!this.slots[i].available) {
                    break;
                }

                // A break in the working hours ends the run just like a booked slot.
                if (i > index && at(this.slots[i]) - at(this.slots[i - 1]) !== this.slotMinutes * 60000) {
                    break;
                }

                const minutes = (i - index + 1) * this.slotMinutes;

                if (minutes > this.maxDuration) {
                    break;
                }

                options.push(minutes);
            }

            return options.length ? options : [this.slotMinutes];
        },

        formatDuration(minutes) {
            if (minutes < 60) {
                return `${minutes} min`;
            }

            const hours = Math.floor(minutes / 60);
            const rest = minutes % 60;

            return rest ? `${hours} h ${rest} min` : `${hours} h`;
        },
    };
}
