<?php

namespace App\Modules\Destination\Form;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\District;
use App\Modules\Destination\Repository\CityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DistrictType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('city', EntityType::class, [
                'class' => City::class,
                'choice_label' => static fn (City $city): string => (string) $city,
                'placeholder' => 'destination.district.city_placeholder',
                'label' => 'destination.district.city',
                'attr' => ['class' => 'form-select'],
                'query_builder' => static fn (CityRepository $repository) => $repository->createQueryBuilder('city')
                    ->innerJoin('city.country', 'country')
                    ->addSelect('country')
                    ->orderBy('country.name', 'ASC')
                    ->addOrderBy('city.name', 'ASC'),
            ])
            ->add('name', TextType::class, [
                'label' => 'destination.district.name',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('nameFa', TextType::class, [
                'label' => 'destination.district.name_fa',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'destination.common.slug',
                'attr' => ['class' => 'form-input'],
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
            'data_class' => District::class,
        ]);
    }
}
