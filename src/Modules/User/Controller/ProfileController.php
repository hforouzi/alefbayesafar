<?php
namespace App\Modules\User\Controller;
use App\Modules\User\Form\ChangePasswordType;
use App\Modules\User\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
 {
    
    #[Route('/profile/change-password', name: 'app_profile_change_password')]
    public function changePassword(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof UserEntity) {
                throw $this->createAccessDeniedException();
            }

            $data = $form->getData();
            
            // Verify current password
            if (!$passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
                $this->addFlash('error', 'Current password is incorrect');
                return $this->redirectToRoute('app_profile_change_password');
            }
            
            // Hash the new password
            $hashedPassword = $passwordHasher->hashPassword($user, $data['newPassword']);
            $user->setPassword($hashedPassword);
            
            $em->persist($user);
            $em->flush();
            
            $this->addFlash('success', 'Password has been changed successfully');
            return $this->redirectToRoute('app_dashboard');
        }
        
        return $this->render('@User/profile/change_password.html.twig', [
            'form' => $form->createView()
        ]);
    }
    
}
