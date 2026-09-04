<?php

namespace App\Modules\Flight\Form;

use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Enum\FlightAvailabilityStatus;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightPricingMode;
use App\Modules\Flight\Enum\FlightTripType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlightOfferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tripType', ChoiceType::class, [
                'label' => 'flight.offer.trip_type',
                'choices' => FlightTripType::choices(),
                'choice_value' => static fn (mixed $type): string => $type instanceof FlightTripType ? $type->value : '',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('pricingMode', ChoiceType::class, [
                'label' => 'flight.offer.pricing_mode',
                'choices' => FlightPricingMode::choices(),
                'choice_value' => static fn (mixed $mode): string => $mode instanceof FlightPricingMode ? $mode->value : '',
                'attr' => ['class' => 'form-select'],
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
            ->add('currency', TextType::class, [
                'label' => 'flight.offer.currency',
                'attr' => ['class' => 'form-input', 'maxlength' => 3],
            ])
            ->add('adultPrice', TextType::class, [
                'label' => 'flight.offer.adult_price',
                'required' => false,
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('childPrice', TextType::class, [
                'label' => 'flight.offer.child_price',
                'required' => false,
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('infantPrice', TextType::class, [
                'label' => 'flight.offer.infant_price',
                'required' => false,
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('totalPrice', TextType::class, [
                'label' => 'flight.offer.total_price',
                'required' => false,
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('baggage', TextareaType::class, [
                'label' => 'flight.offer.baggage',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 3],
            ])
            ->add('availabilityStatus', ChoiceType::class, [
                'label' => 'flight.offer.availability',
                'choices' => FlightAvailabilityStatus::choices(),
                'choice_value' => static fn (mixed $status): string => $status instanceof FlightAvailabilityStatus ? $status->value : '',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'flight.offer.priority',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('validFrom', DateType::class, [
                'label' => 'flight.offer.valid_from',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('validTo', DateType::class, [
                'label' => 'flight.offer.valid_to',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'flight.common.active',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FlightOffer::class,
        ]);
    }
}
