<?php

namespace App\Modules\Activity\Form;

use App\Modules\Activity\Entity\ActivityOffer;
use App\Modules\Activity\Enum\ActivityAvailabilityStatus;
use App\Modules\Activity\Enum\ActivityPricingMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActivityOfferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('validFrom', DateType::class, [
                'label' => 'activity.offer.valid_from',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('validTo', DateType::class, [
                'label' => 'activity.offer.valid_to',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('specificDate', DateType::class, [
                'label' => 'activity.offer.specific_date',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('currency', TextType::class, [
                'label' => 'activity.offer.currency',
                'attr' => ['class' => 'form-input', 'maxlength' => 3],
            ])
            ->add('pricingMode', ChoiceType::class, [
                'label' => 'activity.offer.pricing_mode',
                'choices' => ActivityPricingMode::choices(),
                'choice_value' => static fn (mixed $mode): string => $mode instanceof ActivityPricingMode ? $mode->value : '',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('totalPrice', TextType::class, [
                'label' => 'activity.offer.total_price',
                'required' => false,
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('adultPrice', TextType::class, [
                'label' => 'activity.offer.adult_price',
                'required' => false,
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('childPrice', TextType::class, [
                'label' => 'activity.offer.child_price',
                'required' => false,
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('infantPrice', TextType::class, [
                'label' => 'activity.offer.infant_price',
                'required' => false,
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('minimumParticipants', IntegerType::class, [
                'label' => 'activity.offer.minimum_participants',
                'required' => false,
                'attr' => ['class' => 'form-input', 'min' => 1],
            ])
            ->add('maximumParticipants', IntegerType::class, [
                'label' => 'activity.offer.maximum_participants',
                'required' => false,
                'attr' => ['class' => 'form-input', 'min' => 1],
            ])
            ->add('availabilityStatus', ChoiceType::class, [
                'label' => 'activity.offer.availability_status',
                'choices' => ActivityAvailabilityStatus::choices(),
                'choice_value' => static fn (mixed $status): string => $status instanceof ActivityAvailabilityStatus ? $status->value : '',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'activity.offer.priority',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'activity.offer.active',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ActivityOffer::class,
            'validation_groups' => false,
        ]);
    }
}
