# Products Avenue

Blissim technical test: an e-commerce database (part 1) and a native PHP catalogue of FakeStore products with customer reviews (part 2).

> Work in progress. This README is completed at the end of the project.

## Requirements

- Docker with Docker Compose
- Free ports 8050 (site) and 3350 (MySQL), or other values in `.env`

## Run

```bash
cp .env.example .env
docker compose up -d --build --wait
```

- Site: http://localhost:8050
- Health check: http://localhost:8050/health
- MySQL from your machine: `localhost:3350`, user and password in `.env`

The first start installs the PHP dependencies and creates three databases: `blissim` (part 1, loaded from `database/blissim.sql`), `catalog` (the application) and `catalog_test` (tests).

## Checks

```bash
docker compose exec app composer check
```

Runs the code style check, PHPStan (level 8) and the three test suites.

On Linux, add `-u www-data` (`docker compose exec -u www-data app composer check`) so that cache files keep your user's ownership.

## Reset the databases

The databases are created on the first start only. To recreate them (this deletes the data):

```bash
docker compose down -v && docker compose up -d --wait
```

## Going further

- **Injected logger.** Errors are logged with `error_log()`, which Docker sends to `docker compose logs app`; tests redirect it to a temporary file. In a v2, a PSR-3 logger (Monolog) injected in the constructors would add log levels, structured context (URL, status, duration as fields) and shipping to a log platform, and tests would assert on an in-memory logger.
