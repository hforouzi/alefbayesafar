<?php
namespace App\Modules\User\Controller;

use App\Modules\User\Entity\Permission;
use App\Modules\User\Entity\Role;
use App\Modules\User\Form\PermissionType;
use App\Modules\User\Form\RoleType;
use App\Modules\User\Repository\PermissionRepository;
use App\Modules\User\Repository\RoleRepository;
use App\Modules\User\Repository\UserEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class RoleController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    #[Route('/role/new', name: 'role_new')]
    public function new(Request $request, EntityManagerInterface $em, PermissionRepository $permissionRepository): Response
    {
        $role = new Role();
        $form = $this->createForm(RoleType::class, $role, [
            'current_role' => $role,
        ]);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $this->validateParentRole($form, $role);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->persist($role);
                $em->flush();

                $this->addFlash('success', $this->translator->trans('role.created_successfully'));

                return $this->redirectToRoute('role_list');
            } catch (Throwable $exception) {
                $this->addFlash('danger', $this->translator->trans('role.save_failed'));
            }
        }
        
        return $this->render('@User/role/new.html.twig', [
            'form' => $form->createView(),
            'permissionGroups' => $permissionRepository->findGroupedForRoleForm(),
        ]);
    }
    
    #[Route('/role/{id}/edit', name: 'role_edit')]
    public function edit(Request $request, EntityManagerInterface $em, Role $role, PermissionRepository $permissionRepository): Response
    {
        $form = $this->createForm(RoleType::class, $role, [
            'current_role' => $role,
        ]);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $this->validateParentRole($form, $role);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->flush();

                $this->addFlash('success', $this->translator->trans('role.updated_successfully'));

                return $this->redirectToRoute('role_list');
            } catch (Throwable $exception) {
                $this->addFlash('danger', $this->translator->trans('role.save_failed'));
            }
        }
        
        return $this->render('@User/role/edit.html.twig', [
            'form' => $form->createView(),
            'permissionGroups' => $permissionRepository->findGroupedForRoleForm(),
        ]);
    }
    
    #[Route('/roles', name: 'role_list')]
    public function list(EntityManagerInterface $em): Response
    {
        $roles = $em->getRepository(Role::class)->findAll();
        
        return $this->render('@User/role/list.html.twig', [
            'roles' => $roles,
        ]);
    }
    
    #[Route('/role/{id}/delete', name: 'role_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em, Role $role): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $role->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', $this->translator->trans('role.invalid_csrf_token'));
            
            return $this->redirectToRoute('role_list');
        }
        
        try {
            $em->remove($role);
            $em->flush();
            
            $this->addFlash('success', $this->translator->trans('role.deleted_successfully'));
        } catch (ForeignKeyConstraintViolationException $exception) {
            $this->addFlash('danger', $this->translator->trans('role.delete_failed_in_use'));
        } catch (\Throwable $exception) {
            $this->addFlash('danger', $this->translator->trans('role.delete_failed'));
        }
        
        return $this->redirectToRoute('role_list');
    }

    private function validateParentRole($form, Role $role): void
    {
        $parent = $role->getParent();
        if ($parent === null) {
            return;
        }

        if ($parent === $role) {
            $form->get('parent')->addError(new FormError($this->translator->trans('role.parent.invalid_self')));
            return;
        }

        if ($this->isDescendant($parent, $role)) {
            $form->get('parent')->addError(new FormError($this->translator->trans('role.parent.invalid_cycle')));
        }
    }

    private function isDescendant(Role $candidateParent, Role $role, array $visitedRoleObjectIds = []): bool
    {
        $roleObjectId = spl_object_id($role);
        if (isset($visitedRoleObjectIds[$roleObjectId])) {
            return false;
        }

        $visitedRoleObjectIds[$roleObjectId] = true;
        foreach ($role->getChildren() as $child) {
            if ($child === $candidateParent || $this->isDescendant($candidateParent, $child, $visitedRoleObjectIds)) {
                return true;
            }
        }

        return false;
    }
}
