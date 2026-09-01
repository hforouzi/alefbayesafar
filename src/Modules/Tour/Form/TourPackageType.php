<?php

namespace App\Modules\Tour\Form;

use App\Modules\Hotel\Enum\HotelBoardType;
use App\Modules\Tour\Entity\TourPackage;
use App\Modules\Tour\Enum\TourInclusionKey;
use App\Modules\Tour\Enum\TourPricingMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TourPackageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('originAirportId', HiddenType::class, ['mapped' => false, 'required' => false])
            ->add('destinationCityId', HiddenType::class, ['mapped' => false, 'required' => true])
            ->add('flightOfferId', HiddenType::class, ['mapped' => false, 'required' => false])
            ->add('hotelId', HiddenType::class, ['mapped' => false, 'required' => false])
            ->add('hotelRoomTypeId', HiddenType::class, ['mapped' => false, 'required' => false])
            ->add('childrenAgesText', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'tour.package.children_ages',
                'attr' => ['class' => 'form-input', 'placeholder' => '7, 11'],
            ])
            ->add('name', TextType::class, [
                'label' => 'tour.package.name',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('nameFa', TextType::class, [
                'label' => 'tour.package.name_fa',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'tour.package.slug',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('validFrom', DateType::class, [
                'label' => 'tour.package.valid_from',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('validTo', DateType::class, [
                'label' => 'tour.package.valid_to',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('departureDate', DateType::class, [
                'label' => 'tour.package.departure_date',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('returnDate', DateType::class, [
                'label' => 'tour.package.return_date',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('nights', IntegerType::class, [
                'label' => 'tour.package.nights',
                'attr' => ['class' => 'form-input', 'min' => 0],
            ])
            ->add('days', IntegerType::class, [
                'label' => 'tour.package.days',
                'required' => false,
                'attr' => ['class' => 'form-input', 'min' => 1],
            ])
            ->add('adults', IntegerType::class, [
                'label' => 'tour.package.adults',
                'attr' => ['class' => 'form-input', 'min' => 1],
            ])
            ->add('children', IntegerType::class, [
                'label' => 'tour.package.children',
                'attr' => ['class' => 'form-input', 'min' => 0],
            ])
            ->add('infants', IntegerType::class, [
                'label' => 'tour.package.infants',
                'attr' => ['class' => 'form-input', 'min' => 0],
            ])
            ->add('pricingMode', ChoiceType::class, [
                'label' => 'tour.package.pricing_mode',
                'choices' => TourPricingMode::choices(),
                'choice_value' => static fn (mixed $mode): string => $mode instanceof TourPricingMode ? $mode->value : '',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('currency', TextType::class, [
                'label' => 'tour.package.currency',
                'attr' => ['class' => 'form-input', 'maxlength' => 3],
            ])
            ->add('totalPrice', TextType::class, [
                'label' => 'tour.package.total_price',
                'required' => false,
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('adultPrice', TextType::class, [
                'label' => 'tour.package.adult_price',
                'required' => false,
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('childPrice', TextType::class, [
                'label' => 'tour.package.child_price',
                'required' => false,
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('infantPrice', TextType::class, [
                'label' => 'tour.package.infant_price',
                'required' => false,
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('boardType', ChoiceType::class, [
                'label' => 'tour.package.board_type',
                'choices' => ['tour.common.none' => ''] + HotelBoardType::formChoices(),
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('inclusions', ChoiceType::class, [
                'label' => 'tour.package.inclusions',
                'choices' => TourInclusionKey::choices(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('exclusions', ChoiceType::class, [
                'label' => 'tour.package.exclusions',
                'choices' => TourInclusionKey::choices(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('shortDescription', TextareaType::class, [
                'label' => 'tour.package.short_description',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 2],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'tour.package.description',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 5],
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'tour.package.priority',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'tour.package.active',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->add('featured', CheckboxType::class, [
                'label' => 'tour.package.featured',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->add('publicVisible', CheckboxType::class, [
                'label' => 'tour.package.public_visible',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                $package = $event->getData();
                if (!$package instanceof TourPackage) {
                    return;
                }

                $form = $event->getForm();
                $form->get('originAirportId')->setData($package->getOriginAirport()?->getId());
                $form->get('destinationCityId')->setData($package->getDestinationCity()?->getId());
                $form->get('flightOfferId')->setData($package->getFlightOffer()?->getId());
                $form->get('hotelId')->setData($package->getHotel()?->getId());
                $form->get('hotelRoomTypeId')->setData($package->getHotelRoomType()?->getId());
                $form->get('childrenAgesText')->setData(implode(', ', $package->getChildrenAges()));
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TourPackage::class,
            'validation_groups' => false,
        ]);
    }
}
