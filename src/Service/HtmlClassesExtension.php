<?php
namespace App\Service;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class HtmlClassesExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('html_classes', [$this, 'htmlClasses']),
        ];
    }
    
    public function htmlClasses(string $baseClass, array $additionalClasses = []): string
    {
        // ترکیب کلاس‌ها
        $classes = array_merge([$baseClass], array_keys($additionalClasses, true));
        return implode(' ', $classes);
    }
}
