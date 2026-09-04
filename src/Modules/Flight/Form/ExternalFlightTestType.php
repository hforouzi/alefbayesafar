<?php

namespace App\Modules\Flight\Form;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\Flight\Enum\FlightCabinClass;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExternalFlightTestType extends AbstractType
{
    public function __construct(private readonly AirportRepository $airportRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('originAirportId', HiddenType::class, ['required' => true])
            ->add('destinationAirportId', HiddenType::class, ['required' => true])
            ->add('departureDate', DateType::class, [
                'label' => 'flight.external_test.departure_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('returnDate', DateType::class, [
                'label' => 'flight.external_test.return_date',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('adults', IntegerType::class, [
                'label' => 'flight.offer.adults',
                'attr' => ['class' => 'form-input', 'min' => 1],
            ])
            ->add('children', IntegerType::class, [
                'label' => 'flight.offer.children',
                'attr' => ['class' => 'form-input', 'min' => 0],
            ])
            ->add('infants', IntegerType::class, [
                'label' => 'flight.offer.infants',
                'attr' => ['class' => 'form-input', 'min' => 0],
            ])
            ->add('cabinClass', ChoiceType::class, [
                'label' => 'flight.offer.cabin_class',
                'choices' => FlightCabinClass::choices(),
                'choice_value' => static fn (mixed $class): string => $class instanceof FlightCabinClass ? $class->value : '',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('directOnly', CheckboxType::class, [
                'label' => 'flight.external_test.direct_only',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
                $form = $event->getForm();
                $data = \is_array($event->getData()) ? $event->getData() : [];

                $origin = $this->airport((string) ($data['originAirportId'] ?? ''));
                $destination = $this->airport((string) ($data['destinationAirportId'] ?? ''));
                if (!$origin instanceof Airport) {
                    $form->get('originAirportId')->addError(new FormError('flight.external_test.validation.origin_required'));
                }
                if (!$destination instanceof Airport) {
                    $form->get('destinationAirportId')->addError(new FormError('flight.external_test.validation.destination_required'));
                }

                $data['originAirport'] = $origin;
                $data['destinationAirport'] = $destination;
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
                'adults' => 2,
                'children' => 0,
                'infants' => 0,
                'cabinClass' => FlightCabinClass::ECONOMY,
                'directOnly' => false,
            ],
        ]);
    }

    private function airport(string $id): ?Airport
    {
        return preg_match('/^\d+$/', $id) === 1 ? $this->airportRepository->find((int) $id) : null;
    }
}
