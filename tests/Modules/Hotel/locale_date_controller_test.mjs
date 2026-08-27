import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs
    .readFileSync(new URL('../../../assets/controllers/locale_date_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';\r\n\r\n", '')
    .replace("import { Controller } from '@hotwired/stimulus';\n\n", '')
    .replace('export default class extends Controller', 'class LocaleDateController extends Controller');

class Controller {}

const context = {
    Controller,
    Date,
    Event: class Event {
        constructor(type) {
            this.type = type;
        }
    },
    document: {
        querySelector() {
            return null;
        },
    },
};

vm.createContext(context);
vm.runInContext(`${source}\nthis.LocaleDateController = LocaleDateController;`, context);

function controller(displayValue = '', hiddenValue = '') {
    const instance = new context.LocaleDateController();
    instance.locale = 'fa';
    instance.isFa = true;
    instance.invalidDateTextValue = 'Enter a valid date.';
    instance.displayTarget = {
        id: 'hotel_offer_search_checkIn_display',
        value: displayValue,
        setCustomValidity(value) {
            this.validity = value;
        },
        reportValidity() {
            this.reported = true;
        },
    };
    instance.valueTarget = {
        id: 'hotel_offer_search_checkIn',
        value: hiddenValue,
        dispatchEvent() {},
    };
    instance.statusTarget = { textContent: '' };

    return instance;
}

test('renders Gregorian backend date as visible Jalali date', () => {
    const instance = controller('', '2026-09-10');
    instance.selected = instance.parseCanonical(instance.valueTarget.value);

    instance.syncDisplay();

    assert.equal(instance.displayTarget.value, '۱۴۰۵/۰۶/۱۹');
    assert.equal(instance.valueTarget.value, '2026-09-10');
});

test('converts visible Jalali date to Gregorian hidden value before submit', () => {
    const instance = controller('1405/06/19', '1405/06/19');
    const event = {
        prevented: false,
        stopped: false,
        preventDefault() {
            this.prevented = true;
        },
        stopPropagation() {
            this.stopped = true;
        },
    };

    instance.beforeSubmit(event);

    assert.equal(event.prevented, false);
    assert.equal(instance.displayTarget.value, '1405/06/19');
    assert.equal(instance.valueTarget.value, '2026-09-10');
    assert.match(instance.valueTarget.value, /^\d{4}-\d{2}-\d{2}$/);
});

test('blocks submit when visible Jalali date cannot be converted', () => {
    const instance = controller('not-a-date', 'not-a-date');
    const event = {
        prevented: false,
        stopped: false,
        preventDefault() {
            this.prevented = true;
        },
        stopPropagation() {
            this.stopped = true;
        },
    };

    instance.beforeSubmit(event);

    assert.equal(event.prevented, true);
    assert.equal(event.stopped, true);
    assert.equal(instance.valueTarget.value, 'not-a-date');
    assert.equal(instance.statusTarget.textContent, 'Enter a valid date.');
    assert.equal(instance.displayTarget.validity, 'Enter a valid date.');
    assert.equal(instance.displayTarget.reported, true);
});
