<?php

namespace App\Modules\Destination\Form;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Repository\CityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AirportType extends AbstractType
{
    public function __construct(
        private readonly CityRepository $cityRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cityId', HiddenType::class, [
                'mapped' => false,
                'label' => 'destination.airport.city',
            ])
            ->add('name', TextType::class, [
                'label' => 'destination.airport.name',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('nameFa', TextType::class, [
                'label' => 'destination.airport.name_fa',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('iataCode', TextType::class, [
                'label' => 'destination.airport.iata',
                'required' => false,
                'attr' => ['class' => 'form-input', 'maxlength' => 3],
            ])
            ->add('icaoCode', TextType::class, [
                'label' => 'destination.airport.icao',
                'required' => false,
                'attr' => ['class' => 'form-input', 'maxlength' => 4],
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'destination.airport.latitude',
                'required' => false,
                'scale' => 7,
                'attr' => ['class' => 'form-input', 'step' => '0.0000001'],
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'destination.airport.longitude',
                'required' => false,
                'scale' => 7,
                'attr' => ['class' => 'form-input', 'step' => '0.0000001'],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'destination.common.active',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
                $airport = $event->getData();
                if (!$airport instanceof Airport) {
                    return;
                }

                $cityId = $event->getForm()->get('cityId')->getData();
                $city = $cityId !== null && $cityId !== '' ? $this->cityRepository->find((int) $cityId) : null;
                $airport->setCity($city);
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Airport::class,
        ]);
    }
}
