# Salix Site

A website built on [Salix CMS](https://github.com/jakubvavro/salix) (`salix/cms-bundle`).
This project was created from `salix/skeleton`. Everything in this repository is yours:
site code goes in `src/`, templates in `templates/`, schema in `migrations/` — the CMS
lives in `vendor/` and is upgraded with Composer.

## Creating a new site

```bash
composer create-project salix/skeleton my-site
```

> **Until the packages are published to Packagist**, point Composer at your Salix
> checkout instead:
>
> ```bash
> COMPOSER_MIRROR_PATH_REPOS=1 composer create-project salix/skeleton my-site \
>     --repository='{"type":"path","url":"/path/to/salix/skeleton"}' \
>     --stability=dev --no-install
> cd my-site
> composer config repositories.salix '{"type":"path","url":"/path/to/salix/cms-bundle"}'
> composer install
> ```
>
> (`composer install` also installs the prebuilt admin UI into `public/admin/` via the
> `salix:admin:install` auto-script. Build it first in the Salix checkout with
> `npm run build` if `public/admin` ends up empty.)

## Development

```bash
docker compose up --build -d                    # app on :8000, MySQL, phpMyAdmin
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:create-user admin@example.com
```

- Public site: http://localhost:8000 — admin UI: http://localhost:8000/admin
- Site theme: edit `assets/styles/frontend.scss`, run `npm run dev` (watch) or `npm run build`
- Override CMS templates via `templates/bundles/SalixCmsBundle/…` (defaults render from
  `@SalixCms/frontend/…`)
- Site-specific schema: `php bin/console doctrine:migrations:diff` (goes to `migrations/`;
  CMS migrations ship inside the bundle on a separate namespace)

## Upgrading the CMS

```bash
composer update salix/cms-bundle
php bin/console doctrine:migrations:migrate
```

This also refreshes `public/admin/` with the admin UI matching the new CMS version.

## Production

- **Docker**: `cp .env.prod.example .env.prod`, fill in secrets, then
  `docker compose -f compose.prod.yaml --env-file .env.prod up -d --build`.
  TLS terminates in front of the stack (see the Salix README for the reverse-proxy
  setup with Caddy on a shared server).
- **Shared hosting (no shell)**: `bin/package-release` builds an upload-ready zip into
  `var/releases/`; upload, point the web root at `public/`, and finish via the `/install`
  web installer.
