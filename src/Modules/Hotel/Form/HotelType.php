<?php

namespace App\Modules\Hotel\Form;

use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Repository\DistrictRepository;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelAmenity;
use App\Modules\Hotel\Repository\HotelAmenityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HotelType extends AbstractType
{
    public function __construct(
        private readonly CityRepository $cityRepository,
        private readonly DistrictRepository $districtRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cityId', HiddenType::class, [
                'mapped' => false,
                'label' => 'hotel.hotel.city',
            ])
            ->add('districtId', HiddenType::class, [
                'mapped' => false,
                'label' => 'hotel.hotel.district',
                'required' => false,
            ])
            ->add('name', TextType::class, [
                'label' => 'hotel.hotel.name',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('nameFa', TextType::class, [
                'label' => 'hotel.hotel.name_fa',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'hotel.common.slug',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('stars', ChoiceType::class, [
                'label' => 'hotel.hotel.stars',
                'required' => false,
                'placeholder' => 'hotel.hotel.unrated',
                'choices' => [
                    'hotel.hotel.star_1' => 1,
                    'hotel.hotel.star_2' => 2,
                    'hotel.hotel.star_3' => 3,
                    'hotel.hotel.star_4' => 4,
                    'hotel.hotel.star_5' => 5,
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'hotel.hotel.address',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 3],
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'hotel.hotel.latitude',
                'required' => false,
                'scale' => 7,
                'attr' => ['class' => 'form-input', 'step' => '0.0000001'],
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'hotel.hotel.longitude',
                'required' => false,
                'scale' => 7,
                'attr' => ['class' => 'form-input', 'step' => '0.0000001'],
            ])
            ->add('website', TextType::class, [
                'label' => 'hotel.hotel.website',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('phone', TextType::class, [
                'label' => 'hotel.hotel.phone',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('checkIn', TimeType::class, [
                'label' => 'hotel.hotel.check_in',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('checkOut', TimeType::class, [
                'label' => 'hotel.hotel.check_out',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('descriptionOriginal', TextareaType::class, [
                'label' => 'hotel.hotel.description_original',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 5],
            ])
            ->add('descriptionFa', TextareaType::class, [
                'label' => 'hotel.hotel.description_fa',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 5],
            ])
            ->add('amenities', EntityType::class, [
                'class' => HotelAmenity::class,
                'choice_label' => static fn (HotelAmenity $amenity): string => (string) $amenity,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'hotel.hotel.amenities',
                'query_builder' => static fn (HotelAmenityRepository $repository) => $repository->createQueryBuilder('amenity')
                    ->andWhere('amenity.active = :active')
                    ->setParameter('active', true)
                    ->orderBy('amenity.name', 'ASC'),
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'hotel.common.active',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->add('verified', CheckboxType::class, [
                'label' => 'hotel.common.verified',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
                $hotel = $event->getData();
                if (!$hotel instanceof Hotel) {
                    return;
                }

                $form = $event->getForm();
                $cityId = $form->get('cityId')->getData();
                $districtId = $form->get('districtId')->getData();

                $city = $cityId !== null && $cityId !== '' ? $this->cityRepository->find((int) $cityId) : null;
                $district = $districtId !== null && $districtId !== '' ? $this->districtRepository->find((int) $districtId) : null;

                $hotel->setCity($city);
                $hotel->setDistrict($district);
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Hotel::class,
        ]);
    }
}
