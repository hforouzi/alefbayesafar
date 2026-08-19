<?php

namespace App\Modules\Default\Controller;

use App\Modules\Default\Entity\AppSetting;
use App\Modules\Default\Form\AppSettingType;
use App\Modules\Default\Repository\AppSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for managing application settings.
 */
class SettingController extends AbstractController
{
    /**
     * Create a new application setting.
     */
    #[Route('/setting/create', name: 'create_setting')]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $setting = new AppSetting();
        $form = $this->createForm(AppSettingType::class, $setting);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($setting);
            $entityManager->flush();
            
            return $this->redirectToRoute('settings_list');
        }
        
        return $this->render('@Default/settings/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Create Setting',
        ]);
    }
    
    /**
     * List all application settings.
     */
    #[Route('/settings', name: 'settings_list')]
    public function list(AppSettingRepository $appSettingRepository): Response
    {
        $settings = $appSettingRepository->findAllSettings();
        
        return $this->render('@Default/settings/list.html.twig', [
            'settings' => $settings,
        ]);
    }
    
    /**
     * Show a specific setting by name.
     */
    #[Route('/setting/{name}', name: 'setting_by_name')]
    public function show(string $name, AppSettingRepository $appSettingRepository): Response
    {
        $setting = $appSettingRepository->findOneByName($name);
        
        if (!$setting) {
            throw $this->createNotFoundException('No setting found with name ' . $name);
        }
        
        return $this->render('@Default/settings/show.html.twig', [
            'setting' => $setting,
        ]);
    }
    
    /**
     * Edit an existing application setting.
     */
    #[Route('/setting/edit/{id}', name: 'edit_setting')]
    public function edit(Request $request, AppSetting $setting, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AppSettingType::class, $setting);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            
            return $this->redirectToRoute('settings_list');
        }
        
        return $this->render('@Default/settings/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Edit Setting',
        ]);
    }
}
