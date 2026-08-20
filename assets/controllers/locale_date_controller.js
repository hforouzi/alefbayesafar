import { Controller } from '@hotwired/stimulus';

const FA_MONTHS = [
    'فروردین',
    'اردیبهشت',
    'خرداد',
    'تیر',
    'مرداد',
    'شهریور',
    'مهر',
    'آبان',
    'آذر',
    'دی',
    'بهمن',
    'اسفند',
];
const EN_MONTHS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];
const FA_WEEKDAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
const EN_WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
const GREGORIAN_DAYS = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
const JALALI_DAYS = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

export default class extends Controller {
    static targets = ['display', 'value', 'status'];
    static values = {
        locale: String,
        todayText: String,
        clearText: String,
        selectDateText: String,
        previousMonthText: String,
        nextMonthText: String,
        invalidDateText: String,
    };

    connect() {
        this.locale = this.localeValue || document.documentElement.lang || 'fa';
        this.isFa = this.locale === 'fa';
        this.selected = this.parseCanonical(this.valueTarget.value);
        this.viewDate = this.selected || new Date();
        this.boundOutsideClick = (event) => this.closeFromOutside(event);

        this.displayTarget.setAttribute('dir', this.isFa ? 'rtl' : 'ltr');
        this.displayTarget.setAttribute('aria-label', this.selectDateTextValue);
        this.syncDisplay();
        this.build();
        document.addEventListener('click', this.boundOutsideClick);
    }

    disconnect() {
        document.removeEventListener('click', this.boundOutsideClick);
        this.panel?.remove();
    }

    toggle(event) {
        event.preventDefault();
        this.panel.classList.contains('hidden') ? this.open() : this.close();
    }

    open() {
        this.render();
        this.panel.classList.remove('hidden');
        this.displayTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.panel.classList.add('hidden');
        this.displayTarget.setAttribute('aria-expanded', 'false');
    }

    typed() {
        const parsed = this.isFa ? this.parseJalali(this.displayTarget.value) : this.parseGregorian(this.displayTarget.value);
        if (!this.displayTarget.value.trim()) {
            this.clear(false);
            return;
        }

        if (!parsed) {
            this.statusTarget.textContent = this.invalidDateTextValue;
            this.displayTarget.setCustomValidity(this.invalidDateTextValue);
            return;
        }

        this.selected = parsed;
        this.viewDate = parsed;
        this.valueTarget.value = this.formatGregorian(parsed);
        this.statusTarget.textContent = '';
        this.displayTarget.setCustomValidity('');
        this.render();
    }

    keydown(event) {
        if (event.key === 'Escape') {
            this.close();
        }
        if (event.key === 'Enter' && !this.panel.classList.contains('hidden')) {
            event.preventDefault();
            this.typed();
            if (this.displayTarget.checkValidity()) {
                this.close();
            }
        }
    }

    previousMonth() {
        this.viewDate = this.addMonths(this.viewDate, -1);
        this.render();
    }

    nextMonth() {
        this.viewDate = this.addMonths(this.viewDate, 1);
        this.render();
    }

    today() {
        this.selectDate(new Date());
    }

    clear(close = true) {
        this.selected = null;
        this.valueTarget.value = '';
        this.displayTarget.value = '';
        this.statusTarget.textContent = '';
        this.displayTarget.setCustomValidity('');
        if (close) {
            this.close();
        }
    }

