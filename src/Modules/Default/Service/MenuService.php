<?php

namespace App\Modules\Default\Service;

use App\Modules\Default\Entity\Menu;
use App\Modules\Default\Repository\MenuRepository;
use App\Modules\User\Service\PermissionChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

class MenuService
{
    private const LEGACY_CATEGORY_ORDER = [
        'main' => 10,
        'administration' => 20,
        'settings' => 30,
        'apps' => 40,
        'other' => 999,
    ];

    public function __construct(
        private readonly MenuRepository $menuRepository,
        private readonly Security $security,
        private readonly PermissionChecker $permissionChecker,
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * @return Menu[]
     */
    public function getMenuItems(): array
    {
        if (!$this->security->getUser()) {
            return [];
        }

        $menus = $this->menuRepository->findBy([], [
            'position' => 'ASC',
            'id' => 'ASC',
        ]);

        return array_values(array_filter(
            $menus,
            fn (Menu $menu): bool => $this->permissionChecker->isMenuAccess($menu->getPermissions())
        ));
    }

    /**
     * Returns the allowed menu items as a plain tree of arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTreeMenuItems(): array
    {
        $menus = $this->getMenuItems();
        if ($menus === []) {
            return [];
        }

        $nodes = [];
        foreach ($menus as $menu) {
            $nodes[] = $this->mapMenuToArray($menu);
        }

        return $this->buildTree($nodes);
    }

    /**
     * Backward-compatible alias for legacy callers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMenuTreeItems(): array
    {
        return $this->getTreeMenuItems();
    }

    /**
     * Returns grouped sidebar menu data.
     *
     * @return array<string, array{normalized_category: string, category_label: string, category_position: int, category_active: bool, items: array<int, array<string, mixed>>}>
     */
    public function getSidebarMenu(): array
    {
        $treeItems = $this->filterInactiveCategories($this->getTreeMenuItems());
        if ($treeItems === []) {
            return [];
        }

        $grouped = [];
        foreach ($treeItems as $item) {
            $categoryKey = (string) $item['normalized_category'];

            if (!isset($grouped[$categoryKey])) {
                $grouped[$categoryKey] = [
                    'normalized_category' => $categoryKey,
                    'category_label' => (string) $item['category_label'],
                    'category_position' => (int) $item['category_position'],
                    'category_active' => (bool) $item['category_active'],
                    'items' => [],
                ];
            }

            $grouped[$categoryKey]['items'][] = $item;
            $grouped[$categoryKey]['category_position'] = min(
                $grouped[$categoryKey]['category_position'],
                (int) $item['category_position']
            );
        }

        uasort($grouped, function (array $left, array $right): int {
            if ($left['category_position'] !== $right['category_position']) {
                return $left['category_position'] <=> $right['category_position'];
            }

            $labelComparison = strcmp($left['category_label'], $right['category_label']);
            if ($labelComparison !== 0) {
                return $labelComparison;
            }

            return strcmp($left['normalized_category'], $right['normalized_category']);
        });

        return $grouped;
    }

    /**
     * Returns a flat list with full hierarchical names for admin tables.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFlatMenuItemsWithHierarchy(): array
    {
        $menus = $this->getMenuItems();
        if ($menus === []) {
            return [];
        }

        $menuMap = [];
        foreach ($menus as $menu) {
            $menuMap[$menu->getId()] = $menu;
        }

        $processedMenus = [];
        foreach ($menus as $menu) {
            $menuData = $this->mapMenuToArray($menu);
            $menuData['full_name'] = $this->getMenuFullPath($menu, $menuMap);
            $processedMenus[] = $menuData;
        }

        usort($processedMenus, function (array $left, array $right): int {
            $leftOrder = $this->getCategorySortOrder($left['normalized_category'], (int) $left['category_position']);
            $rightOrder = $this->getCategorySortOrder($right['normalized_category'], (int) $right['category_position']);

            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            if ($left['position'] !== $right['position']) {
                return $left['position'] <=> $right['position'];
            }

            $nameComparison = strcmp((string) $left['full_name'], (string) $right['full_name']);
            if ($nameComparison !== 0) {
                return $nameComparison;
            }

            return $left['id'] <=> $right['id'];
        });

        return $processedMenus;
    }

    private function mapMenuToArray(Menu $menu): array
    {
        $routeAnalysis = $this->analyzeRoute($menu->getRoute());
        $categoryData = $this->resolveCategoryData($menu);
        $parent = $menu->getParent();

        return [
            'id' => $menu->getId(),
            'name' => $menu->getName(),
            'route' => $menu->getRoute(),
            'icon' => $menu->getIcon(),
            'category' => $categoryData['category'],
            'normalized_category' => $categoryData['normalized_category'],
            'category_label' => $categoryData['category_label'],
            'category_position' => $categoryData['category_position'],
            'category_active' => $categoryData['category_active'],
            'menu_category_id' => $categoryData['menu_category_id'],
            'menu_category_code' => $categoryData['menu_category_code'],
            'position' => $menu->getPosition(),
            'parent_id' => $parent ? $parent->getId() : null,
            'children' => [],
            'is_linkable' => $routeAnalysis['is_linkable'],
            'route_requires_parameters' => $routeAnalysis['route_requires_parameters'],
            'route_error' => $routeAnalysis['route_error'],
        ];
    }

    private function resolveCategoryData(Menu $menu): array
    {
        $menuCategory = $menu->getMenuCategory();
        if ($menuCategory !== null) {
            $code = mb_strtolower(trim($menuCategory->getCode()));
            $normalized = $code !== '' ? $code : 'other';
            $label = $menuCategory->getLabelKey() ?: $menuCategory->getName();

            return [
                'category' => $menu->getCategory(),
                'normalized_category' => $normalized,
                'category_label' => $label,
                'category_position' => $menuCategory->getPosition(),
                'category_active' => $menuCategory->isActive(),
                'menu_category_id' => $menuCategory->getId(),
                'menu_category_code' => $menuCategory->getCode(),
            ];
        }

        $normalized = $this->normalizeCategory($menu->getCategory());

        return [
            'category' => $menu->getCategory(),
            'normalized_category' => $normalized,
            'category_label' => 'navigation.' . $normalized,
            'category_position' => $this->getLegacyCategoryPosition($normalized),
            'category_active' => true,
            'menu_category_id' => null,
            'menu_category_code' => null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(array $items, ?int $parentId = null, array $visited = []): array
    {
        $branch = [];

        foreach ($items as $item) {
            if (($item['parent_id'] ?? null) !== $parentId) {
                continue;
            }

            $itemId = (int) $item['id'];
            if (in_array($itemId, $visited, true)) {
                continue;
            }

            $item['children'] = $this->buildTree($items, $itemId, [...$visited, $itemId]);
            $branch[] = $item;
        }

        return $branch;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function filterInactiveCategories(array $items): array
    {
        $filtered = [];

        foreach ($items as $item) {
            if (!($item['category_active'] ?? true)) {
                continue;
            }

            if (!empty($item['children'])) {
                $item['children'] = $this->filterInactiveCategories($item['children']);
            }

            $filtered[] = $item;
        }

        return $filtered;
    }

    private function normalizeCategory(?string $category): string
    {
        $normalized = mb_strtolower(trim((string) $category));

        return match ($normalized) {
            '', 'dashboard', 'main' => 'main',
            'administration', 'admin', 'users' => 'administration',
            'setting', 'settings' => 'settings',
            'app', 'apps' => 'apps',
            default => 'other',
        };
    }

    private function getLegacyCategoryPosition(string $category): int
    {
        return self::LEGACY_CATEGORY_ORDER[$category] ?? self::LEGACY_CATEGORY_ORDER['other'];
    }

    private function getCategorySortOrder(string $category, int $categoryPosition): int
    {
        return $categoryPosition;
    }

    /**
     * @return array{is_linkable: bool, route_requires_parameters: bool, route_error: string|null}
     */
    private function analyzeRoute(?string $routeName): array
    {
        $routeName = trim((string) $routeName);
        if ($routeName === '' || $routeName === '#') {
            return [
                'is_linkable' => false,
                'route_requires_parameters' => false,
                'route_error' => 'empty_route',
            ];
        }
        
//        if (str_contains($routeName, '\\') || str_contains($routeName, '::')) {
//            return [
//                'is_linkable' => false,
//                'route_requires_parameters' => false,
//                'route_error' => 'controller_action_not_route_name',
//            ];
//        }

        $routeCollection = $this->router->getRouteCollection();
        $route = $routeCollection?->get($routeName);
        if (!$route instanceof Route) {
            return [
                'is_linkable' => false,
                'route_requires_parameters' => false,
                'route_error' => 'route_not_found',
            ];
        }

        $defaults = $route->getDefaults();
        $requiredVariables = [];
        foreach ($route->compile()->getVariables() as $variable) {
            if (!array_key_exists($variable, $defaults)) {
                $requiredVariables[] = $variable;
            }
        }

        if ($requiredVariables !== []) {
            return [
                'is_linkable' => false,
                'route_requires_parameters' => true,
                'route_error' => 'route_requires_parameters',
            ];
        }

        return [
            'is_linkable' => true,
            'route_requires_parameters' => false,
            'route_error' => null,
        ];
    }

    /**
     * @param array<int, Menu> $menuMap
     */
    private function getMenuFullPath(Menu $menu, array $menuMap): string
    {
        $path = [$menu->getName()];
        $visited = [$menu->getId() => true];
        $current = $menu;

        while ($current->getParent()) {
            $parentId = $current->getParent()->getId();
            if (isset($visited[$parentId])) {
                break;
            }

            if (!isset($menuMap[$parentId])) {
                break;
            }

            $parentMenu = $menuMap[$parentId];
            array_unshift($path, $parentMenu->getName());
            $visited[$parentId] = true;
            $current = $parentMenu;
        }

        return implode(' > ', $path);
    }
}
