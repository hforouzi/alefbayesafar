<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Application Kernel with support for modular architecture.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    
    /**
     * Configures the container and loads module-specific configurations.
     */
    protected function configureContainer(ContainerConfigurator $container): void
    {
        $modulesDir = $this->getProjectDir() . '/src/Modules';
        $modules = array_filter(glob($modulesDir . '/*'), 'is_dir');
        $migrationsPaths = [
            'App\Migrations' => $this->getProjectDir() . '/migrations',
        ];
        $translationPaths = [];
        
        // Load module-specific configurations
        foreach ($modules as $module) {
            $configDir = $module . '/Config';
            $moduleName = basename($module);
            $migrationsPath = "$module/Resources/Migrations";
            $translationsPath = "$module/Resources/translations";
            
            // Register module migrations
            if (is_dir($migrationsPath)) {
                $migrationsPaths["App\\Modules\\$moduleName\\Resources\\Migrations"] = $migrationsPath;
            }

            // Register module translations for the shared translator configuration.
            if (is_dir($translationsPath)) {
                $translationPaths[] = $translationsPath;
            }
            
            // Load module services configuration
            if (is_dir($configDir)) {
                $servicesFile = $configDir . '/services.yaml';
                if (file_exists($servicesFile)) {
                    $container->import('Modules/' . $moduleName . '/Config/services.yaml');
                }
            }
            
            // Load module events configuration
            $eventsFile = $configDir . '/events.yaml';
            if (file_exists($eventsFile)) {
                $container->import($eventsFile);
            }
        }
        
        // Configure Doctrine migrations
        $container->extension('doctrine_migrations', [
            'migrations_paths' => $migrationsPaths,
        ]);

        if ($translationPaths) {
            $container->extension('framework', [
                'translator' => [
                    'paths' => array_values(array_unique($translationPaths)),
                ],
            ]);
        }
        
        // Load global configurations
        $container->import('../config/{packages}/*.yaml');
        $container->import('../config/{packages}/' . $this->environment . '/*.yaml');
        
        if (is_file(dirname(__DIR__) . '/config/services.yaml')) {
            $container->import('../config/services.yaml');
        } else {
            $container->import('../config/{services}.php');
        }
    }
    
    /**
     * Configures the routing for the application.
     */
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('../config/{routes}/' . $this->environment . '/*.yaml');
        $routes->import('../config/{routes}/*.yaml');
        
        if (is_file(dirname(__DIR__) . '/config/routes.yaml')) {
            $routes->import('../config/routes.yaml');
        } else {
            $routes->import('../config/{routes}.php');
        }
    }
}
