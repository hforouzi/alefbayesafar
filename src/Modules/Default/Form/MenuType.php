<?php
// src/Form/MenuType.php

namespace App\Modules\Default\Form;

use App\Modules\Default\Entity\Menu;
use App\Modules\Default\Entity\MenuCategory;
use App\Modules\Default\Repository\MenuCategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Modules\User\Entity\Permission;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuType extends AbstractType
{
    private RouterInterface $router;
    private TranslatorInterface $translator;
    
    public function __construct(RouterInterface $router, TranslatorInterface $translator)
    {
        $this->router = $router;
        $this->translator = $translator;
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $routes = $this->router->getRouteCollection();
        $choices = [];
        // ساخت آرایه choices برای نمایش در dropdown
        foreach ($routes as $name => $route) {
            if (str_starts_with((string) $name, '_')) {
                continue;
            }

            if ($route->compile()->getVariables() !== []) {
                continue;
            }

            $choices[$name] = $name;
        }
        $menuParent = [
            $this->translator->trans('navigation.main') => 'main',
            $this->translator->trans('navigation.administration') => 'administration',
            $this->translator->trans('navigation.settings') => 'settings',
            $this->translator->trans('navigation.apps') => 'apps',
        ];
        
        $builder
            ->add('name', TextType::class)
            ->add('icon', TextType::class, [
                'required' => false,
            ])
            ->add('route', ChoiceType::class, [
                'choices' => $choices,
                'placeholder' => 'Select a route',
                'label' => 'select a route',
                'help' => $this->translator->trans('menu.route_help'),
            ])
            ->add('category', ChoiceType::class, [
                'choices' => $menuParent,
                'label' => 'select',
                'required' => false,
            ])
            ->add('menuCategory', EntityType::class, [
                'class' => MenuCategory::class,
                'choice_label' => static fn (MenuCategory $menuCategory): string => sprintf('%s (%s)', $menuCategory->getName(), $menuCategory->getCode()),
                'placeholder' => $this->translator->trans('menu.menu_category_placeholder'),
                'label' => $this->translator->trans('menu.menu_category'),
                'required' => false,
                'help' => $this->translator->trans('menu.help.menu_category'),
                'query_builder' => static fn (MenuCategoryRepository $repository) => $repository->createQueryBuilder('mc')
                    ->orderBy('mc.position', 'ASC')
                    ->addOrderBy('mc.name', 'ASC'),
            ])
            ->add('parent', EntityType::class, [
                'class' => Menu::class,
                'choice_label' => 'name',
                'required' => false,
            ])
            ->add('position', IntegerType::class)
            ->add('permissions', EntityType::class, [
                'class' => Permission::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                "label_html"=>true
            ]);
    }
    
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Menu::class,
        ]);
    }
}
