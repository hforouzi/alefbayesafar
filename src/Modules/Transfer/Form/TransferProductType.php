<?php

namespace App\Modules\Transfer\Form;

use App\Modules\Transfer\Entity\TransferProduct;
use App\Modules\Transfer\Enum\TransferType;
use App\Modules\Transfer\Enum\TransferVehicleType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransferProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('originAirportId', HiddenType::class, ['mapped' => false, 'required' => false])
            ->add('originCityId', HiddenType::class, ['mapped' => false, 'required' => false])
            ->add('originHotelId', HiddenType::class, ['mapped' => false, 'required' => false])
            ->add('destinationAirportId', HiddenType::class, ['mapped' => false, 'required' => false])
            ->add('destinationCityId', HiddenType::class, ['mapped' => false, 'required' => false])
            ->add('destinationHotelId', HiddenType::class, ['mapped' => false, 'required' => false])
            ->add('name', TextType::class, [
                'label' => 'transfer.product.name',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('nameFa', TextType::class, [
                'label' => 'transfer.product.name_fa',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('transferType', ChoiceType::class, [
                'label' => 'transfer.product.transfer_type',
                'choices' => TransferType::choices(),
                'choice_value' => static fn (mixed $type): string => $type instanceof TransferType ? $type->value : '',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('vehicleType', ChoiceType::class, [
                'label' => 'transfer.product.vehicle_type',
                'choices' => TransferVehicleType::choices(),
                'choice_value' => static fn (mixed $type): string => $type instanceof TransferVehicleType ? $type->value : '',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('maxPassengers', IntegerType::class, [
                'label' => 'transfer.product.max_passengers',
                'required' => false,
                'attr' => ['class' => 'form-input', 'min' => 1],
            ])
            ->add('maxLuggage', IntegerType::class, [
                'label' => 'transfer.product.max_luggage',
                'required' => false,
                'attr' => ['class' => 'form-input', 'min' => 0],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'transfer.product.description',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 4],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'transfer.product.active',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->add('featured', CheckboxType::class, [
                'label' => 'transfer.product.featured',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->add('publicVisible', CheckboxType::class, [
                'label' => 'transfer.product.public_visible',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                $product = $event->getData();
                if (!$product instanceof TransferProduct) {
                    return;
                }

                $form = $event->getForm();
                $form->get('originAirportId')->setData($product->getOriginAirport()?->getId());
                $form->get('originCityId')->setData($product->getOriginCity()?->getId());
                $form->get('originHotelId')->setData($product->getOriginHotel()?->getId());
                $form->get('destinationAirportId')->setData($product->getDestinationAirport()?->getId());
                $form->get('destinationCityId')->setData($product->getDestinationCity()?->getId());
                $form->get('destinationHotelId')->setData($product->getDestinationHotel()?->getId());
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TransferProduct::class,
            'validation_groups' => false,
        ]);
    }
}
