<?php

namespace App\Modules\Destination\Form;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\State;
use App\Modules\Destination\Repository\CountryRepository;
use App\Modules\Destination\Repository\StateRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('country', EntityType::class, [
                'class' => Country::class,
                'choice_label' => static fn (Country $country): string => (string) $country,
                'placeholder' => 'destination.city.country_placeholder',
                'label' => 'destination.city.country',
                'attr' => ['class' => 'form-select'],
                'query_builder' => static fn (CountryRepository $repository) => $repository->createQueryBuilder('country')
                    ->orderBy('country.name', 'ASC'),
            ])
            ->add('state', EntityType::class, [
                'class' => State::class,
                'choice_label' => static fn (State $state): string => (string) $state,
                'placeholder' => 'destination.city.state_placeholder',
                'label' => 'destination.city.state',
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'query_builder' => static fn (StateRepository $repository) => $repository->createQueryBuilder('state')
                    ->addSelect('country')
                    ->innerJoin('state.country', 'country')
                    ->orderBy('country.name', 'ASC')
                    ->addOrderBy('state.name', 'ASC'),
            ])
            ->add('name', TextType::class, [
                'label' => 'destination.city.name',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('nameFa', TextType::class, [
                'label' => 'destination.city.name_fa',
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
            'data_class' => City::class,
        ]);
    }
}
