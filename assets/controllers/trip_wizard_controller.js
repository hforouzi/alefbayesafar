import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'indicator', 'previousButton', 'nextButton', 'field', 'summary', 'sideSummary', 'stepLabel', 'progress', 'validationMessage'];
    static values = { current: Number };

    connect() {
        this.currentValue = this.currentValue || 1;
        this.showCurrentStep();
        this.refreshSummary();
        this.fieldTargets.forEach((field) => {
            field.addEventListener('input', () => this.refreshSummary());
            field.addEventListener('change', () => this.refreshSummary());
        });
    }

    next() {
        if (!this.canLeaveCurrentStep()) {
            return;
        }

        if (this.currentValue < 4) {
            this.currentValue += 1;
            this.showCurrentStep();
        }
    }

    previous() {
        if (this.currentValue > 1) {
            this.currentValue -= 1;
            this.showCurrentStep();
        }
    }

    goTo(event) {
        const step = Number(event.currentTarget.dataset.step);
        if (step >= 1 && step <= 4) {
            if (step > this.currentValue && !this.canLeaveCurrentStep()) {
                return;
            }

            this.currentValue = step;
            this.showCurrentStep();
        }
    }

    showCurrentStep() {
        this.panelTargets.forEach((panel) => {
            panel.classList.toggle('hidden', Number(panel.dataset.step) !== this.currentValue);
        });

        this.indicatorTargets.forEach((indicator) => {
            const isActive = Number(indicator.dataset.step) === this.currentValue;
            indicator.classList.toggle('border-[#0f766e]', isActive);
            indicator.classList.toggle('bg-[#e0f2ef]', isActive);
            indicator.classList.toggle('text-[#0f766e]', isActive);
            indicator.classList.toggle('border-[#e7ddd0]', !isActive);
            indicator.classList.toggle('text-[#65716e]', !isActive);
        });

        this.previousButtonTarget.disabled = this.currentValue === 1;
        this.previousButtonTarget.classList.toggle('opacity-50', this.currentValue === 1);
        this.nextButtonTarget.textContent = this.currentValue === 4 ? 'پایان' : 'مرحله بعد';

        if (this.hasStepLabelTarget) {
            const labels = {
                1: 'مرحله 1 از 4 — سفر',
                2: 'مرحله 2 از 4 — پرواز / هتل',
                3: 'مرحله 3 از 4 — فعالیت‌ها',
                4: 'مرحله 4 از 4 — تأیید / نتایج',
            };
            this.stepLabelTarget.textContent = labels[this.currentValue];
        }

        if (this.hasProgressTarget) {
            this.progressTarget.style.width = `${(this.currentValue / 4) * 100}%`;
        }

        if (this.currentValue === 4) {
            this.refreshSummary();
        }

        this.clearValidationMessage();
    }

    canLeaveCurrentStep() {
        if (this.currentValue !== 1) {
            this.clearValidationMessage();
            return true;
        }

        const validation = this.validateTripDates();
        if (!validation.valid) {
            this.showValidationMessage(validation.message);
            return false;
        }

        this.clearValidationMessage();
        return true;
    }

    validateTripDates() {
        const dateMode = this.element.querySelector('input[name="dateMode"]:checked')?.value || 'flexible';
        const tripDays = Number(this.element.querySelector('input[name="tripDays"]')?.value || 0);

        if (dateMode === 'exact') {
            const departure = this.dateSerial('departureDate');
            const returnDate = this.dateSerial('returnDate');

            if (!departure) {
                return { valid: false, message: 'تاریخ رفت را از تقویم انتخاب کنید.' };
            }

            if (departure < this.todaySerial()) {
                return { valid: false, message: 'تاریخ رفت نمی‌تواند قبل از امروز باشد.' };
            }

            if (!returnDate) {
                return { valid: false, message: 'تاریخ برگشت را از تقویم انتخاب کنید.' };
            }

            if (returnDate < departure) {
                return { valid: false, message: 'تاریخ برگشت نمی‌تواند قبل از تاریخ رفت باشد.' };
            }
        } else {
            const windowStart = this.dateSerial('windowStart');
            const windowEnd = this.dateSerial('windowEnd');

            if (!windowStart) {
                return { valid: false, message: 'شروع بازه سفر را از تقویم انتخاب کنید.' };
            }

            if (windowStart < this.todaySerial()) {
                return { valid: false, message: 'شروع بازه سفر نمی‌تواند قبل از امروز باشد.' };
            }

            if (!windowEnd) {
                return { valid: false, message: 'پایان بازه سفر را از تقویم انتخاب کنید.' };
            }

            if (windowEnd < windowStart) {
                return { valid: false, message: 'پایان بازه نمی‌تواند قبل از شروع بازه باشد.' };
            }

            if (tripDays <= 0) {
                return { valid: false, message: 'مدت سفر باید بیشتر از صفر باشد.' };
            }
        }

        return { valid: true, message: '' };
    }

    dateSerial(name) {
        const value = this.element.querySelector(`[name="${name}"]`)?.value || '';
        const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);

        return match ? Number(`${match[1]}${match[2]}${match[3]}`) : 0;
    }

    todaySerial() {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');

        return Number(`${year}${month}${day}`);
    }

    showValidationMessage(message) {
        if (!this.hasValidationMessageTarget) {
            return;
        }

        this.validationMessageTarget.textContent = message;
        this.validationMessageTarget.classList.remove('hidden');
    }

    clearValidationMessage() {
        if (!this.hasValidationMessageTarget) {
            return;
        }

        this.validationMessageTarget.textContent = '';
        this.validationMessageTarget.classList.add('hidden');
    }

    refreshSummary() {
        const summary = this.collectSummary();
        const emptyText = '<p class="text-sm text-[#65716e]">هنوز اطلاعاتی وارد نشده است.</p>';

        const rows = summary.map((item) => `
            <div>
                <dt class="font-extrabold text-[#65716e]">${item.label}</dt>
                <dd class="mt-1 font-bold text-[#18312f]">${item.value}</dd>
            </div>
        `).join('');

        if (this.hasSummaryTarget) {
            this.summaryTarget.innerHTML = rows || emptyText;
        }

        if (this.hasSideSummaryTarget) {
            this.sideSummaryTarget.innerHTML = summary.slice(0, 6).map((item) => `
                <div class="rounded-md bg-[#f7f3ee] p-3">
                    <p class="font-extrabold text-[#65716e]">${item.label}</p>
                    <p class="mt-1 font-bold text-[#18312f]">${item.value}</p>
                </div>
            `).join('') || emptyText;
        }
    }

    collectSummary() {
        const grouped = new Map();

        this.fieldTargets.forEach((field) => {
            const label = field.dataset.summaryLabel;
            const value = this.fieldValue(field);
            if (!label || !value) {
                return;
            }

            if (grouped.has(label)) {
                grouped.set(label, `${grouped.get(label)}، ${value}`);
                return;
            }

            grouped.set(label, value);
        });

        return Array.from(grouped, ([label, value]) => ({ label, value }));
    }

    fieldValue(field) {
        if (field.type === 'checkbox') {
            return field.checked ? field.value : '';
        }

        if (field.type === 'radio') {
            if (!field.checked) {
                return '';
            }

            return field.dataset.summaryValue || field.value;
        }

        return field.value.trim();
    }
}
