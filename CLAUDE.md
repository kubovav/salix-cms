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
- The admin UI is a separate **Angular SPA** (see below); the Symfony app exposes a JSON **API** for it under `/api`
- Public (frontend) routes are open and rendered server-side with Twig

### Admin UI — Angular SPA + API Platform
- The admin section is an **Angular standalone app** living in `admin-app/`, built into `public/admin/` and served by Nginx at `/admin` (same-origin).
- The backend exposes a REST API via **API Platform** under `/api` (config in `config/packages/api_platform.yaml`). Entities are exposed with `#[ApiResource]` + serialization groups + validation constraints. API responses are **plain JSON** (`formats: json`), so collections are plain arrays.
- **Auth is session-cookie based, same-origin** (reuses Symfony Security — no JWT). The `api` firewall uses `json_login` (`POST /api/auth/login` with `{email, password}`), `POST /api/auth/logout`, and `GET /api/auth/me`. Custom handlers return JSON instead of HTML redirects (`src/Security/`).
- API Platform defaults: `stateless: false` (session auth), `pagination_enabled: false`, `extra_properties.standard_put: false` (so PUT populates the managed entity and `UniqueEntity` works).
- Custom API endpoints live in `src/Controller/Api/` (uploads, block reorder, settings, meta).
- **Frontend dev workflow** (in `admin-app/`):
  - `npm install` then `npx ng serve` (uses `proxy.conf.json` to proxy `/api` + `/uploads` to `http://localhost:8000`)
  - `npx ng build` — outputs to `public/admin/` with `baseHref: /admin/`
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

### Admin SPA (Angular) commands

```bash
cd admin-app
npm install            # install Angular + UI dependencies
npx ng serve           # dev server (proxies /api and /uploads to :8000)
npx ng build           # production build into ../public/admin
```

## General Guidelines

- Prefer Symfony attributes over YAML/XML configuration
- Frontend (public) templates live in `templates/` — follow the existing `frontend/layout.html.twig` layout
- Follow existing naming patterns for new entities, controllers, and repositories
- The **admin UI is an Angular SPA** in `admin-app/` talking to the **API Platform** API under `/api`; the public frontend is rendered server-side via Twig
- Admin UI input fields have `autocomplete="off"` by default
- Do not add code comments for trivial functions
