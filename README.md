# Symfony App Template

Reusable Symfony 7.2 browser-admin template with database-backed users, roles, permissions, dynamic menus, settings, Twig admin layouts, localization, Messenger, HttpClient, Mailer baseline, AssetMapper, Stimulus, PHPUnit, PHPStan, and coding-standard tooling.

## Requirements

- PHP 8.2+
- Composer
- MySQL or MariaDB
- Symfony CLI optional
- Node.js only if you choose to build npm-managed assets

## Installation

```powershell
composer install
```

## Environment

Copy `.env.example` to your local override if needed and set:

```dotenv
APP_SECRET=change-me
DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/symfony_app?serverVersion=mariadb-10.8.3&charset=utf8mb4"
MAILER_DSN=null://null
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```

Use a project-specific database name. Do not point this template at another application's database.

## Database

```powershell
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
php bin/console app:security:bootstrap-super-admin --email=admin@example.com
php bin/console app:menu:seed-baseline
```

The bootstrap command creates `ROLE_SUPER_ADMIN`, `ROLE_ADMIN`, `ROLE_USER`, generic baseline permissions, and the first Super Admin user when the email does not exist. When run interactively with `--email`, it prompts for the initial password if `--password` is not supplied. Use a unique local password and rotate it before deploying a derived application.

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
