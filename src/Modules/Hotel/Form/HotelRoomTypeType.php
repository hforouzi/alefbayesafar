<?php

namespace App\Modules\Hotel\Form;

use App\Modules\Hotel\Entity\HotelRoomType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HotelRoomTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'hotel.room_type.name',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('nameFa', TextType::class, [
                'label' => 'hotel.room_type.name_fa',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('code', TextType::class, [
                'label' => 'hotel.room_type.code',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('maxAdults', IntegerType::class, [
                'label' => 'hotel.room_type.max_adults',
                'required' => false,
                'attr' => ['class' => 'form-input', 'min' => 1],
            ])
            ->add('maxChildren', IntegerType::class, [
                'label' => 'hotel.room_type.max_children',
                'required' => false,
                'attr' => ['class' => 'form-input', 'min' => 0],
            ])
            ->add('maxOccupancy', IntegerType::class, [
                'label' => 'hotel.room_type.max_occupancy',
                'required' => false,
                'attr' => ['class' => 'form-input', 'min' => 1],
            ])
            ->add('bedConfiguration', TextType::class, [
                'label' => 'hotel.room_type.bed_configuration',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('sizeSqm', TextType::class, [
                'label' => 'hotel.room_type.size_sqm',
                'required' => false,
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('descriptionOriginal', TextareaType::class, [
                'label' => 'hotel.room_type.description_original',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 3],
            ])
            ->add('descriptionFa', TextareaType::class, [
                'label' => 'hotel.room_type.description_fa',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 3],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'hotel.common.active',
                'required' => false,
                'label_attr' => ['class' => 'inline-flex items-center gap-2'],
                'attr' => ['class' => 'form-checkbox'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HotelRoomType::class,
        ]);
    }
}
