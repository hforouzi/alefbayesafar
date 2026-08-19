import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'ages'];
    static values = { min: Number };

    connect() {
        this.inputTarget.addEventListener('input', () => this.renderAges());
        this.renderAges();
    }

    increment() {
        this.inputTarget.value = Number(this.inputTarget.value || 0) + 1;
        this.changed();
    }

    decrement() {
        const next = Number(this.inputTarget.value || 0) - 1;
        this.inputTarget.value = Math.max(this.minValue, next);
        this.changed();
    }

    changed() {
        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
        this.renderAges();
    }

    renderAges() {
        if (!this.hasAgesTarget) {
            return;
        }

        const count = Math.max(0, Number(this.inputTarget.value || 0));
        this.agesTarget.innerHTML = '';

        for (let index = 1; index <= count; index += 1) {
            const label = document.createElement('label');
            label.className = 'block text-xs font-extrabold text-[#65716e]';
            label.textContent = `سن کودک ${index}`;

            const select = document.createElement('select');
            select.name = `childAge${index}`;
            select.className = 'form-select mt-1';

            for (let age = 0; age <= 17; age += 1) {
                const option = document.createElement('option');
                option.value = String(age);
                option.textContent = `${age} سال`;
                select.appendChild(option);
            }

            label.appendChild(select);
            this.agesTarget.appendChild(label);
        }
    }
}
