# AlefBayeSafar

AlefBayeSafar is a Symfony 7.2 travel planning application derived from `hforouzi/SymfonyAppTemplate`.

The inherited template infrastructure remains the foundation: database-backed users, roles, permissions, dynamic menus, settings, Twig admin layouts, localization, Messenger, HttpClient, Mailer baseline, AssetMapper, Stimulus, PHPUnit, PHPStan, and coding-standard tooling.

The Lovable MVP is a public UI/UX reference only. React, TanStack, Lovable auth, PostgreSQL/RLS, Firecrawl and AI backend architecture are not part of this Symfony foundation phase.

## Requirements

- PHP 8.2+
- Composer
- MySQL or MariaDB
- Symfony CLI optional
- Node.js only if you choose to build npm-managed assets

## Fresh Installation / Development Setup

```powershell
git clone <repository-url> alefbayesafar
cd alefbayesafar
composer install
```

### Environment

Copy `.env.example` to your local override if needed and set:

```dotenv
APP_SECRET=change-me
DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/alefbayesafar?serverVersion=mariadb-10.8.3&charset=utf8mb4"
MAILER_DSN=null://null
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```

Use a project-specific database name. Do not commit real credentials.

### Database and System Bootstrap

```powershell
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
php bin/console app:bootstrap
php bin/console app:create-user --email=admin@example.com --password=change-me --role=ROLE_SUPER_ADMIN
```

`app:bootstrap` creates required system data only:

- baseline roles: `ROLE_SUPER_ADMIN`, `ROLE_ADMIN`, `ROLE_USER`;
- baseline permissions and controller-action mappings for current admin modules;
- dynamic admin menu categories and menu entries;
- required default settings such as `site_name`.

The command is idempotent and safe to run repeatedly:

```powershell
php bin/console app:bootstrap
php bin/console app:bootstrap --dry-run
```

`app:bootstrap` intentionally does not create business/demo data, live SearchSource rows, hotels, flights, offers, users, credentials, API keys, bookings, tours, or provider-specific configuration.

To create an admin user, keep using the existing user command:

```powershell
php bin/console app:create-user --email=admin@example.com --password=change-me --role=ROLE_SUPER_ADMIN
```

The older `app:security:bootstrap-super-admin --email=...` command still exists for template compatibility and can create/promote a Super Admin interactively, but fresh AlefBayeSafar setup should prefer `app:bootstrap` for system data plus `app:create-user` for credentials.

### Optional Reference Catalogue Data

Destination catalogue data is separate from system bootstrap. Use the existing import commands when you need local reference data:

```powershell
php bin/console app:destination:import --target=country --provider=geonames
php bin/console app:destination:import --target=city --provider=geonames --country=TR
php bin/console app:destination:import --target=airport --provider=ourairports --country=TR
```

Search Sources are admin-configured environment/provider records. Create them through Admin or the Search Source CRUD; do not put API keys, tokens, cookies, or private URLs in fixtures or bootstrap data.

## Search UX Guardrail

Interactive searches and admin test actions must not render a silent empty state after submission. Every search-style action should show what was attempted, which sources or services were considered, whether the action succeeded, how many records were found or rejected, any provider/request failure, and the next useful state such as no results, no eligible sources, or invalid request.

This rule applies to provider/debug tooling first and should guide later Hotel, Flight, Tour, and Trip Planner search surfaces.

## Flight External Provider Notes

Flight external search is provider-neutral behind `FlightOfferProviderInterface`. The current Firecrawl provider can query admin-configured SearchSource URL templates and normalize only explicit flight offer facts into external `FlightOffer` snapshots.

Real verification was performed against Booking Flights and Kiwi public pages. Firecrawl could fetch both sources, but both returned `NO_DATA` because critical structured facts such as price, airline, flight number, times, or the requested route could not be reliably verified against the fetched source content. The system intentionally rejects unsupported or hallucinated structured extraction instead of persisting unsafe prices.

This does not block Own Flight Deals or `FlightPricingResolver`. A future source, scraper, affiliate integration, or proper API can be added behind the same provider contract without changing the Flight domain model.

## Assets

This template uses Symfony AssetMapper/importmap by default.

```powershell
php bin/console importmap:install
```

If npm assets are added later:

```powershell
npm install
npm run build
```

## Run Locally

```powershell
symfony server:start
```

or use any local web server pointed at `public/`.

Key browser paths:

- `/`
- `/build`
- `/trips`
- `/login`
- `/dashboard`
- `/user/list`
- `/roles`
- `/permissions`
- `/settings`
- `/admin/menu/`
- `/admin/menu-categories/`

## Checks

```powershell
composer validate
php bin/console cache:clear
php bin/console lint:yaml config
php bin/console lint:twig templates
php bin/console doctrine:schema:validate
php bin/phpunit
vendor\bin\phpstan analyse
```
