# Salix CMS

Lightweight CMS built with Symfony and Twig, optimized for rapid development and reusable website delivery.

## Project Status

This project is currently a **work in progress** and is under active development.

- Interfaces and internal architecture may evolve as core capabilities are stabilized.
- Documentation is updated iteratively and may trail the latest implementation details.
- Some modules are currently intended for development and evaluation workflows.

This repository also serves as a continuously evolving reference implementation for full-stack architecture and delivery practices.

## Repository Layout

- `src/`: Symfony application code
- `templates/`: Twig templates
- `migrations/`: Doctrine database migrations
- `config/`: Symfony configuration
- `public/`: Web root
- `docker/`: container config (Dockerfile, Nginx, Supervisor)

## Development Workflow (Single App Container)

The `salix_dev` service runs two processes via Supervisor:

- **Nginx** — on port `8000` (mapped to `8010` on host), serves the Symfony app
- **PHP-FPM** — executes Symfony

Nginx and Supervisor configs are symlinked from `docker/` into the container so changes take effect with a reload — no image rebuild required:

```bash
# Apply nginx.conf changes
nginx -s reload

# Apply supervisord.conf changes
supervisorctl reload
```

## Start

```bash
docker compose up --build -d
```

Then open:

- Site: `http://localhost:8010`
- phpMyAdmin: `http://localhost:8010/phpmyadmin`

