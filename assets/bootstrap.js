import { startStimulusApp } from '@symfony/stimulus-bundle';
import DateModeController from './controllers/date_mode_controller.js';
import JalaliDateController from './controllers/jalali_date_controller.js';
import PassengerCounterController from './controllers/passenger_counter_controller.js';
import TripWizardController from './controllers/trip_wizard_controller.js';

const app = startStimulusApp();

app.register('date-mode', DateModeController);
app.register('jalali-date', JalaliDateController);
app.register('passenger-counter', PassengerCounterController);
app.register('trip-wizard', TripWizardController);
