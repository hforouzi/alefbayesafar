<?php

namespace App\Modules\TripPlanner\Form;

use App\Modules\Activity\Enum\ActivityCategory;
use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Repository\CountryRepository;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\TripPlanner\Enum\TripDateMode;
use App\Modules\TripPlanner\Enum\TripPlanningGoal;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TripPlannerTestType extends AbstractType
{
    public function __construct(
        private readonly AirportRepository $airportRepository,
        private readonly CityRepository $cityRepository,
        private readonly CountryRepository $countryRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('originAirportId', HiddenType::class, ['required' => false])
            ->add('originCityId', HiddenType::class, ['required' => false])
            ->add('destinationCountryId', HiddenType::class, ['required' => false])
            ->add('destinationCityId', HiddenType::class, ['required' => false])
            ->add('goal', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    'trip_planner.goal.specific_destination' => TripPlanningGoal::SPECIFIC_DESTINATION,
                    'trip_planner.goal.cheapest' => TripPlanningGoal::CHEAPEST,
                    'trip_planner.goal.best_value' => TripPlanningGoal::BEST_VALUE,
                    'trip_planner.goal.surprise_me' => TripPlanningGoal::SURPRISE_ME,
                ],
                'choice_value' => static fn (TripPlanningGoal $goal): string => $goal->value,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('dateMode', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    'trip_planner.date_mode.exact' => TripDateMode::EXACT,
                    'trip_planner.date_mode.flexible' => TripDateMode::FLEXIBLE,
                ],
                'choice_value' => static fn (TripDateMode $mode): string => $mode->value,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('departureDate', DateType::class, ['required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'attr' => ['class' => 'form-input']])
            ->add('returnDate', DateType::class, ['required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'attr' => ['class' => 'form-input']])
            ->add('windowStart', DateType::class, ['required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'attr' => ['class' => 'form-input']])
            ->add('windowEnd', DateType::class, ['required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'attr' => ['class' => 'form-input']])
            ->add('nights', IntegerType::class, ['required' => false, 'attr' => ['class' => 'form-input', 'min' => 1]])
            ->add('rooms', IntegerType::class, ['required' => true, 'attr' => ['class' => 'form-input', 'min' => 1]])
            ->add('adults', IntegerType::class, ['attr' => ['class' => 'form-input', 'min' => 1]])
            ->add('children', IntegerType::class, ['attr' => ['class' => 'form-input', 'min' => 0]])
            ->add('childrenAgesText', TextType::class, ['required' => false, 'mapped' => false, 'attr' => ['class' => 'form-input', 'placeholder' => '7, 11']])
            ->add('infants', IntegerType::class, ['attr' => ['class' => 'form-input', 'min' => 0]])
            ->add('budget', TextType::class, ['required' => false, 'attr' => ['class' => 'form-input', 'inputmode' => 'decimal']])
            ->add('preferredCurrency', TextType::class, ['required' => true, 'attr' => ['class' => 'form-input', 'maxlength' => 3]])
            ->add('hotelStarPreference', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'trip_planner.form.any_stars',
                'choices' => [
                    '1' => 1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                    '5' => 5,
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('breakfastPreferred', CheckboxType::class, ['required' => false, 'attr' => ['class' => 'form-checkbox']])
            ->add('flightCabin', ChoiceType::class, [
                'required' => true,
                'choices' => FlightCabinClass::choices(),
                'choice_label' => static fn (FlightCabinClass $choice): string => 'flight.cabin_class.' . $choice->value,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('directFlightPreferred', CheckboxType::class, ['required' => false, 'attr' => ['class' => 'form-checkbox']])
            ->add('transferRequired', CheckboxType::class, ['required' => false, 'attr' => ['class' => 'form-checkbox']])
            ->add('activityCategories', ChoiceType::class, [
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => ActivityCategory::choices(),
                'choice_label' => static fn (ActivityCategory $choice): string => $choice->labelKey(),
            ])
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
                $form = $event->getForm();
                $data = \is_array($event->getData()) ? $event->getData() : [];
                $dateMode = $data['dateMode'] instanceof TripDateMode ? $data['dateMode'] : TripDateMode::EXACT;
                $goal = $data['goal'] instanceof TripPlanningGoal ? $data['goal'] : TripPlanningGoal::SPECIFIC_DESTINATION;
                $destinationCountry = $this->country((string) ($data['destinationCountryId'] ?? ''));
                $destination = $this->city((string) ($data['destinationCityId'] ?? ''));
                if ($destination instanceof City && !$destinationCountry instanceof Country) {
                    $destinationCountry = $destination->getCountry();
                }
                $originAirport = $this->airport((string) ($data['originAirportId'] ?? ''));
                $originCity = $this->city((string) ($data['originCityId'] ?? ''));
                if ($goal === TripPlanningGoal::SPECIFIC_DESTINATION && !$destination instanceof City && !$destinationCountry instanceof Country) {
                    $form->get('destinationCountryId')->addError(new FormError('trip_planner.validation.destination_required'));
                }
                if ($goal === TripPlanningGoal::SPECIFIC_DESTINATION && !$originCity instanceof City && !$originAirport instanceof Airport) {
                    $form->get('originCityId')->addError(new FormError('trip_planner.validation.origin_required'));
                }
                if ($destination instanceof City && $destinationCountry instanceof Country && $destination->getCountry()?->getId() !== $destinationCountry->getId()) {
                    $form->get('destinationCityId')->addError(new FormError('trip_planner.validation.destination_city_country_mismatch'));
                }
                if ($dateMode === TripDateMode::EXACT) {
                    if (!$data['departureDate'] instanceof \DateTimeImmutable) {
                        $form->get('departureDate')->addError(new FormError('trip_planner.validation.departure_required'));
                    }
                    if (!$data['returnDate'] instanceof \DateTimeImmutable) {
                        $form->get('returnDate')->addError(new FormError('trip_planner.validation.return_required'));
                    }
                }
                if ($dateMode === TripDateMode::FLEXIBLE) {
                    if (!$data['windowStart'] instanceof \DateTimeImmutable) {
                        $form->get('windowStart')->addError(new FormError('trip_planner.validation.window_start_required'));
                    }
                    if (!$data['windowEnd'] instanceof \DateTimeImmutable) {
                        $form->get('windowEnd')->addError(new FormError('trip_planner.validation.window_end_required'));
                    }
                    if (($data['nights'] ?? null) === null) {
                        $form->get('nights')->addError(new FormError('trip_planner.validation.nights_required'));
                    }
                }

                $childrenAges = $this->childrenAges((string) $form->get('childrenAgesText')->getData());
                if ($childrenAges === null) {
                    $form->get('childrenAgesText')->addError(new FormError('trip_planner.validation.child_ages_numeric'));
                    $childrenAges = [];
                }

                $data['originAirport'] = $originAirport;
                $data['originCity'] = $originCity;
                $data['destinationCountry'] = $destinationCountry;
                $data['destinationCity'] = $destination;
                $data['childrenAges'] = $childrenAges;
                $event->setData($data);
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'empty_data' => [
                'departureDate' => new \DateTimeImmutable('+30 days'),
                'returnDate' => null,
                'dateMode' => TripDateMode::EXACT,
                'goal' => TripPlanningGoal::SPECIFIC_DESTINATION,
                'windowStart' => null,
                'windowEnd' => null,
                'nights' => 5,
                'rooms' => 1,
                'adults' => 2,
                'children' => 0,
                'infants' => 0,
                'budget' => null,
                'preferredCurrency' => 'EUR',
                'hotelStarPreference' => null,
                'breakfastPreferred' => true,
                'flightCabin' => FlightCabinClass::ECONOMY,
                'directFlightPreferred' => false,
                'transferRequired' => false,
                'activityCategories' => [],
                'destinationCountryId' => null,
            ],
        ]);
    }

    private function airport(string $id): ?Airport
    {
        return preg_match('/^\d+$/', $id) === 1 ? $this->airportRepository->find((int) $id) : null;
    }

    private function city(string $id): ?City
    {
        return preg_match('/^\d+$/', $id) === 1 ? $this->cityRepository->find((int) $id) : null;
    }

    private function country(string $id): ?Country
    {
        return preg_match('/^\d+$/', $id) === 1 ? $this->countryRepository->find((int) $id) : null;
    }

    /**
     * @return int[]|null
     */
    private function childrenAges(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $ages = [];
        foreach (preg_split('/[,\s]+/', $raw) ?: [] as $part) {
            if ($part === '') {
                continue;
            }
            if (!ctype_digit($part)) {
                return null;
            }
            $ages[] = (int) $part;
        }

        return $ages;
    }
}
