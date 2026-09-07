import { Controller } from '@hotwired/stimulus';

const CAPTIONS = [
    'دارم گزینه‌های مناسب رو پیدا می‌کنم...',
    'تورهای موجود بررسی شد...',
    'قیمت‌های زنده بررسی شد...',
    'هتل‌ها مقایسه شدند...',
    'اطلاعات هتل‌ها بررسی شد...',
];

export default class extends Controller {
    static targets = ['input', 'form', 'submitButton', 'panel', 'caption', 'log'];

    connect() {
        if (this.hasLogTarget) {
            this.logTarget.scrollTop = this.logTarget.scrollHeight;
        }
    }

    fill(event) {
        const text = event.params.text;
        if (this.hasInputTarget && text) {
            this.inputTarget.value = text;
            this.inputTarget.focus();
        }
    }

    quickReply(event) {
        const text = event.params.message;
        if (!text) {
            return;
        }
        if (this.hasInputTarget) {
            this.inputTarget.value = text;
        }
        this.start();
        if (this.hasFormTarget) {
            this.formTarget.requestSubmit();
        }
    }

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
            }, 1500);
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
