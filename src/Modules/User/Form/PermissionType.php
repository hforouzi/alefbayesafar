<?php
namespace App\Modules\User\Form;

use App\Modules\User\Entity\ControllerAction;
use App\Modules\User\Entity\Permission;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class PermissionType extends AbstractType
{
    protected $translator;
    public function __construct( TranslatorInterface $translator)
    {
        
        $this->translator = $translator;
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name',TextType::class,[
                'label' => $this->translator->trans('permission.name'),
                'attr' => [
                    'class' => 'form-input',
                ]])
            ->add('controllerActions', EntityType::class, [
                'class' => ControllerAction::class,
                'choice_label' => function (ControllerAction $action) {
                    
                    if (!str_contains($action->getController(),'web_profiler')) {
                        return str_replace('App\Modules','',$action->getController()) . '::' . $action->getAction();
                    }
                },
                'multiple' => true,
                'label' => $this->translator->trans('permission.controller_actions'),
                'help' => $this->translator->trans('permission.help.controller_actions'),
                'expanded' => true,
                'attr' => [
                    'class' => 'form-checkbox',
                ],
            ])
            ->add('route', TextType::class, [
                'label' => $this->translator->trans('permission.route'),
                'required' => true,
                'help' => $this->translator->trans('permission.help.route'),
                'attr' => [
                    'class' => 'form-input',
                ],
            ]);
        ;
    }
    
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Permission::class,
        ]);
    }
}
