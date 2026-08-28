<?php

namespace App\Modules\Flight\Form;

use App\Modules\Flight\Entity\Airline;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AirlineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'flight.airline.name',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('nameFa', TextType::class, [
                'label' => 'flight.airline.name_fa',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('iataCode', TextType::class, [
                'label' => 'flight.airline.iata',
                'required' => false,
                'attr' => ['class' => 'form-input', 'maxlength' => 3],
            ])
            ->add('icaoCode', TextType::class, [
                'label' => 'flight.airline.icao',
                'required' => false,
                'attr' => ['class' => 'form-input', 'maxlength' => 4],
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
            'data_class' => Airline::class,
        ]);
    }
}
