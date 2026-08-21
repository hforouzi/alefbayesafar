<?php

namespace App\Modules\Hotel\Form;

use App\Modules\Hotel\Entity\HotelImage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class HotelImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'mapped' => false,
                'label' => 'hotel.image.file',
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                        ],
                        'mimeTypesMessage' => 'hotel.image.validation.image_file',
                    ]),
                ],
                'attr' => ['class' => 'form-input', 'accept' => 'image/jpeg,image/png,image/webp,image/gif'],
            ])
            ->add('path', TextType::class, [
                'label' => 'hotel.image.path',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('alt', TextType::class, [
                'label' => 'hotel.image.alt',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('altFa', TextType::class, [
                'label' => 'hotel.image.alt_fa',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'hotel.image.position',
                'attr' => ['class' => 'form-input', 'min' => 0],
            ])
            ->add('primary', CheckboxType::class, [
                'label' => 'hotel.image.primary',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HotelImage::class,
        ]);
    }
}
