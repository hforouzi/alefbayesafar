<?php

namespace App\Modules\Default\Bootstrap;

use App\Modules\Default\Controller\DefaultController;
use App\Modules\Default\Controller\MenuCategoryController;
use App\Modules\Default\Controller\MenuController;
use App\Modules\Default\Controller\SettingController;
use App\Modules\Destination\Controller\AirportController;
use App\Modules\Destination\Controller\CityController;
use App\Modules\Destination\Controller\CountryController;
use App\Modules\Destination\Controller\DestinationImportController;
use App\Modules\Destination\Controller\DestinationLookupController;
use App\Modules\Destination\Controller\DistrictController;
use App\Modules\Destination\Controller\StateController;
use App\Modules\Flight\Controller\AirlineController;
use App\Modules\Flight\Controller\ExternalFlightTestController;
use App\Modules\Flight\Controller\FlightLookupController;
use App\Modules\Flight\Controller\FlightOfferController;
use App\Modules\Flight\Controller\FlightOfferLegController;
use App\Modules\Hotel\Controller\HotelAmenityController;
use App\Modules\Hotel\Controller\HotelController;
use App\Modules\Hotel\Controller\HotelImageController;
use App\Modules\Hotel\Controller\HotelOfferController;
use App\Modules\Hotel\Controller\HotelRateController;
use App\Modules\Hotel\Controller\HotelRoomTypeController;
use App\Modules\Hotel\Controller\HotelSearchController;
use App\Modules\SearchSource\Controller\SearchSourceController;
use App\Modules\User\Controller\PermissionController;
use App\Modules\User\Controller\RoleController;
use App\Modules\User\Controller\UserController;

