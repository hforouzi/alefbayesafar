<?php

namespace App\Modules\Destination\Form;

use App\Modules\Destination\Entity\District;
use App\Modules\Destination\Repository\CityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DistrictType extends AbstractType
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
                'label' => 'destination.district.city',
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
            ])
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
                $district = $event->getData();
                if (!$district instanceof District) {
                    return;
                }

                $cityId = $event->getForm()->get('cityId')->getData();
                $city = $cityId !== null && $cityId !== '' ? $this->cityRepository->find((int) $cityId) : null;
                $district->setCity($city);
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => District::class,
        ]);
    }
}
