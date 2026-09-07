import { Controller } from '@hotwired/stimulus';

const CAPTIONS = [
    'در حال جست‌وجوی تورهای موجود...',
    'بررسی پیشنهادهای لحظه‌ای...',
    'مقایسه قیمت‌ها...',
    'بررسی اطلاعات هتل...',
    'آماده‌سازی پیشنهادها...',
];

export default class extends Controller {
    static targets = ['submitButton', 'panel', 'caption'];

    start() {
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = true;
            this.submitButtonTarget.classList.add('opacity-60');
        }
        if (this.hasPanelTarget) {
            this.panelTarget.classList.remove('hidden');
        }
        if (this.hasCaptionTarget) {
            let index = 0;
            this.captionTarget.textContent = CAPTIONS[0];
            this.interval = window.setInterval(() => {
                index = (index + 1) % CAPTIONS.length;
                this.captionTarget.textContent = CAPTIONS[index];
            }, 1800);
        }
    }

    stop() {
        window.clearInterval(this.interval);
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = false;
            this.submitButtonTarget.classList.remove('opacity-60');
        }
        if (this.hasPanelTarget) {
            this.panelTarget.classList.add('hidden');
        }
    }

    disconnect() {
        window.clearInterval(this.interval);
    }
}
