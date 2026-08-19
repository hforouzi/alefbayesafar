<?php

namespace App\Modules\Auth\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class CaptchaService
{
    private const CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const LENGTH = 6;
    
    public function __construct(
        private RequestStack $requestStack
    ) {
    }
    
    public function generateCaptcha(): string
    {
        $code = '';
        $max = strlen(self::CHARS) - 1;
        
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::CHARS[random_int(0,
                $max)];
        }
        
        $this->requestStack->getSession()->set('captcha_code',
            $code);
        return $code;
    }
    
    public function validateCaptcha(
        string $userInput
    ): bool {
        $correctCode = $this->requestStack->getSession()->get('captcha_code');
        $this->requestStack->getSession()->remove('captcha_code');
        
        return $correctCode && strtoupper($userInput) === strtoupper($correctCode);
    }
}
