import { Controller } from '@hotwired/stimulus';

const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';

export default class extends Controller {
    static targets = ['input', 'picker', 'months', 'days'];
    static values = {
        minSource: String,
    };

    connect() {
        this.today = this.currentJalaliDate();
        this.selectedYear = this.today.year;
        this.selectedMonthIndex = this.today.month - 1;
        this.boundSourceChanged = () => this.sourceChanged();

        if (this.hasMinSourceValue) {
            this.sourceInput = document.querySelector(this.minSourceValue);
            this.sourceInput?.addEventListener('input', this.boundSourceChanged);
            this.sourceInput?.addEventListener('change', this.boundSourceChanged);
        }

        this.renderMonths();
        this.renderDays();
        this.inputTarget.addEventListener('focus', () => this.open());
        document.addEventListener('click', this.closeFromOutside);
        this.sourceChanged();
    }

    disconnect() {
        this.sourceInput?.removeEventListener('input', this.boundSourceChanged);
        this.sourceInput?.removeEventListener('change', this.boundSourceChanged);
        document.removeEventListener('click', this.closeFromOutside);
    }

    closeFromOutside = (event) => {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    };

    open() {
        this.pickerTarget.classList.remove('hidden');
        this.renderMonths();
        this.renderDays();
    }

    close() {
        this.pickerTarget.classList.add('hidden');
    }

    sourceChanged() {
        const selected = this.inputTarget.dataset.jalaliSerial;
        if (selected && Number(selected) < this.minDate().serial) {
            this.clearInput();
        }

        if (this.selectedMonthIndex + 1 < this.minDate().month) {
            this.selectedMonthIndex = this.minDate().month - 1;
        }

        this.renderMonths();
        this.renderDays();
    }

    renderMonths() {
        this.monthsTarget.innerHTML = '';
        MONTHS.forEach((month, index) => {
            const monthNumber = index + 1;
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = month;
            button.disabled = this.isMonthDisabled(monthNumber);
            button.className = this.monthClass(monthNumber);
            button.addEventListener('click', () => {
                if (button.disabled) {
                    return;
                }

                this.selectedMonthIndex = index;
                this.renderMonths();
                this.renderDays();
            });
            this.monthsTarget.appendChild(button);
        });
    }

    renderDays() {
        this.daysTarget.innerHTML = '';
        const dayCount = this.selectedMonthIndex < 6 ? 31 : this.selectedMonthIndex < 11 ? 30 : 29;

        for (let day = 1; day <= dayCount; day += 1) {
            const date = this.makeDate(this.selectedMonthIndex + 1, day);
            const disabled = date.serial < this.minDate().serial;
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = this.toPersianNumber(day);
            button.dataset.jalaliSerial = String(date.serial);
            button.disabled = disabled;
            button.className = this.dayClass(disabled);
            button.addEventListener('click', () => {
                if (button.disabled) {
                    return;
                }

                this.inputTarget.value = `${this.toPersianNumber(day)} ${MONTHS[this.selectedMonthIndex]}`;
                this.inputTarget.dataset.jalaliSerial = String(date.serial);
                this.inputTarget.dataset.jalaliYear = String(date.year);
                this.inputTarget.dataset.jalaliMonth = String(date.month);
                this.inputTarget.dataset.jalaliDay = String(date.day);
                this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
                this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
                this.close();
            });
            this.daysTarget.appendChild(button);
        }
    }

    minDate() {
        const sourceSerial = Number(this.sourceInput?.dataset.jalaliSerial || 0);
        if (!sourceSerial) {
            return this.today;
        }

        return sourceSerial > this.today.serial
            ? this.dateFromSerial(sourceSerial)
            : this.today;
    }

    isMonthDisabled(month) {
        return this.makeDate(month, this.daysInMonth(month)).serial < this.minDate().serial;
    }

    clearInput() {
        this.inputTarget.value = '';
        delete this.inputTarget.dataset.jalaliSerial;
        delete this.inputTarget.dataset.jalaliYear;
        delete this.inputTarget.dataset.jalaliMonth;
        delete this.inputTarget.dataset.jalaliDay;
        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    currentJalaliDate() {
        const parts = new Intl.DateTimeFormat('en-US-u-ca-persian', {
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
        }).formatToParts(new Date());
        const year = Number(parts.find((part) => part.type === 'year')?.value);
        const month = Number(parts.find((part) => part.type === 'month')?.value);
        const day = Number(parts.find((part) => part.type === 'day')?.value);

        return this.makeDate(month, day, year);
    }

    makeDate(month, day, year = this.selectedYear) {
        return {
            year,
            month,
            day,
            serial: (year * 10000) + (month * 100) + day,
        };
    }

    dateFromSerial(serial) {
        const year = Math.floor(serial / 10000);
        const month = Math.floor((serial % 10000) / 100);
        const day = serial % 100;

        return { year, month, day, serial };
    }

    daysInMonth(month) {
        return month <= 6 ? 31 : month <= 11 ? 30 : 29;
    }

    monthClass(month) {
        const active = month === this.selectedMonthIndex + 1;
        const disabled = this.isMonthDisabled(month);

        if (disabled) {
            return 'cursor-not-allowed rounded-md border border-[#e7ddd0] bg-[#f7f3ee] px-2 py-2 text-[#a8b0ad] opacity-60';
        }

        return active
            ? 'rounded-md border border-[#0f766e] bg-[#0f766e] px-2 py-2 text-white'
            : 'rounded-md border border-[#e7ddd0] bg-white px-2 py-2 text-[#596461] hover:border-[#0f766e]';
    }

    dayClass(disabled) {
        return disabled
            ? 'cursor-not-allowed rounded-md border border-[#e7ddd0] bg-[#f7f3ee] py-2 font-bold text-[#a8b0ad] opacity-60'
            : 'rounded-md border border-[#e7ddd0] bg-white py-2 font-bold text-[#18312f] hover:border-[#0f766e] hover:bg-[#e0f2ef]';
    }

    toPersianNumber(value) {
        return String(value).replace(/\d/g, (digit) => PERSIAN_DIGITS[Number(digit)]);
    }
}
