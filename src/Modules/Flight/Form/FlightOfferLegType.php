<?php

namespace App\Modules\Flight\Form;

use App\Modules\Flight\Entity\FlightOfferLeg;
use App\Modules\Flight\Enum\FlightDirection;
use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\Flight\Entity\Airline;
use App\Modules\Flight\Repository\AirlineRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlightOfferLegType extends AbstractType
{
    public function __construct(
        private readonly AirlineRepository $airlineRepository,
        private readonly AirportRepository $airportRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('airlineId', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'flight.leg.airline',
            ])
            ->add('originAirportId', HiddenType::class, [
                'mapped' => false,
                'label' => 'flight.leg.origin_airport',
            ])
            ->add('destinationAirportId', HiddenType::class, [
                'mapped' => false,
                'label' => 'flight.leg.destination_airport',
            ])
            ->add('direction', ChoiceType::class, [
                'label' => 'flight.leg.direction',
                'choices' => FlightDirection::choices(),
                'choice_value' => static fn (mixed $direction): string => $direction instanceof FlightDirection ? $direction->value : '',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('segmentIndex', IntegerType::class, [
                'label' => 'flight.leg.segment_index',
                'attr' => ['class' => 'form-input', 'min' => 0],
            ])
            ->add('flightNumber', TextType::class, [
                'label' => 'flight.leg.flight_number',
                'required' => false,
                'attr' => ['class' => 'form-input', 'maxlength' => 16],
            ])
            ->add('departureAt', DateTimeType::class, [
                'label' => 'flight.leg.departure_at',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('arrivalAt', DateTimeType::class, [
                'label' => 'flight.leg.arrival_at',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('durationMinutes', IntegerType::class, [
                'label' => 'flight.leg.duration_minutes',
                'required' => false,
                'attr' => ['class' => 'form-input', 'min' => 1],
            ])
            ->add('aircraft', TextType::class, [
                'label' => 'flight.leg.aircraft',
                'required' => false,
                'attr' => ['class' => 'form-input', 'maxlength' => 120],
            ])
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
                $form = $event->getForm();
                $leg = $event->getData();

                if (!$leg instanceof FlightOfferLeg) {
                    return;
                }

                $airlineId = $form->get('airlineId')->getData();
                $airline = $airlineId !== null && $airlineId !== '' ? $this->airlineRepository->find((int) $airlineId) : null;
                if ($airlineId !== null && $airlineId !== '' && !$airline instanceof Airline) {
                    $form->get('airlineId')->addError(new FormError('flight.leg.validation.airline_required'));
                }
                $leg->setAirline($airline);

                $originId = $form->get('originAirportId')->getData();
                $origin = $originId !== null && $originId !== '' ? $this->airportRepository->find((int) $originId) : null;
                if (!$origin instanceof Airport) {
                    $form->get('originAirportId')->addError(new FormError('flight.leg.validation.origin_required'));
                }
                $leg->setOriginAirport($origin);

                $destinationId = $form->get('destinationAirportId')->getData();
                $destination = $destinationId !== null && $destinationId !== '' ? $this->airportRepository->find((int) $destinationId) : null;
                if (!$destination instanceof Airport) {
                    $form->get('destinationAirportId')->addError(new FormError('flight.leg.validation.destination_required'));
                }
                $leg->setDestinationAirport($destination);
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FlightOfferLeg::class,
        ]);
    }
}
