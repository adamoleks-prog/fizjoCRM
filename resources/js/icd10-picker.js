export default function icd10Picker(config) {
    return {
        query: '',
        results: [],
        open: false,
        loading: false,
        code: config.code || '',
        name: config.name || '',
        searchUrl: config.searchUrl,
        timer: null,

        init() {
            this.query = this.code ? `${this.code} — ${this.name}` : '';
        },

        onInput() {
            this.open = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.search(), 200);
        },

        async search() {
            this.loading = true;

            const url = new URL(this.searchUrl, window.location.origin);
            url.searchParams.set('q', this.query);

            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            this.results = await response.json();
            this.loading = false;
        },

        choose(item) {
            this.code = item.code;
            this.name = item.name;
            this.query = `${item.code} — ${item.name}`;
            this.open = false;
        },

        clear() {
            this.code = '';
            this.name = '';
            this.query = '';
            this.results = [];
            this.open = false;
        },
    };
}
