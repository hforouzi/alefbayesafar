import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'directFlight', 'hotelStars', 'destinationCityId', 'destinationCityDisplay'];

    directFlightOnly() {
        if (this.hasDirectFlightTarget) {
            this.directFlightTarget.checked = true;
        }
        this.submit();
    }

    betterHotel() {
        if (this.hasHotelStarsTarget) {
            this.hotelStarsTarget.value = '4';
        }
        this.submit();
    }

    anotherCity() {
        if (this.hasDestinationCityIdTarget) {
            this.destinationCityIdTarget.value = '';
        }
        if (this.hasDestinationCityDisplayTarget) {
            this.destinationCityDisplayTarget.value = '';
        }
        this.submit();
    }

    submit() {
        if (this.hasFormTarget) {
            this.formTarget.requestSubmit();
        }
    }
}
