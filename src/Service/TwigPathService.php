<?php
namespace App\Service;

use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigPathService
{
    private $twig;
    private $kernelProjectDir;
    
    public function __construct(Environment $twig, string $kernelProjectDir)
    {
        $this->twig = $twig;
        $this->kernelProjectDir = $kernelProjectDir;
    }
    
    public function addModulePaths(): void
    {
        $modulesPath = $this->kernelProjectDir . '/src/Modules';
        if (!is_dir($modulesPath)) {
            return;
        }
        
        $loader = $this->twig->getLoader();
        if (!$loader instanceof FilesystemLoader) {
            return;
        }
        
        $finder = new Finder();
        $finder->directories()->in($modulesPath);
        
        foreach ($finder as $moduleDir) {
            $moduleName = $moduleDir->getFilename();
            $viewsPath = $moduleDir->getRealPath() . '/Resources/views';
            
            if (is_dir($viewsPath)) {
                // Add path with namespace
                $loader->addPath($viewsPath, $moduleName);
                // Add path to default namespace
                $loader->addPath($viewsPath);
            }
        }
    }
}