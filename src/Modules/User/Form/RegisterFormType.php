<?php
namespace App\Modules\User\Form;

use App\Modules\User\Entity\ControllerAction;
use App\Modules\User\Entity\Permission;
use App\Modules\User\Entity\Role;
use App\Modules\User\Repository\RoleRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterFormType extends AbstractType

{
    public function __construct(
        private readonly RoleRepository $roleRepository,
    ) {
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class)
            ->add('password', PasswordType::class)
            ->add('roles', ChoiceType::class,[
                'choices' =>$this->getRoles()                ,
                'multiple' => true,
                'expanded' => true,
                "label_html"=>true,
                'attr' => [
                    'class' => 'form-input',
                ],
            ])
        ;
    }
    private function getRoles(): array
    {
      $role= $this->roleRepository->findAll();
        foreach ($role as $item) {
            $roles[$item->getName()] = $item->getName();
      }
        return $roles;
      
    }
}