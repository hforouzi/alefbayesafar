import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'hidden', 'results', 'status'];
    static values = {
        url: String,
        dependsCountry: String,
        dependsState: String,
        dependsCity: String,
        minLength: { type: Number, default: 2 },
    };

    connect() {
        this.abortController = null;
        this.timeout = null;
        this.activeIndex = -1;
        this.items = [];
        this.dependencyListeners = [];
        this.watchDependency(this.dependsCountryValue);
        this.watchDependency(this.dependsStateValue);
        this.watchDependency(this.dependsCityValue);
    }

    disconnect() {
        if (this.abortController) {
            this.abortController.abort();
        }
        for (const [field, listener] of this.dependencyListeners) {
            field.removeEventListener('change', listener);
        }
    }

    search() {
        window.clearTimeout(this.timeout);
        const query = this.inputTarget.value.trim();
        this.hiddenTarget.value = '';
        this.dispatchChange();

        if (query.length < this.minLengthValue) {
            this.items = [];
            this.render([]);
            this.setStatus('');
            return;
        }

        this.setStatus(this.element.dataset.loadingText || 'Loading...');
        this.timeout = window.setTimeout(() => this.fetch(query), 250);
    }

    clear() {
        this.inputTarget.value = '';
        this.hiddenTarget.value = '';
        this.items = [];
        this.render([]);
        this.setStatus('');
        this.dispatchChange();
    }

    keydown(event) {
        if (this.items.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            this.activeIndex = Math.min(this.activeIndex + 1, this.items.length - 1);
            this.paintActive();
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            this.activeIndex = Math.max(this.activeIndex - 1, 0);
            this.paintActive();
        }

        if (event.key === 'Enter' && this.activeIndex >= 0) {
            event.preventDefault();
            this.choose(this.items[this.activeIndex]);
        }

        if (event.key === 'Escape') {
            this.render([]);
        }
    }

    async fetch(query) {
        if (this.abortController) {
            this.abortController.abort();
        }

        const url = new URL(this.urlValue, window.location.origin);
        url.searchParams.set('q', query);
        this.addDependency(url, 'country', this.dependsCountryValue);
        this.addDependency(url, 'state', this.dependsStateValue);
        this.addDependency(url, 'city', this.dependsCityValue);

        this.abortController = new AbortController();
        try {
            const response = await window.fetch(url, {
                signal: this.abortController.signal,
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            this.items = Array.isArray(payload.results) ? payload.results : [];
            this.render(this.items);
            this.setStatus(this.items.length === 0 ? (this.element.dataset.noResultsText || 'No results') : '');
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            this.items = [];
            this.render([]);
            this.setStatus(this.element.dataset.errorText || 'Search failed');
        }
    }

    addDependency(url, key, selector) {
        if (!selector) {
            return;
        }

        const field = document.querySelector(selector);
        if (field && field.value) {
            url.searchParams.set(key, field.value);
        }
    }

    watchDependency(selector) {
        if (!selector) {
            return;
        }

        const field = document.querySelector(selector);
        if (!field) {
            return;
        }

        const listener = () => this.clear();
        field.addEventListener('change', listener);
        this.dependencyListeners.push([field, listener]);
    }

    render(items) {
        this.resultsTarget.innerHTML = '';
        this.activeIndex = -1;
        if (items.length === 0) {
            this.resultsTarget.classList.add('hidden');
            return;
        }

        for (const item of items) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'block w-full px-3 py-2 text-start text-sm hover:bg-primary/10 focus:bg-primary/10 focus:outline-none';
            button.textContent = item.text;
            button.addEventListener('click', () => this.choose(item));
            this.resultsTarget.appendChild(button);
        }
        this.resultsTarget.classList.remove('hidden');
    }

    choose(item) {
        this.inputTarget.value = item.text;
        this.hiddenTarget.value = item.id;
        this.render([]);
        this.setStatus('');
        this.dispatchChange();
    }

    paintActive() {
        Array.from(this.resultsTarget.children).forEach((child, index) => {
            child.classList.toggle('bg-primary/10', index === this.activeIndex);
        });
    }

    setStatus(message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message;
        }
    }

    dispatchChange() {
        this.hiddenTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
