<?php

namespace App\Modules\Activity\Form;

use App\Modules\Activity\Entity\ActivityImage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ActivityImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'activity.image.file',
                'constraints' => [
                    new File(maxSize: '8M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif']),
                ],
            ])
            ->add('path', TextType::class, [
                'label' => 'activity.image.path',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('alt', TextType::class, [
                'label' => 'activity.image.alt',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('altFa', TextType::class, [
                'label' => 'activity.image.alt_fa',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'activity.image.position',
                'attr' => ['class' => 'form-input', 'min' => 0],
            ])
            ->add('primary', CheckboxType::class, [
                'label' => 'activity.image.primary',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ActivityImage::class,
        ]);
    }
}
