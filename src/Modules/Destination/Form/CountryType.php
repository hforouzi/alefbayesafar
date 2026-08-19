<?php

namespace App\Modules\Destination\Form;

use App\Modules\Destination\Entity\Country;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CountryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'destination.country.name',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('nameFa', TextType::class, [
                'label' => 'destination.country.name_fa',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('iso2', TextType::class, [
                'label' => 'destination.country.iso2',
                'required' => false,
                'attr' => ['class' => 'form-input', 'maxlength' => 2],
            ])
            ->add('iso3', TextType::class, [
                'label' => 'destination.country.iso3',
                'required' => false,
                'attr' => ['class' => 'form-input', 'maxlength' => 3],
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
            'data_class' => Country::class,
        ]);
    }
}