    closeFromOutside(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    build() {
        this.panel = document.createElement('div');
        this.panel.className = 'locale-date-picker hidden';
        this.panel.setAttribute('role', 'dialog');
        this.panel.setAttribute('aria-label', this.selectDateTextValue);
        this.panel.dir = this.isFa ? 'rtl' : 'ltr';
        this.element.appendChild(this.panel);
    }

    render() {
        const monthTitle = this.isFa ? this.jalaliMonthTitle(this.viewDate) : `${EN_MONTHS[this.viewDate.getMonth()]} ${this.viewDate.getFullYear()}`;
        const weekdays = this.isFa ? FA_WEEKDAYS : EN_WEEKDAYS;

        this.panel.innerHTML = `
            <div class="locale-date-picker__header">
                <button type="button" class="locale-date-picker__nav" data-action="locale-date#previousMonth" aria-label="${this.previousMonthTextValue}">‹</button>
                <div class="locale-date-picker__title">${monthTitle}</div>
                <button type="button" class="locale-date-picker__nav" data-action="locale-date#nextMonth" aria-label="${this.nextMonthTextValue}">›</button>
            </div>
            <div class="locale-date-picker__weekdays">${weekdays.map((day) => `<span>${day}</span>`).join('')}</div>
            <div class="locale-date-picker__days">${this.dayButtons()}</div>
            <div class="locale-date-picker__actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-action="locale-date#clear">${this.clearTextValue}</button>
                <button type="button" class="btn btn-sm btn-primary" data-action="locale-date#today">${this.todayTextValue}</button>
            </div>
        `;
    }

    dayButtons() {
        return this.isFa ? this.jalaliDayButtons() : this.gregorianDayButtons();
    }

    jalaliDayButtons() {
        const [jy, jm] = this.gregorianToJalali(this.viewDate.getFullYear(), this.viewDate.getMonth() + 1, this.viewDate.getDate());
        const daysInMonth = jm <= 6 ? 31 : jm <= 11 ? 30 : this.isJalaliValid(jy, jm, 30) ? 30 : 29;
        const [gy, gm, gd] = this.jalaliToGregorian(jy, jm, 1);
        const firstDay = new Date(gy, gm - 1, gd);
        const offset = (firstDay.getDay() + 1) % 7;
        const todayKey = this.formatGregorian(new Date());
        const selectedKey = this.selected ? this.formatGregorian(this.selected) : '';
        const buttons = Array.from({ length: offset }, () => '<span></span>');

        for (let day = 1; day <= daysInMonth; day += 1) {
            const [dateYear, dateMonth, dateDay] = this.jalaliToGregorian(jy, jm, day);
            const date = new Date(dateYear, dateMonth - 1, dateDay);
            const key = this.formatGregorian(date);
            const classes = [
                'locale-date-picker__day',
                key === todayKey ? 'locale-date-picker__day--today' : '',
                key === selectedKey ? 'locale-date-picker__day--selected' : '',
            ].join(' ');
            buttons.push(`<button type="button" class="${classes}" data-date="${key}" data-action="locale-date#pick">${this.toPersianNumber(day)}</button>`);
        }

        return buttons.join('');
    }

    gregorianDayButtons() {
        const year = this.viewDate.getFullYear();
        const month = this.viewDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const todayKey = this.formatGregorian(new Date());
        const selectedKey = this.selected ? this.formatGregorian(this.selected) : '';
        const buttons = Array.from({ length: firstDay.getDay() }, () => '<span></span>');

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = new Date(year, month, day);
            const key = this.formatGregorian(date);
            const classes = [
                'locale-date-picker__day',
                key === todayKey ? 'locale-date-picker__day--today' : '',
                key === selectedKey ? 'locale-date-picker__day--selected' : '',
            ].join(' ');
            buttons.push(`<button type="button" class="${classes}" data-date="${key}" data-action="locale-date#pick">${day}</button>`);
        }

        return buttons.join('');
    }

    pick(event) {
        const date = this.parseCanonical(event.currentTarget.dataset.date);
        if (date) {
            this.selectDate(date);
        }
    }

    selectDate(date) {
        this.selected = date;
        this.viewDate = date;
        this.valueTarget.value = this.formatGregorian(date);
        this.syncDisplay();
        this.statusTarget.textContent = '';
        this.displayTarget.setCustomValidity('');
        this.valueTarget.dispatchEvent(new Event('change', { bubbles: true }));
        this.close();
    }

    syncDisplay() {
        if (!this.selected) {
            this.displayTarget.value = '';
            return;
        }

        this.displayTarget.value = this.isFa ? this.formatJalali(this.selected) : this.formatGregorian(this.selected);
    }

    addMonths(date, count) {
        if (this.isFa) {
            let [jy, jm, jd] = this.gregorianToJalali(date.getFullYear(), date.getMonth() + 1, date.getDate());
            jm += count;
            while (jm < 1) {
                jy -= 1;
                jm += 12;
            }
            while (jm > 12) {
                jy += 1;
                jm -= 12;
            }
            jd = Math.min(jd, jm <= 6 ? 31 : jm <= 11 ? 30 : this.isJalaliValid(jy, jm, 30) ? 30 : 29);
            const [gy, gm, gd] = this.jalaliToGregorian(jy, jm, jd);
            return new Date(gy, gm - 1, gd);
        }

        return new Date(date.getFullYear(), date.getMonth() + count, Math.min(date.getDate(), 28));
    }

    jalaliMonthTitle(date) {
        const [jy, jm] = this.gregorianToJalali(date.getFullYear(), date.getMonth() + 1, date.getDate());
        return `${FA_MONTHS[jm - 1]} ${this.toPersianNumber(jy)}`;
    }

