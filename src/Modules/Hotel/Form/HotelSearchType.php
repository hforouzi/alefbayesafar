<?php

namespace App\Modules\Hotel\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HotelSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('query', SearchType::class, [
                'label' => 'hotel.search.query',
                'attr' => ['class' => 'form-input', 'autocomplete' => 'off'],
            ])
            ->add('cityId', HiddenType::class, [
                'label' => 'hotel.hotel.city',
            ])
            ->add('districtId', HiddenType::class, [
                'label' => 'hotel.hotel.district',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
