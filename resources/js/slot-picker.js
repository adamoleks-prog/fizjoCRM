export default function slotPicker(config) {
    return {
        date: config.date,
        slots: config.slots,
        workingDay: config.workingDay,
        slotMinutes: config.slotMinutes,
        maxDuration: config.maxDuration,
        selected: config.selected,
        duration: config.duration,

        async loadSlots() {
            const url = new URL(config.slotsUrl, window.location.origin);
            url.searchParams.set('date', this.date);

            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();

            this.workingDay = data.working_day;
            this.slots = data.slots;
            this.selected = null;
        },

        select(slot) {
            this.selected = slot.value;
            this.duration = this.slotMinutes;
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

            for (let i = index; i < this.slots.length; i++) {
                if (!this.slots[i].available) {
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