final readonly class ApplicationBootstrapData
{
    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER'];
    }

    /**
     * @return array<string, string|null>
     */
    public function roleParents(): array
    {
        return [
            'ROLE_SUPER_ADMIN' => null,
            'ROLE_ADMIN' => 'ROLE_SUPER_ADMIN',
            'ROLE_USER' => 'ROLE_ADMIN',
        ];
    }

    /**
     * @return array<string, array{name: string, value: string}>
     */
    public function settings(): array
    {
        return [
            'site_name' => ['name' => 'site_name', 'value' => 'AlefBayeSafar'],
        ];
    }

    /**
     * @return array<string, array{name: string, label: string, icon: string, position: int, active: bool}>
     */
    public function menuCategories(): array
    {
        return [
            'main' => ['name' => 'Main', 'label' => 'navigation.main', 'icon' => 'solar:home-bold', 'position' => 10, 'active' => true],
            'catalog' => ['name' => 'Catalog', 'label' => 'destination.navigation.catalog', 'icon' => 'solar:map-bold', 'position' => 15, 'active' => true],
            'administration' => ['name' => 'Administration', 'label' => 'navigation.administration', 'icon' => 'solar:settings-bold', 'position' => 20, 'active' => true],
            'flight_commerce' => ['name' => 'Flight Commerce', 'label' => 'flight.navigation.commerce', 'icon' => 'solar:plane-bold', 'position' => 22, 'active' => true],
            'data_sources' => ['name' => 'Data Sources', 'label' => 'search_source.navigation.data_sources', 'icon' => 'solar:database-bold', 'position' => 25, 'active' => true],
            'settings' => ['name' => 'Settings', 'label' => 'navigation.settings', 'icon' => 'solar:tuning-bold', 'position' => 30, 'active' => true],
        ];
    }

    /**
     * @return array<string, array{label: string, route: string, controller: class-string, action: string}>
     */
    public function permissions(): array
    {
        return [
            'dashboard.view' => ['label' => 'dashboard.view', 'route' => 'app_dashboard', 'controller' => DefaultController::class, 'action' => 'dashboard'],
            'user.view' => ['label' => 'user.view', 'route' => 'user_list', 'controller' => UserController::class, 'action' => 'listUsers'],
            'user.create' => ['label' => 'user.create', 'route' => 'user_new', 'controller' => UserController::class, 'action' => 'newUser'],
            'user.update' => ['label' => 'user.update', 'route' => 'user_edit', 'controller' => UserController::class, 'action' => 'editUser'],
            'user.delete' => ['label' => 'user.delete', 'route' => 'user_delete', 'controller' => UserController::class, 'action' => 'deleteUser'],
            'role.view' => ['label' => 'role.view', 'route' => 'role_list', 'controller' => RoleController::class, 'action' => 'list'],
            'role.create' => ['label' => 'role.create', 'route' => 'role_new', 'controller' => RoleController::class, 'action' => 'new'],
            'role.update' => ['label' => 'role.update', 'route' => 'role_edit', 'controller' => RoleController::class, 'action' => 'edit'],
            'role.delete' => ['label' => 'role.delete', 'route' => 'role_delete', 'controller' => RoleController::class, 'action' => 'delete'],
            'permission.view' => ['label' => 'permission.view', 'route' => 'permission_list', 'controller' => PermissionController::class, 'action' => 'list'],
            'permission.create' => ['label' => 'permission.create', 'route' => 'permission_new', 'controller' => PermissionController::class, 'action' => 'new'],
            'permission.update' => ['label' => 'permission.update', 'route' => 'permission_edit', 'controller' => PermissionController::class, 'action' => 'edit'],
            'permission.delete' => ['label' => 'permission.delete', 'route' => 'permission_delete', 'controller' => PermissionController::class, 'action' => 'delete'],
            'menu.view' => ['label' => 'menu.view', 'route' => 'menu_index', 'controller' => MenuController::class, 'action' => 'index'],
            'menu.create' => ['label' => 'menu.create', 'route' => 'menu_new', 'controller' => MenuController::class, 'action' => 'new'],
            'menu.update' => ['label' => 'menu.update', 'route' => 'menu_edit', 'controller' => MenuController::class, 'action' => 'edit'],
            'menu.delete' => ['label' => 'menu.delete', 'route' => 'menu_delete', 'controller' => MenuController::class, 'action' => 'delete'],
            'menu_category.view' => ['label' => 'menu_category.view', 'route' => 'menu_category_index', 'controller' => MenuCategoryController::class, 'action' => 'index'],
            'menu_category.create' => ['label' => 'menu_category.create', 'route' => 'menu_category_new', 'controller' => MenuCategoryController::class, 'action' => 'new'],
            'menu_category.update' => ['label' => 'menu_category.update', 'route' => 'menu_category_edit', 'controller' => MenuCategoryController::class, 'action' => 'edit'],
            'menu_category.delete' => ['label' => 'menu_category.delete', 'route' => 'menu_category_delete', 'controller' => MenuCategoryController::class, 'action' => 'delete'],
            'settings.view' => ['label' => 'settings.view', 'route' => 'settings_list', 'controller' => SettingController::class, 'action' => 'list'],
            'settings.create' => ['label' => 'settings.create', 'route' => 'create_setting', 'controller' => SettingController::class, 'action' => 'create'],
            'settings.detail' => ['label' => 'settings.detail', 'route' => 'setting_by_name', 'controller' => SettingController::class, 'action' => 'show'],
            'settings.update' => ['label' => 'settings.update', 'route' => 'edit_setting', 'controller' => SettingController::class, 'action' => 'edit'],
            'destination.country.view' => ['label' => 'destination.country.view', 'route' => 'destination_country_index', 'controller' => CountryController::class, 'action' => 'index'],
            'destination.country.create' => ['label' => 'destination.country.create', 'route' => 'destination_country_new', 'controller' => CountryController::class, 'action' => 'new'],
            'destination.country.update' => ['label' => 'destination.country.update', 'route' => 'destination_country_edit', 'controller' => CountryController::class, 'action' => 'edit'],
            'destination.country.delete' => ['label' => 'destination.country.delete', 'route' => 'destination_country_delete', 'controller' => CountryController::class, 'action' => 'delete'],
            'destination.state.view' => ['label' => 'destination.state.view', 'route' => 'destination_state_index', 'controller' => StateController::class, 'action' => 'index'],
            'destination.state.create' => ['label' => 'destination.state.create', 'route' => 'destination_state_new', 'controller' => StateController::class, 'action' => 'new'],
            'destination.state.update' => ['label' => 'destination.state.update', 'route' => 'destination_state_edit', 'controller' => StateController::class, 'action' => 'edit'],
            'destination.state.delete' => ['label' => 'destination.state.delete', 'route' => 'destination_state_delete', 'controller' => StateController::class, 'action' => 'delete'],
            'destination.city.view' => ['label' => 'destination.city.view', 'route' => 'destination_city_index', 'controller' => CityController::class, 'action' => 'index'],
            'destination.city.create' => ['label' => 'destination.city.create', 'route' => 'destination_city_new', 'controller' => CityController::class, 'action' => 'new'],
            'destination.city.update' => ['label' => 'destination.city.update', 'route' => 'destination_city_edit', 'controller' => CityController::class, 'action' => 'edit'],
            'destination.city.delete' => ['label' => 'destination.city.delete', 'route' => 'destination_city_delete', 'controller' => CityController::class, 'action' => 'delete'],
            'destination.district.view' => ['label' => 'destination.district.view', 'route' => 'destination_district_index', 'controller' => DistrictController::class, 'action' => 'index'],
            'destination.district.create' => ['label' => 'destination.district.create', 'route' => 'destination_district_new', 'controller' => DistrictController::class, 'action' => 'new'],
            'destination.district.update' => ['label' => 'destination.district.update', 'route' => 'destination_district_edit', 'controller' => DistrictController::class, 'action' => 'edit'],
            'destination.district.delete' => ['label' => 'destination.district.delete', 'route' => 'destination_district_delete', 'controller' => DistrictController::class, 'action' => 'delete'],
            'destination.airport.view' => ['label' => 'destination.airport.view', 'route' => 'destination_airport_index', 'controller' => AirportController::class, 'action' => 'index'],
            'destination.airport.create' => ['label' => 'destination.airport.create', 'route' => 'destination_airport_new', 'controller' => AirportController::class, 'action' => 'new'],
            'destination.airport.update' => ['label' => 'destination.airport.update', 'route' => 'destination_airport_edit', 'controller' => AirportController::class, 'action' => 'edit'],
            'destination.airport.delete' => ['label' => 'destination.airport.delete', 'route' => 'destination_airport_delete', 'controller' => AirportController::class, 'action' => 'delete'],
            'destination.import.view' => ['label' => 'destination.import.view', 'route' => 'destination_import_index', 'controller' => DestinationImportController::class, 'action' => 'index'],
            'destination.import.bootstrap' => ['label' => 'destination.import.bootstrap', 'route' => 'destination_import_bootstrap', 'controller' => DestinationImportController::class, 'action' => 'bootstrap'],
            'destination.import.enrichment' => ['label' => 'destination.import.enrichment', 'route' => 'destination_import_enrichment_travel_areas', 'controller' => DestinationImportController::class, 'action' => 'refreshTravelAreas'],
            'destination.import.airports' => ['label' => 'destination.import.airports', 'route' => 'destination_import_enrichment_airports', 'controller' => DestinationImportController::class, 'action' => 'refreshAirports'],
            'destination.lookup.countries' => ['label' => 'destination.lookup.view', 'route' => 'destination_lookup_countries', 'controller' => DestinationLookupController::class, 'action' => 'countries'],
            'destination.lookup.states' => ['label' => 'destination.lookup.view', 'route' => 'destination_lookup_states', 'controller' => DestinationLookupController::class, 'action' => 'states'],
            'destination.lookup.cities' => ['label' => 'destination.lookup.view', 'route' => 'destination_lookup_cities', 'controller' => DestinationLookupController::class, 'action' => 'cities'],
            'destination.lookup.districts' => ['label' => 'destination.lookup.view', 'route' => 'destination_lookup_districts', 'controller' => DestinationLookupController::class, 'action' => 'districts'],
            'destination.lookup.airports' => ['label' => 'destination.lookup.view', 'route' => 'destination_lookup_airports', 'controller' => DestinationLookupController::class, 'action' => 'airports'],
            'search_source.view' => ['label' => 'search_source.view', 'route' => 'search_source_index', 'controller' => SearchSourceController::class, 'action' => 'index'],
            'search_source.detail' => ['label' => 'search_source.detail', 'route' => 'search_source_show', 'controller' => SearchSourceController::class, 'action' => 'show'],
            'search_source.create' => ['label' => 'search_source.create', 'route' => 'search_source_new', 'controller' => SearchSourceController::class, 'action' => 'new'],
            'search_source.update' => ['label' => 'search_source.update', 'route' => 'search_source_edit', 'controller' => SearchSourceController::class, 'action' => 'edit'],
            'search_source.delete' => ['label' => 'search_source.delete', 'route' => 'search_source_delete', 'controller' => SearchSourceController::class, 'action' => 'delete'],
            'hotel.view' => ['label' => 'hotel.view', 'route' => 'hotel_index', 'controller' => HotelController::class, 'action' => 'index'],
            'hotel.detail' => ['label' => 'hotel.detail', 'route' => 'hotel_show', 'controller' => HotelController::class, 'action' => 'show'],
            'hotel.create' => ['label' => 'hotel.create', 'route' => 'hotel_new', 'controller' => HotelController::class, 'action' => 'new'],
            'hotel.update' => ['label' => 'hotel.update', 'route' => 'hotel_edit', 'controller' => HotelController::class, 'action' => 'edit'],
            'hotel.delete' => ['label' => 'hotel.delete', 'route' => 'hotel_delete', 'controller' => HotelController::class, 'action' => 'delete'],
            'hotel.search' => ['label' => 'hotel.search', 'route' => 'hotel_search', 'controller' => HotelSearchController::class, 'action' => 'search'],
            'hotel.import' => ['label' => 'hotel.import', 'route' => 'hotel_search_import', 'controller' => HotelSearchController::class, 'action' => 'import'],
            'hotel.offer.search' => ['label' => 'hotel.offer.search', 'route' => 'hotel_offer_search', 'controller' => HotelOfferController::class, 'action' => 'search'],
            'hotel.image.create' => ['label' => 'hotel.image.create', 'route' => 'hotel_image_new', 'controller' => HotelImageController::class, 'action' => 'new'],
            'hotel.image.update' => ['label' => 'hotel.image.update', 'route' => 'hotel_image_edit', 'controller' => HotelImageController::class, 'action' => 'edit'],
            'hotel.image.delete' => ['label' => 'hotel.image.delete', 'route' => 'hotel_image_delete', 'controller' => HotelImageController::class, 'action' => 'delete'],
            'hotel.amenity.view' => ['label' => 'hotel.amenity.view', 'route' => 'hotel_amenity_index', 'controller' => HotelAmenityController::class, 'action' => 'index'],
            'hotel.amenity.create' => ['label' => 'hotel.amenity.create', 'route' => 'hotel_amenity_new', 'controller' => HotelAmenityController::class, 'action' => 'new'],
            'hotel.amenity.update' => ['label' => 'hotel.amenity.update', 'route' => 'hotel_amenity_edit', 'controller' => HotelAmenityController::class, 'action' => 'edit'],
            'hotel.amenity.delete' => ['label' => 'hotel.amenity.delete', 'route' => 'hotel_amenity_delete', 'controller' => HotelAmenityController::class, 'action' => 'delete'],
            'hotel.room_type.view' => ['label' => 'hotel.room_type.view', 'route' => 'hotel_room_type_index', 'controller' => HotelRoomTypeController::class, 'action' => 'index'],
            'hotel.room_type.create' => ['label' => 'hotel.room_type.create', 'route' => 'hotel_room_type_new', 'controller' => HotelRoomTypeController::class, 'action' => 'new'],
            'hotel.room_type.update' => ['label' => 'hotel.room_type.update', 'route' => 'hotel_room_type_edit', 'controller' => HotelRoomTypeController::class, 'action' => 'edit'],
            'hotel.room_type.toggle' => ['label' => 'hotel.room_type.toggle', 'route' => 'hotel_room_type_toggle', 'controller' => HotelRoomTypeController::class, 'action' => 'toggle'],
            'hotel.rate.view' => ['label' => 'hotel.rate.view', 'route' => 'hotel_rate_index', 'controller' => HotelRateController::class, 'action' => 'index'],
            'hotel.rate.create' => ['label' => 'hotel.rate.create', 'route' => 'hotel_rate_new', 'controller' => HotelRateController::class, 'action' => 'new'],
            'hotel.rate.update' => ['label' => 'hotel.rate.update', 'route' => 'hotel_rate_edit', 'controller' => HotelRateController::class, 'action' => 'edit'],
            'hotel.rate.toggle' => ['label' => 'hotel.rate.toggle', 'route' => 'hotel_rate_toggle', 'controller' => HotelRateController::class, 'action' => 'toggle'],
            'flight.airline.view' => ['label' => 'flight.airline.view', 'route' => 'flight_airline_index', 'controller' => AirlineController::class, 'action' => 'index'],
            'flight.airline.create' => ['label' => 'flight.airline.create', 'route' => 'flight_airline_new', 'controller' => AirlineController::class, 'action' => 'new'],
            'flight.airline.update' => ['label' => 'flight.airline.update', 'route' => 'flight_airline_edit', 'controller' => AirlineController::class, 'action' => 'edit'],
            'flight.airline.delete' => ['label' => 'flight.airline.delete', 'route' => 'flight_airline_delete', 'controller' => AirlineController::class, 'action' => 'delete'],
            'flight.lookup.airlines' => ['label' => 'flight.lookup.view', 'route' => 'flight_lookup_airlines', 'controller' => FlightLookupController::class, 'action' => 'airlines'],
            'flight.external_test.view' => ['label' => 'flight.external_test.view', 'route' => 'flight_external_test', 'controller' => ExternalFlightTestController::class, 'action' => '__invoke'],
            'flight.offer.view' => ['label' => 'flight.offer.view', 'route' => 'flight_offer_index', 'controller' => FlightOfferController::class, 'action' => 'index'],
            'flight.offer.create' => ['label' => 'flight.offer.create', 'route' => 'flight_offer_new', 'controller' => FlightOfferController::class, 'action' => 'new'],
            'flight.offer.update' => ['label' => 'flight.offer.update', 'route' => 'flight_offer_edit', 'controller' => FlightOfferController::class, 'action' => 'edit'],
            'flight.offer.toggle' => ['label' => 'flight.offer.toggle', 'route' => 'flight_offer_toggle', 'controller' => FlightOfferController::class, 'action' => 'toggle'],
            'flight.leg.view' => ['label' => 'flight.leg.view', 'route' => 'flight_offer_leg_index', 'controller' => FlightOfferLegController::class, 'action' => 'index'],
            'flight.leg.create' => ['label' => 'flight.leg.create', 'route' => 'flight_offer_leg_new', 'controller' => FlightOfferLegController::class, 'action' => 'new'],
            'flight.leg.update' => ['label' => 'flight.leg.update', 'route' => 'flight_offer_leg_edit', 'controller' => FlightOfferLegController::class, 'action' => 'edit'],
            'flight.leg.delete' => ['label' => 'flight.leg.delete', 'route' => 'flight_offer_leg_delete', 'controller' => FlightOfferLegController::class, 'action' => 'delete'],
        ];
    }

    /**
     * @return array<int, array{route: string, name: string, category: string, position: int, icon: string, permission: string}>
     */
    public function menus(): array
    {
        return [
            ['route' => 'app_dashboard', 'name' => 'navigation.dashboard', 'category' => 'main', 'position' => 10, 'icon' => 'solar:home-bold', 'permission' => 'dashboard.view'],
            ['route' => 'destination_country_index', 'name' => 'destination.navigation.countries', 'category' => 'catalog', 'position' => 10, 'icon' => 'solar:flag-bold', 'permission' => 'destination.country.view'],
            ['route' => 'destination_state_index', 'name' => 'destination.navigation.states', 'category' => 'catalog', 'position' => 20, 'icon' => 'solar:map-bold', 'permission' => 'destination.state.view'],
            ['route' => 'destination_city_index', 'name' => 'destination.navigation.cities', 'category' => 'catalog', 'position' => 30, 'icon' => 'solar:city-bold', 'permission' => 'destination.city.view'],
            ['route' => 'destination_district_index', 'name' => 'destination.navigation.districts', 'category' => 'catalog', 'position' => 40, 'icon' => 'solar:map-point-bold', 'permission' => 'destination.district.view'],
            ['route' => 'destination_airport_index', 'name' => 'destination.navigation.airports', 'category' => 'catalog', 'position' => 50, 'icon' => 'solar:plane-bold', 'permission' => 'destination.airport.view'],
            ['route' => 'hotel_index', 'name' => 'hotel.navigation.hotels', 'category' => 'catalog', 'position' => 70, 'icon' => 'solar:buildings-bold', 'permission' => 'hotel.view'],
            ['route' => 'hotel_amenity_index', 'name' => 'hotel.navigation.amenities', 'category' => 'catalog', 'position' => 80, 'icon' => 'solar:star-bold', 'permission' => 'hotel.amenity.view'],
            ['route' => 'destination_import_index', 'name' => 'destination.navigation.import', 'category' => 'catalog', 'position' => 90, 'icon' => 'solar:download-square-bold', 'permission' => 'destination.import.view'],
            ['route' => 'user_list', 'name' => 'navigation.users', 'category' => 'administration', 'position' => 10, 'icon' => 'solar:users-group-rounded-bold', 'permission' => 'user.view'],
            ['route' => 'role_list', 'name' => 'navigation.roles', 'category' => 'administration', 'position' => 20, 'icon' => 'solar:shield-user-bold', 'permission' => 'role.view'],
            ['route' => 'permission_list', 'name' => 'navigation.permissions', 'category' => 'administration', 'position' => 30, 'icon' => 'solar:key-bold', 'permission' => 'permission.view'],
            ['route' => 'flight_offer_index', 'name' => 'flight.navigation.own_deals', 'category' => 'flight_commerce', 'position' => 10, 'icon' => 'solar:plane-bold', 'permission' => 'flight.offer.view'],
            ['route' => 'flight_airline_index', 'name' => 'flight.navigation.airlines', 'category' => 'flight_commerce', 'position' => 20, 'icon' => 'solar:compass-bold', 'permission' => 'flight.airline.view'],
            ['route' => 'flight_external_test', 'name' => 'flight.navigation.external_test', 'category' => 'flight_commerce', 'position' => 30, 'icon' => 'solar:magnifer-bold', 'permission' => 'flight.external_test.view'],
            ['route' => 'search_source_index', 'name' => 'search_source.navigation.sources', 'category' => 'data_sources', 'position' => 10, 'icon' => 'solar:database-bold', 'permission' => 'search_source.view'],
            ['route' => 'settings_list', 'name' => 'navigation.settings', 'category' => 'settings', 'position' => 10, 'icon' => 'solar:tuning-bold', 'permission' => 'settings.view'],
            ['route' => 'menu_index', 'name' => 'navigation.menu', 'category' => 'settings', 'position' => 20, 'icon' => 'solar:list-bold', 'permission' => 'menu.view'],
            ['route' => 'menu_category_index', 'name' => 'navigation.menu_categories', 'category' => 'settings', 'position' => 30, 'icon' => 'solar:folder-bold', 'permission' => 'menu_category.view'],
        ];
    }
}
