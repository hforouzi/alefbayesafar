import { startStimulusApp } from '@symfony/stimulus-bundle';
import DateModeController from './controllers/date_mode_controller.js';
import PassengerCounterController from './controllers/passenger_counter_controller.js';
import TripWizardController from './controllers/trip_wizard_controller.js';
import GeoSelectController from './controllers/geo_select_controller.js';
import LocaleDateController from './controllers/locale_date_controller.js';
import ChildAgesController from './controllers/child_ages_controller.js';

const app = startStimulusApp();

app.register('date-mode', DateModeController);
app.register('passenger-counter', PassengerCounterController);
app.register('trip-wizard', TripWizardController);
app.register('geo-select', GeoSelectController);
app.register('locale-date', LocaleDateController);
app.register('child-ages', ChildAgesController);
