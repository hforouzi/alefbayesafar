<?php

namespace App\Modules\User\Controller;

use App\Modules\User\Entity\UserEntity;
use App\Modules\User\Form\UserType;
use App\Shared\Security\RoleConstants;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/user/list', name: 'user_list')]
    public function listUsers(): Response
    {
        $this->denyUnlessUserManager();

        return $this->render('@User/user/list.html.twig', [
            'users' => $this->entityManager->getRepository(UserEntity::class)->findAll(),
        ]);
    }

    #[Route('/user/new', name: 'user_new')]
    public function newUser(Request $request): Response
    {
        $actor = $this->getCurrentUserEntityOrDeny();
        $this->denyUnlessUserManager($actor);

        $user = new UserEntity();
        $form = $this->createForm(UserType::class, $user, [
            'allowed_role_names' => null,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setCreatedBy($actor);

            $password = $form->get('password')->getData();
            if ($password) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('User created successfully.'));

            return $this->redirectToRoute('user_list');
        }

        return $this->render('@User/user/form.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/user/edit/{id}', name: 'user_edit')]
    public function editUser(Request $request, UserEntity $user): Response
    {
        $this->denyUnlessUserManager();

        $form = $this->createForm(UserType::class, $user, [
            'allowed_role_names' => null,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = $form->get('password')->getData();
            if ($password) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            }

            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('User updated successfully.'));

            return $this->redirectToRoute('user_list');
        }

        return $this->render('@User/user/form.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/user/delete/{id}', name: 'user_delete', methods: ['POST'])]
    public function deleteUser(Request $request, UserEntity $user): Response
    {
        $this->denyUnlessUserManager();

        if ($this->isCsrfTokenValid('delete' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('User deleted successfully.'));
        }

        return $this->redirectToRoute('user_list');
    }

    private function getCurrentUserEntityOrDeny(): UserEntity
    {
        $actor = $this->getUser();
        if ($actor instanceof UserEntity) {
            return $actor;
        }

        throw new AccessDeniedException();
    }

    private function denyUnlessUserManager(?UserEntity $actor = null): void
    {
        $actor ??= $this->getCurrentUserEntityOrDeny();

        if ($this->isGranted(RoleConstants::ROLE_SUPER_ADMIN) || $this->isGranted(RoleConstants::ROLE_ADMIN)) {
            return;
        }

        throw new AccessDeniedException($this->translator->trans('user.access.denied'));
    }
}
