<?php

namespace App\Modules\Default\Form;

use App\Modules\Default\Entity\MenuCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuCategoryType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => $this->translator->trans('menu_category.code'),
                'help' => $this->translator->trans('menu_category.help.code'),
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'administration',
                ],
            ])
            ->add('name', TextType::class, [
                'label' => $this->translator->trans('menu_category.name'),
                'help' => $this->translator->trans('menu_category.help.name'),
                'attr' => [
                    'class' => 'form-input',
                ],
            ])
            ->add('labelKey', TextType::class, [
                'label' => $this->translator->trans('menu_category.label_key'),
                'required' => false,
                'help' => $this->translator->trans('menu_category.help.label_key'),
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'navigation.administration',
                ],
            ])
            ->add('icon', TextType::class, [
                'label' => $this->translator->trans('menu_category.icon'),
                'required' => false,
                'help' => $this->translator->trans('menu_category.help.icon'),
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'icon-menu-dashboard',
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => $this->translator->trans('menu_category.position'),
                'attr' => [
                    'class' => 'form-input',
                    'min' => 0,
                ],
            ])
            ->add('active', CheckboxType::class, [
                'label' => $this->translator->trans('menu_category.active'),
                'required' => false,
                'attr' => [
                    'class' => 'form-checkbox',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MenuCategory::class,
        ]);
    }
}
