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
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Lightweight public-facing trip search form.
 *
 * Deliberately smaller than TripPlannerTestType: no origin airport field,
 * no internal source/provider inputs. Origin airport is resolved internally
 * (best-effort) from the chosen origin city when the mapped TripSearchRequest
 * is built.
 */
class PublicTripSearchType extends AbstractType
{
    public function __construct(
        private readonly CityRepository $cityRepository,
        private readonly CountryRepository $countryRepository,
        private readonly AirportRepository $airportRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('originCityId', HiddenType::class, ['required' => false])
            ->add('destinationCityId', HiddenType::class, ['required' => false])
            ->add('destinationCountryId', HiddenType::class, ['required' => false])
            ->add('dateMode', ChoiceType::class, [
                'required' => true,
                'expanded' => true,
                'choices' => [
                    'flexible' => TripDateMode::FLEXIBLE,
                    'exact' => TripDateMode::EXACT,
                ],
                'choice_value' => static fn (TripDateMode $mode): string => $mode->value,
            ])
            ->add('departureDate', DateType::class, ['required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('returnDate', DateType::class, ['required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('windowStart', DateType::class, ['required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('windowEnd', DateType::class, ['required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('nights', IntegerType::class, ['required' => false, 'attr' => ['min' => 1]])
            ->add('rooms', IntegerType::class, ['required' => true, 'attr' => ['min' => 1]])
            ->add('adults', IntegerType::class, ['attr' => ['min' => 1]])
            ->add('children', IntegerType::class, ['attr' => ['min' => 0]])
            ->add('childrenAgesText', TextType::class, ['required' => false, 'mapped' => false])
            ->add('budget', TextType::class, ['required' => false])
            ->add('hotelStarPreference', ChoiceType::class, [
                'required' => false,
                'choices' => ['1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5],
            ])
            ->add('breakfastPreferred', CheckboxType::class, ['required' => false])
            ->add('directFlightPreferred', CheckboxType::class, ['required' => false])
            ->add('transferRequired', CheckboxType::class, ['required' => false])
            ->add('flightCabin', ChoiceType::class, [
                'required' => true,
                'choices' => FlightCabinClass::choices(),
                'choice_value' => static fn (FlightCabinClass $choice): string => $choice->value,
            ])
            ->add('activityCategories', ChoiceType::class, [
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => ActivityCategory::choices(),
                'choice_value' => static fn (ActivityCategory $choice): string => $choice->value,
            ])
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
                $form = $event->getForm();
                $data = \is_array($event->getData()) ? $event->getData() : [];
                $dateMode = $data['dateMode'] instanceof TripDateMode ? $data['dateMode'] : TripDateMode::FLEXIBLE;

                $originCity = $this->city((string) ($data['originCityId'] ?? ''));
                $destinationCity = $this->city((string) ($data['destinationCityId'] ?? ''));
                $destinationCountry = $this->country((string) ($data['destinationCountryId'] ?? ''));
                if ($destinationCity instanceof City && !$destinationCountry instanceof Country) {
                    $destinationCountry = $destinationCity->getCountry();
                }

                if (!$originCity instanceof City) {
                    $form->get('originCityId')->addError(new FormError('Please tell us which city you are leaving from.'));
                }
                if (!$destinationCity instanceof City && !$destinationCountry instanceof Country) {
                    $form->get('destinationCityId')->addError(new FormError('Please choose a destination city or country.'));
                }
                if ($destinationCity instanceof City && $destinationCountry instanceof Country && $destinationCity->getCountry()?->getId() !== $destinationCountry->getId()) {
                    $form->get('destinationCityId')->addError(new FormError('Destination city must belong to the selected country.'));
                }

                if ($dateMode === TripDateMode::EXACT) {
                    if (!$data['departureDate'] instanceof \DateTimeImmutable) {
                        $form->get('departureDate')->addError(new FormError('Please choose a departure date.'));
                    }
                    if (!$data['returnDate'] instanceof \DateTimeImmutable) {
                        $form->get('returnDate')->addError(new FormError('Please choose a return date.'));
                    }
                } else {
                    if (!$data['windowStart'] instanceof \DateTimeImmutable) {
                        $form->get('windowStart')->addError(new FormError('Please choose the start of your travel window.'));
                    }
                    if (!$data['windowEnd'] instanceof \DateTimeImmutable) {
                        $form->get('windowEnd')->addError(new FormError('Please choose the end of your travel window.'));
                    }
                    if (($data['nights'] ?? null) === null) {
                        $form->get('nights')->addError(new FormError('Please tell us how many nights you want to stay.'));
                    }
                }

                $childrenAges = $this->childrenAges((string) $form->get('childrenAgesText')->getData());
                if ($childrenAges === null) {
                    $form->get('childrenAgesText')->addError(new FormError('Child ages must be numbers separated by commas.'));
                    $childrenAges = [];
                }

                $data['originCity'] = $originCity;
                $data['originAirport'] = $originCity instanceof City ? $this->primaryAirport($originCity) : null;
                $data['destinationCity'] = $destinationCity;
                $data['destinationCountry'] = $destinationCountry;
                $data['childrenAges'] = $childrenAges;
                $event->setData($data);
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'empty_data' => [
                'dateMode' => TripDateMode::FLEXIBLE,
                'departureDate' => null,
                'returnDate' => null,
                'windowStart' => null,
                'windowEnd' => null,
                'nights' => 5,
                'rooms' => 1,
                'adults' => 2,
                'children' => 0,
                'budget' => null,
                'hotelStarPreference' => null,
                'breakfastPreferred' => true,
                'directFlightPreferred' => false,
                'transferRequired' => false,
                'flightCabin' => FlightCabinClass::ECONOMY,
                'activityCategories' => [],
            ],
        ]);
    }

    /**
     * Best-effort internal resolution of an airport for the given city so
     * downstream flight search can run without ever asking the user for an
     * airport code. Public UI never exposes this value.
     */
    private function primaryAirport(City $city): ?Airport
    {
        $matches = $this->airportRepository->search('', $city, 1);

        return $matches[0] ?? null;
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
