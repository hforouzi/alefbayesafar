<?php

namespace App\Modules\Auth\Controller;

use App\Modules\Auth\Service\CaptchaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CaptchaController extends AbstractController
{
    #[Route('/captcha', name: 'app_captcha')]
    public function captcha(
        CaptchaService $captchaService
    ): Response {
        $code = $captchaService->generateCaptcha();
        
        $image = imagecreatetruecolor(200, 50);
        $bgColor = imagecolorallocate($image, 245,
            245, 245);
        $textColor = imagecolorallocate($image, 0,
            0, 0);
        
        imagefilledrectangle($image, 0, 0, 200,
            50, $bgColor);
        
        // Add noise
        for ($i = 0; $i < 1000; $i++) {
            imagesetpixel($image, rand(0, 200),
                rand(0, 50), $textColor);
        }
        
        // Add text
        imagettftext(
            $image,
            24,
            rand(-10, 10),
            40,
            35,
            $textColor,
            __DIR__ . '/../Resources/fonts/Arial.ttf',
            $code
        );
        
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);
        
        return new Response($imageData, 200,
            ['Content-Type' => 'image/png']);
    }
}