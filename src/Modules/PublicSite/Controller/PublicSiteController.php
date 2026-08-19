<?php

declare(strict_types=1);

namespace App\Modules\PublicSite\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicSiteController extends AbstractController
{
    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('@PublicSite/home.html.twig');
    }

    #[Route('/build', name: 'public_build', methods: ['GET'])]
    public function build(): Response
    {
        return $this->render('@PublicSite/build.html.twig');
    }

    #[Route('/trips', name: 'public_trips', methods: ['GET'])]
    public function trips(): Response
    {
        return $this->render('@PublicSite/trips.html.twig');
    }
}
