import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['mode', 'flexibleFields', 'exactFields'];

    connect() {
        this.update();
    }

    change() {
        this.update();
    }

    update() {
        const selected = this.modeTargets.find((mode) => mode.checked)?.value || 'flexible';
        const isFlexible = selected === 'flexible';

        this.flexibleFieldsTarget.classList.toggle('hidden', !isFlexible);
        this.flexibleFieldsTarget.classList.toggle('grid', isFlexible);
        this.exactFieldsTarget.classList.toggle('hidden', isFlexible);
        this.exactFieldsTarget.classList.toggle('grid', !isFlexible);

        this.modeTargets.forEach((mode) => {
            const label = mode.closest('label');
            const active = mode.checked;
            label.classList.toggle('border-[#0f766e]', active);
            label.classList.toggle('bg-[#e0f2ef]', active);
            label.classList.toggle('text-[#0f766e]', active);
            label.classList.toggle('border-[#d8cab9]', !active);
            label.classList.toggle('bg-white', !active);
            label.classList.toggle('text-[#596461]', !active);
        });
    }
}
