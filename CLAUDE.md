## Project Overview

Salix CMS is a lightweight content management system built with Symfony and Twig. The CMS itself is the **`salix/cms-bundle` Symfony bundle** in `cms-bundle/`, consumed by the application shell at the repository root through a Composer **path repository** (`repositories` in `composer.json`). This monorepo is both the bundle's development home and a runnable Salix application.

## Repository Layout

```
cms-bundle/             — the CMS: salix/cms-bundle (version 0.2.x, namespace Salix\Cms\)
  src/                  — bundle code: Entities, Controllers, Repositories, Security, …
  templates/            — frontend + installer Twig templates (@SalixCms namespace)
  migrations/           — CMS schema migrations (Salix\Cms\Migrations, registered by the bundle)
  admin-app/            — Angular admin SPA (source)
  config/services.yaml  — bundle service definitions
src/        — application shell code (site-level; only the Kernel in this repo)
templates/  — application-level Twig (overrides via templates/bundles/SalixCmsBundle/)
migrations/ — application-level migrations (DoctrineMigrations namespace)
config/     — Symfony application configuration
public/     — Web root (index.php)
docker/     — Dockerfile, Nginx config, Supervisor config
```

Everything runs in a single `salix_app` Docker container managed by Supervisor:
- **Nginx** on port `8000` — serves the Symfony app
- **PHP-FPM** — executes Symfony

`docker/Dockerfile` is multi-stage: the `dev` target bind-mounts the source (used by the committed `compose.yaml`), the `prod` target bakes code, `--no-dev` vendors, the built admin SPA, compiled asset-mapper assets, and a warmed cache into an immutable image (used by `compose.prod.yaml` + `.env.prod`). Personal dev tweaks (ports, mounts) belong in a git-ignored `compose.override.yaml`, never in `compose.yaml`.

## Development Environment

**Agents and the VS Code editor are connected directly inside the `salix_app` Docker container via VS Code Remote Development (Dev Containers).** The workspace root is the project root inside the container. All terminal commands run directly in the container — there is no need to `docker exec` or prefix commands with `docker compose exec`. `php` and `composer` are available on `PATH` without any prefix.

## Application — Symfony 8 / Twig

### Stack
- PHP 8.4, Symfony 8.0, Doctrine ORM 3, MySQL
- Twig for all frontend rendering (no separate JS frontend)
- Symfony Security with role-based access (`ROLE_USER`, `ROLE_ADMIN`)

