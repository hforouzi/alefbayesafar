<?php

namespace App\Modules\Destination\Form;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Repository\CityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AirportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('city', EntityType::class, [
                'class' => City::class,
                'choice_label' => static fn (City $city): string => (string) $city,
                'placeholder' => 'destination.airport.city_placeholder',
                'label' => 'destination.airport.city',
                'attr' => ['class' => 'form-select'],
                'query_builder' => static fn (CityRepository $repository) => $repository->createQueryBuilder('city')
                    ->innerJoin('city.country', 'country')
                    ->addSelect('country')
                    ->orderBy('country.name', 'ASC')
                    ->addOrderBy('city.name', 'ASC'),
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Airport::class,
        ]);
    }
}
