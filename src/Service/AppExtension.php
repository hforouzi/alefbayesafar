<?php

namespace App\Service;

use App\Modules\Default\Service\MenuService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigTest;

class AppExtension extends AbstractExtension
{
    public function __construct(private readonly MenuService $menuService)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_menu', [$this->menuService, 'getMenuItems']),
            new TwigFunction('get_tree_menu', [$this->menuService, 'getTreeMenuItems']),
            new TwigFunction('get_sidebar_menu', [$this->menuService, 'getSidebarMenu']),
            new TwigFunction('get_flat_menu', [$this->menuService, 'getFlatMenuItemsWithHierarchy']),
            new TwigFunction('get_menu_tree', [$this->menuService, 'getTreeMenuItems']),
            new TwigFunction('get_flat_menus', [$this->menuService, 'getFlatMenuItemsWithHierarchy']),
        ];
    }

    public function getTests(): array
    {
        return [
            new TwigTest('numeric', [$this, 'isNumeric']),
            new TwigTest('date', [$this, 'isDate']),
        ];
    }

    public function isNumeric($value): bool
    {
        return is_numeric($value);
    }

    public function isDate($value): bool
    {
        return $value instanceof \DateTimeInterface;
    }
}
