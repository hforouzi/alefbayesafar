<?php

namespace App\Modules\Transfer\Form;

use App\Modules\Transfer\Entity\TransferOffer;
use App\Modules\Transfer\Enum\TransferAvailabilityStatus;
use App\Modules\Transfer\Enum\TransferPricingMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransferOfferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('validFrom', DateType::class, [
                'label' => 'transfer.offer.valid_from',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('validTo', DateType::class, [
                'label' => 'transfer.offer.valid_to',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('currency', TextType::class, [
                'label' => 'transfer.offer.currency',
                'attr' => ['class' => 'form-input', 'maxlength' => 3],
            ])
            ->add('pricingMode', ChoiceType::class, [
                'label' => 'transfer.offer.pricing_mode',
                'choices' => TransferPricingMode::choices(),
                'choice_value' => static fn (mixed $mode): string => $mode instanceof TransferPricingMode ? $mode->value : '',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('totalPrice', TextType::class, [
                'label' => 'transfer.offer.total_price',
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('availabilityStatus', ChoiceType::class, [
                'label' => 'transfer.offer.availability_status',
                'choices' => TransferAvailabilityStatus::choices(),
                'choice_value' => static fn (mixed $status): string => $status instanceof TransferAvailabilityStatus ? $status->value : '',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'transfer.offer.priority',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'transfer.offer.active',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TransferOffer::class,
            'validation_groups' => false,
        ]);
    }
}
