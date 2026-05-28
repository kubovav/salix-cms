# Salix CMS — Copilot Instructions

## Project Overview

Salix CMS is a lightweight content management system built with Symfony and Twig. Both the backend logic and the frontend rendering live in a single Symfony application at the repository root.

## Repository Layout

```
src/        — Symfony application code (Controllers, Entities, Repositories, etc.)
templates/  — Twig templates (frontend views)
migrations/ — Doctrine database migrations
config/     — Symfony configuration
public/     — Web root (index.php)
docker/     — Dockerfile, Nginx config, Supervisor config
```

Everything runs in a single `salix_app` Docker container managed by Supervisor:
- **Nginx** on port `8000` (host: `8010`) — serves the Symfony app
- **PHP-FPM** — executes Symfony

## Development Environment

**Agents and the VS Code editor are connected directly inside the `salix_app` Docker container via VS Code Remote Development (Dev Containers).** The workspace root is the project root inside the container. All terminal commands run directly in the container — there is no need to `docker exec` or prefix commands with `docker compose exec`. `php` and `composer` are available on `PATH` without any prefix.

## Application — Symfony 8 / Twig

### Stack
- PHP 8.4, Symfony 8.0, Doctrine ORM 3, MySQL
- Twig for all frontend rendering (no separate JS frontend)
- Symfony Security with role-based access (`ROLE_USER`, `ROLE_ADMIN`)

### Conventions
- Entities live in `src/Entity/` and use PHP 8 attributes for ORM mapping
- Controllers go in `src/Controller/` and use `#[Route]` attributes
- Twig templates go in `templates/` following the `templates/{controller}/action.html.twig` convention
- Repositories go in `src/Repository/` and extend `ServiceEntityRepository`
- Database migrations go in `migrations/` — always generate via `bin/console doctrine:migrations:diff`
- Admin routes use the prefix `/admin/` and require `ROLE_ADMIN`
- Public routes are open

### Admin UI — AdminLTE 4
- The admin section uses **AdminLTE 4** (Bootstrap 5-based theme) installed via npm (`node_modules/admin-lte`)
- AdminLTE CSS and JS are served via **Symfony AssetMapper** — do NOT use a CDN for AdminLTE
- All admin pages must extend `templates/admin/layout.html.twig`, never `base.html.twig`
- AdminLTE bundles Bootstrap 5 inside `adminlte.min.css` — do NOT add a separate Bootstrap link in admin templates
- Bootstrap Icons are loaded from CDN in the layout — use `<i class="bi bi-..."></i>` for icons
- Available Twig blocks in `admin/layout.html.twig`:
  - `title` — page `<title>` text
  - `page_title` — heading shown in the content header bar
  - `breadcrumbs` — `<li class="breadcrumb-item">` items appended after "Home"
  - `sidebar_menu` — additional `<li>` nav items added to the sidebar
  - `content` — main page body rendered inside `.container-fluid`
  - `stylesheets` / `javascripts` — extra per-page CSS/JS
- **Documentation:** https://adminlte.io/docs/4.x/ — components, layout options, widgets
- **Live examples:** `node_modules/admin-lte/dist/index.html` and `node_modules/admin-lte/dist/pages/`

### Security
- Admin controllers/routes are secured with `#[IsGranted('ROLE_ADMIN')]` or firewall config
- Passwords are hashed via `UserPasswordListener` (event listener pattern, not a subscriber)
- Users are created via `bin/console app:create-user`

### Current Entities
- **`ContentPage`** — slug (unique), title, content (text), published (bool), updatedAt
- **`User`** — email (unique), roles (array), password (hashed)

## Development Commands

```bash
# Start everything (run from the host, outside the container)
docker compose up --build -d

# All commands below run directly in the container terminal (no docker exec needed)

# Symfony console
php bin/console <command>

# Create a user
php bin/console app:create-user

# Run migrations
php bin/console doctrine:migrations:migrate

# Generate a migration after entity changes
php bin/console doctrine:migrations:diff
```

## What to Build Next

The project is a work in progress. Likely next steps:
- Authentication flow (login/logout with Symfony Security)
- Admin UI for managing `ContentPage` records (Twig-rendered forms)
- Richer content model (blocks, media, SEO fields)
- Production Docker setup

## General Guidelines

- Prefer Symfony attributes over YAML/XML configuration
- Templates live in `templates/` — follow the existing `base.html.twig` layout
- Follow existing naming patterns for new entities, controllers, and repositories
- There is no separate API layer or JavaScript frontend — all rendering is done server-side via Twig
