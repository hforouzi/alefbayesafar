<?php

namespace App\Service;

/**
 * Service for managing application modules.
 */
class ModuleManager
{
    private string $modulesPath;
    
    public function __construct(string $projectDir)
    {
        $this->modulesPath = $projectDir . '/src/Modules';
    }
    
    /**
     * Get all available modules in the application.
     *
     * @return array<string> Array of module directory paths
     */
    public function getModules(): array
    {
        return array_filter(glob($this->modulesPath . '/*'), 'is_dir');
    }
    
    /**
     * Check if a specific module is enabled.
     *
     * @param string $moduleName The name of the module to check
     * @return bool True if module is enabled, false otherwise
     */
    public function isModuleEnabled(string $moduleName): bool
    {
        // TODO: Implement module enable/disable logic (e.g., check database or config)
        return true;
    }
}
