<?php
namespace App\Modules\Default\Service;

use App\Modules\Default\Repository\AppSettingRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class TwigSettingService extends AbstractExtension implements GlobalsInterface
{
    private $settings;
    
    public function __construct(AppSettingRepository $settingRepository)
    {
        $this->settings = [];

        try {
            $settings = $settingRepository->findAll();
        } catch (\Throwable) {
            return;
        }

        foreach ($settings as $setting) {
            $this->settings[$setting->getName()] = $setting->getValue();
        }
    }
    
    public function getGlobals(): array
    {
        return [
            'app_settings' => $this->settings,
        ];
    }
}
