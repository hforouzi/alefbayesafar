<?php
namespace App\Modules\User\Form;

use App\Modules\User\Entity\Permission;
use App\Modules\User\Entity\Role;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentRole = $options['current_role'];
        $excludedRoleIds = $currentRole instanceof Role ? $this->getExcludedParentChoiceIds($currentRole) : [];

        $builder
            ->add('name', null, [
                'label' => 'role.name',
                'help' => 'role.help.name',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'ROLE_ADMIN',
                ],
            ])
            ->add('parent', EntityType::class, [
                'class' => Role::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $repository) use ($excludedRoleIds) {
                    $queryBuilder = $repository->createQueryBuilder('role')
                        ->orderBy('role.name', 'ASC');

                    if ($excludedRoleIds !== []) {
                        $queryBuilder
                            ->andWhere('role.id NOT IN (:excludedRoleIds)')
                            ->setParameter('excludedRoleIds', $excludedRoleIds);
                    }

                    return $queryBuilder;
                },
                'required' => false,
                'placeholder' => 'role.parent.placeholder',
                'label' => 'role.parent',
                'help' => 'role.help.parent',
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('permissions', EntityType::class, [
                'class' => Permission::class,
                'choice_label' => function (Permission $permission) {
                    return $permission->getName();
                },
                'multiple' => true,
                'expanded' => true,
                'label' => 'role.permissions',
                'help' => 'role.help.permissions',
            ]);
    }
    
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Role::class,
            'current_role' => null,
        ]);
        $resolver->setAllowedTypes('current_role', [Role::class, 'null']);
    }

    /**
     * @return int[]
     */
    private function getExcludedParentChoiceIds(Role $role): array
    {
        $excludedIds = [];
        $roleId = $role->getId();
        if ($roleId !== null) {
            $excludedIds[] = $roleId;
        }

        foreach ($this->getDescendantRoleIds($role) as $descendantId) {
            $excludedIds[] = $descendantId;
        }

        return array_values(array_unique($excludedIds));
    }

    /**
     * @return int[]
     */
    private function getDescendantRoleIds(Role $role, array $visitedRoleObjectIds = []): array
    {
        $roleObjectId = spl_object_id($role);
        if (isset($visitedRoleObjectIds[$roleObjectId])) {
            return [];
        }

        $visitedRoleObjectIds[$roleObjectId] = true;
        $descendantIds = [];

        foreach ($role->getChildren() as $child) {
            $childId = $child->getId();
            if ($childId !== null) {
                $descendantIds[] = $childId;
            }

            foreach ($this->getDescendantRoleIds($child, $visitedRoleObjectIds) as $descendantId) {
                $descendantIds[] = $descendantId;
            }
        }

        return $descendantIds;
    }
}
