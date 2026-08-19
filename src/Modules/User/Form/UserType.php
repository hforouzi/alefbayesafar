<?php
namespace App\Modules\User\Form;

use App\Modules\User\Entity\Role;
use App\Modules\User\Entity\UserEntity;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'user.email',
                'required' => true,
            ])
            ->add('password', PasswordType::class, [
                'label' => 'user.password',
                'required' => !$options['data']->getId(), // Required for new users only
                'mapped' => false, // Don't map directly to entity
            ])
            ->add('userRoles', EntityType::class, [
                'class' => Role::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) use ($options) {
                    $queryBuilder = $er->createQueryBuilder('r')
                        ->orderBy('r.name', 'ASC');

                    if ($options['allowed_role_names'] !== null) {
                        $queryBuilder
                            ->andWhere('r.name IN (:allowedRoleNames)')
                            ->setParameter('allowedRoleNames', $options['allowed_role_names'] !== [] ? $options['allowed_role_names'] : ['__none__']);
                    }

                    return $queryBuilder;
                },
                'multiple' => true,
                'expanded' => true,
                'label' => 'user.roles',
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'action.save',
                'attr' => ['class' => 'btn btn-primary']
            ]);
    }
    
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserEntity::class,
            'allowed_role_names' => null,
        ]);

        $resolver->setAllowedTypes('allowed_role_names', ['null', 'array']);
    }
}
