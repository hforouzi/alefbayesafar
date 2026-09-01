<?php

namespace App\Modules\Tour\Form;

use App\Modules\Tour\Entity\TourPackageImage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class TourPackageImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'tour.image.file',
                'constraints' => [
                    new File(maxSize: '8M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif']),
                ],
            ])
            ->add('path', TextType::class, [
                'label' => 'tour.image.path',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('alt', TextType::class, [
                'label' => 'tour.image.alt',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('altFa', TextType::class, [
                'label' => 'tour.image.alt_fa',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'tour.image.position',
                'attr' => ['class' => 'form-input', 'min' => 0],
            ])
            ->add('primary', CheckboxType::class, [
                'label' => 'tour.image.primary',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TourPackageImage::class,
        ]);
    }
}