### Conventions
- **CMS code goes in the bundle** (`cms-bundle/src/`, namespace `Salix\Cms\`); the root `src/` is the application shell (site-level code, empty here apart from the Kernel)
- Entities live in `cms-bundle/src/Entity/` and use PHP 8 attributes for ORM mapping
- Controllers go in `cms-bundle/src/Controller/` and use `#[Route]` attributes (imported by `config/routes.yaml` via `@SalixCmsBundle/src/Controller/`)
- CMS Twig templates go in `cms-bundle/templates/` and render via the `@SalixCms/...` namespace
- Repositories go in `cms-bundle/src/Repository/` and extend `ServiceEntityRepository`
- CMS migrations go in `cms-bundle/migrations/` — generate via `bin/console doctrine:migrations:diff --namespace='Salix\Cms\Migrations'`. Application-level migrations use plain `doctrine:migrations:diff` into `migrations/`.
- Bundle services are autowired via `cms-bundle/config/services.yaml`; Doctrine/API Platform/migrations wiring is prepended by `SalixCmsBundle::prependExtension()` — Doctrine `auto_mapping` stays **false** (mappings are explicit; the auto-mapping probe also breaks on case-insensitive macOS bind mounts, see `src/Config` vs `src/config`)
- The admin UI is a separate **Angular SPA** (see below); the Symfony app exposes a JSON **API** for it under `/api`
- Public (frontend) routes are open and rendered server-side with Twig

### Admin UI — Angular SPA + API Platform
- The admin section is an **Angular standalone app** living in `cms-bundle/admin-app/`, built into `public/admin/` and served by Nginx at `/admin` (same-origin).
- The backend exposes a REST API via **API Platform** under `/api` (config in `config/packages/api_platform.yaml`). Entities are exposed with `#[ApiResource]` + serialization groups + validation constraints. API responses are **plain JSON** (`formats: json`), so collections are plain arrays.
- **Auth is session-cookie based, same-origin** (reuses Symfony Security — no JWT). The `api` firewall uses `json_login` (`POST /api/auth/login` with `{email, password}`), `POST /api/auth/logout`, and `GET /api/auth/me`. Custom handlers return JSON instead of HTML redirects (`cms-bundle/src/Security/`).
- API Platform defaults: `stateless: false` (session auth), `pagination_enabled: false`. Writes use **POST to create / PATCH to update** (no PUT); PATCH bodies are sent as `application/merge-patch+json` and populate the managed entity (so `UniqueEntity` correctly excludes the current record).
- **After adding/renaming an `#[ApiResource]` property or serialization group, run `php bin/console cache:clear`** — even in dev. API Platform caches property-name/metadata pools under `var/cache` and does **not** auto-invalidate them, so a newly added writable field is silently dropped on write (the request carries it, but it persists as `null`) until the cache is cleared.
- Custom API endpoints live in `cms-bundle/src/Controller/Api/` (uploads, block reorder, settings, meta).
- **Frontend dev workflow**: `npm install` then `npm run dev` (from the project root) — watches frontend SCSS and runs `ng serve` on port `4200` (uses `proxy.conf.json` to proxy `/api` + `/uploads` to `http://localhost:8000`). `npm run build` production-builds the SPA into `public/admin/` (`baseHref: /admin/`) — that is the **only** thing that writes `public/admin/`; there is no watch-build.
- Angular stack: standalone components, zoneless change detection, Reactive Forms, **ng-bootstrap** (Bootstrap 5), **bootstrap-icons**, **ngx-quill** (rich text), **@angular/cdk** drag-drop (block reorder). Bootstrap is compiled from SCSS in `src/styles.scss`; vendor CSS (icons, Quill) is added via `angular.json` `styles`.
- The HTTP interceptor (`src/app/core/auth.interceptor.ts`) sends `withCredentials: true` + `Accept: application/json` and redirects to `/login` on 401.

#### Angular component conventions

- **Component lifecycle:** keep the `constructor` for dependency injection only (`inject(...)` field initializers). Do all initial data loading and other startup side effects in `ngOnInit` (`implements OnInit`), not the constructor.
- **Subscription cleanup is mandatory for every `.subscribe()`.** Pipe `takeUntilDestroyed(this.destroyRef)` onto the observable before subscribing, with `private readonly destroyRef = inject(DestroyRef);` as a class field. Apply it even to one-shot `HttpClient` calls: if the component is destroyed before the response arrives, this unsubscribes, aborts the in-flight request, and prevents the `next`/`error` callback from running against a dead component. Do **not** hand-roll `Subscription` containers + manual `OnDestroy`/`DestroyRef.onDestroy()` unsubscribe — `takeUntilDestroyed` is the standard because it can't be forgotten on one call while being applied to another.

  ```typescript
  private readonly service = inject(SomeService);
  private readonly destroyRef = inject(DestroyRef);

  ngOnInit(): void {
    this.service.list()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((items) => this.items.set(items));
  }
  ```

- **State is held in signals** (`signal`, `computed`); derive composite flags with `computed` (e.g. an overall `loading` from per-request loading signals) rather than tracking them by hand. The app uses zoneless change detection, so never rely on Zone.js to pick up mutations made outside signals.

- **Inject services into a field named after the service** in full, lower-camelCased: `inject(MenuService)` → `menuService`, `inject(ArticleService)` → `articleService` — never abbreviated (`menu`, `articles`). This keeps usages self-documenting and consistent across components.

- **HTTP/IRI concerns belong in the service, not the component.** Forms and templates work with **raw values** (entity ids, plain strings); the service owns the API base paths and all IRI parsing/building, kept **private**. Components never hand-build `/api/...` strings, read `entity['@id']`, or pass IRIs around. Concretely, a `*Service` exposes a pair of mapping methods and keeps the IRI work internal:
  - `toFormValue(entity)` maps a stored entity (whose relations come back as plain-JSON IRI strings or nested objects) to the raw form value — relation fields become bare ids. The component just does `form.patchValue(service.toFormValue(item))`.
  - `buildPayload(formValue)` maps the raw form value to the write body — bare ids become IRIs (`${base}/${id}`), empties become `null`. The component just does `service.create/update(service.buildPayload(form.getRawValue()))`.
  - `<select>` options bind raw ids (`[value]="entity.id"`), not IRIs.
  - The shared `idFromRef(ref)` helper in `core/iri.ts` (which normalizes a relation that comes back as either an IRI string or a nested object to a bare id) exists for services to use **internally** when mapping stored entities to form values. Do not call it from components.

- **Every `<label>` must be associated with a form control** (`@angular-eslint/template/label-has-associated-control`). For a single input, give the input an `id` and the label a matching `for` (use `[id]`/`[attr.for]` bindings for controls rendered inside `@for` loops, e.g. `[id]="'plan-name-' + planIndex"`). A caption that heads a whole group (a `FormArray`/`formArrayName` or `formGroupName` block) labels no single control — render it as a plain `<div class="form-label">`, not a `<label>`.

- **Type-only imports use an inline `type` modifier** (`@typescript-eslint/consistent-type-imports`). When a symbol from a module is used only as a type, mark it `import { type FormArray, FormBuilder } from '@angular/forms'` rather than importing it as a value; a module used purely for types uses `import type { ... }`.

### Security
- API admin routes are secured with `#[IsGranted('ROLE_ADMIN')]` and the `^/api` access-control rule (`ROLE_ADMIN`), except `^/api/auth/login` and `^/api/public` which are public.
- Passwords are hashed via `UserPasswordListener` (event listener pattern, not a subscriber)
- Users are created via `bin/console app:create-user`

### Current Entities
- **`ContentPage`** (API shortName `Article`) — a page made of ordered content blocks
- **`ContentBlock`** (API shortName `Block`) — a typed, ordered unit of content belonging to a page; data shape is validated per-type via `ValidBlockData`
- **`MenuItem`** — nav entry for the `main`/`footer` menus; can link to a `page` and optionally nest under a `parent` item
- **`SiteSetting`** — key/value store for site-wide config (e.g. home page slug)
- **`User`** — login account with hashed password and roles

## Custom Site (Downstream) Workflow

The CMS is a versioned Composer package: **`salix/cms-bundle`** (0.2.x — pre-1.0, minor versions may break). A custom site is a plain Symfony application that requires the bundle; all site code belongs to the site's own `src/`, `templates/`, and `migrations/` — there are no reserved directories and no git-upstream merges (the old quarantine-lane workflow is gone).

- Site PHP/templates/schema go in the app-level `src/`, `templates/`, `migrations/` (`DoctrineMigrations` namespace); CMS migrations live in the bundle on `Salix\Cms\Migrations`, so the timelines never collide.
- CMS frontend templates are overridden per-site via `templates/bundles/SalixCmsBundle/...`; defaults render from `@SalixCms/frontend/...`.
- The admin SPA ships with the bundle — sites never build or customize it.
- CMS upgrade = `composer update salix/cms-bundle` + `doctrine:migrations:migrate`.
- In this monorepo the bundle is consumed via a Composer path repository. Planned next step: a `salix/skeleton` project template (`composer create-project`) with the bundle installed from a Composer registry; the bundle would then ship the admin UI **prebuilt** (built + committed on release tags) so downstream sites never need Node.
- Production/packaging note: the path package is **mirrored** into `vendor/salix/cms-bundle` (`COMPOSER_MIRROR_PATH_REPOS=1`) by the prod Docker build and `bin/package-release`; both then prune `vendor/salix/cms-bundle/admin-app` (mirroring copies everything) and drop the `cms-bundle/` source dir from the artifact.

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

# Build the shared-hosting release zip from HEAD into var/releases/
bin/package-release
```

Pushing a `v*` tag triggers `.github/workflows/release.yml`, which builds both release
artifacts from that commit: the shared-hosting zip (attached to the GitHub release) and
the production Docker image (pushed to ghcr.io).

### Frontend / Admin SPA commands (project root)

```bash
npm install            # install root + admin-app dependencies
npm run dev            # watch frontend SCSS + ng serve on :4200 (proxies /api and /uploads to :8000)
npm run build          # compile frontend SCSS + production-build the SPA into public/admin
```

## General Guidelines

- Prefer Symfony attributes over YAML/XML configuration
- Frontend (public) templates live in `templates/` — follow the existing `frontend/layout.html.twig` layout
- Follow existing naming patterns for new entities, controllers, and repositories
- The **admin UI is an Angular SPA** in `admin-app/` talking to the **API Platform** API under `/api`; the public frontend is rendered server-side via Twig
- Admin UI input fields have `autocomplete="off"` by default
- Do not add code comments for trivial functions
