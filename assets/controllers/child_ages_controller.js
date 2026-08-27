import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['count', 'field', 'row'];

    connect() {
        this.sync();
    }

    sync() {
        const children = Number(this.countTarget.value || 0);
        const enabled = children > 0;

        if (this.hasRowTarget) {
            this.rowTarget.classList.toggle('hidden', !enabled);
        }

        this.fieldTarget.disabled = !enabled;
        if (!enabled) {
            this.fieldTarget.value = '';
        }
    }
}