    formatGregorian(date) {
        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0'),
        ].join('-');
    }

    formatJalali(date) {
        const [jy, jm, jd] = this.gregorianToJalali(date.getFullYear(), date.getMonth() + 1, date.getDate());
        return this.toPersianNumber(`${jy}/${String(jm).padStart(2, '0')}/${String(jd).padStart(2, '0')}`);
    }

    parseCanonical(value) {
        return this.parseGregorian(value);
    }

    parseGregorian(value) {
        const normalized = this.normalizeDigits(value || '').trim();
        const match = normalized.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$/);
        if (!match) {
            return null;
        }

        const year = Number(match[1]);
        const month = Number(match[2]);
        const day = Number(match[3]);
        const date = new Date(year, month - 1, day);

        return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day ? date : null;
    }

    parseJalali(value) {
        const normalized = this.normalizeDigits(value || '').trim();
        const match = normalized.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$/);
        if (!match) {
            return null;
        }

        const jy = Number(match[1]);
        const jm = Number(match[2]);
        const jd = Number(match[3]);
        if (!this.isJalaliValid(jy, jm, jd)) {
            return null;
        }

        const [gy, gm, gd] = this.jalaliToGregorian(jy, jm, jd);
        return new Date(gy, gm - 1, gd);
    }

    isJalaliValid(jy, jm, jd) {
        if (jy < 1 || jm < 1 || jm > 12 || jd < 1 || jd > 31) {
            return false;
        }
        const [gy, gm, gd] = this.jalaliToGregorian(jy, jm, jd);
        const [checkYear, checkMonth, checkDay] = this.gregorianToJalali(gy, gm, gd);
        return jy === checkYear && jm === checkMonth && jd === checkDay;
    }

    normalizeDigits(value) {
        return value.replace(/[۰-۹٠-٩]/g, (digit) => '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩'.indexOf(digit) % 10);
    }

    toPersianNumber(value) {
        return String(value).replace(/\d/g, (digit) => PERSIAN_DIGITS[Number(digit)]);
    }

    gregorianToJalali(gy, gm, gd) {
        gy -= 1600;
        gm -= 1;
        gd -= 1;
        let dayNo = 365 * gy + Math.floor((gy + 3) / 4) - Math.floor((gy + 99) / 100) + Math.floor((gy + 399) / 400);
        for (let i = 0; i < gm; i += 1) {
            dayNo += GREGORIAN_DAYS[i];
        }
        if (gm > 1 && ((gy + 1600) % 4 === 0 && ((gy + 1600) % 100 !== 0 || (gy + 1600) % 400 === 0))) {
            dayNo += 1;
        }
        dayNo += gd;

        let jalaliDayNo = dayNo - 79;
        const cycle = Math.floor(jalaliDayNo / 12053);
        jalaliDayNo %= 12053;

        let jy = 979 + 33 * cycle + 4 * Math.floor(jalaliDayNo / 1461);
        jalaliDayNo %= 1461;

        if (jalaliDayNo >= 366) {
            jy += Math.floor((jalaliDayNo - 1) / 365);
            jalaliDayNo = (jalaliDayNo - 1) % 365;
        }

        let jm = 0;
        for (; jm < 11 && jalaliDayNo >= JALALI_DAYS[jm]; jm += 1) {
            jalaliDayNo -= JALALI_DAYS[jm];
        }

        return [jy, jm + 1, jalaliDayNo + 1];
    }

    jalaliToGregorian(jy, jm, jd) {
        jy -= 979;
        jm -= 1;
        jd -= 1;
        let dayNo = 365 * jy + Math.floor(jy / 33) * 8 + Math.floor(((jy % 33) + 3) / 4);
        for (let i = 0; i < jm; i += 1) {
            dayNo += JALALI_DAYS[i];
        }
        dayNo += jd;

        let gregorianDayNo = dayNo + 79;
        let gy = 1600 + 400 * Math.floor(gregorianDayNo / 146097);
        gregorianDayNo %= 146097;

        let leap = true;
        if (gregorianDayNo >= 36525) {
            gregorianDayNo -= 1;
            gy += 100 * Math.floor(gregorianDayNo / 36524);
            gregorianDayNo %= 36524;
            if (gregorianDayNo >= 365) {
                gregorianDayNo += 1;
            } else {
                leap = false;
            }
        }

        gy += 4 * Math.floor(gregorianDayNo / 1461);
        gregorianDayNo %= 1461;

        if (gregorianDayNo >= 366) {
            leap = false;
            gregorianDayNo -= 1;
            gy += Math.floor(gregorianDayNo / 365);
            gregorianDayNo %= 365;
        }

        let gm = 0;
        for (; gm < 11; gm += 1) {
            const monthDays = GREGORIAN_DAYS[gm] + (gm === 1 && leap ? 1 : 0);
            if (gregorianDayNo < monthDays) {
                break;
            }
            gregorianDayNo -= monthDays;
        }

        return [gy, gm + 1, gregorianDayNo + 1];
    }
}
