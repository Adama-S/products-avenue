# Architecture

How Products Avenue is built and run. Back to the [README](../README.md).

## Project structure

```text
.
├── public/                  web root, the only folder Apache serves
│   ├── index.php            Front Controller and Composition Root
│   ├── assets/style.css     the only stylesheet (native CSS)
│   ├── assets/app.js        delete confirmation and character counter (optional)
│   └── favicon.svg
├── src/                     application code (namespace App\, PSR-4)
│   ├── Config.php           settings read once from the environment
│   ├── Http/                Request, Response, Router, Application, PathId
│   │   └── Client/          HttpClientInterface, CurlHttpClient
│   ├── Controller/          ProductController, CommentController, ProductPage, HealthController
│   ├── Product/             Product, Money, PerPage, providers (HTTP, cached), exceptions
│   ├── Comment/             Comment, CommentRepository, CommentValidator, CommentForm, CommentNotice
│   ├── Cache/               CacheInterface, FileCache, ClockInterface, SystemClock
│   ├── Pagination/          Pagination (catalogue and reviews)
│   ├── Security/            Csrf
│   ├── View/                View (templates, JSON-LD), e() and french_datetime()
│   └── Database/            PdoFactory and its exceptions
├── templates/               PHP templates: layout, pages, partials (no business logic)
├── database/
│   ├── blissim.sql          part 1: schema, seed, the two queries
│   └── catalog.sql          the reviews table
├── tests/
│   ├── Unit/                no network, no database
│   ├── Integration/         CommentRepository and PdoFactory on catalog_test
│   ├── Functional/          the whole application with simulated requests
│   ├── Double/              fakes: HTTP client, product provider, clock
│   └── Support/             test helpers (application builder, temporary directory)
├── docker/                  PHP image, Apache and PHP settings, MySQL init scripts
├── docker-compose.yml       services app (PHP 8.5, Apache) and db (MySQL 8.0)
├── .github/workflows/ci.yml the same containers in CI: composer check on every push
└── RECETTE.md               acceptance test checklist
```

| Folder | Role and rule |
| --- | --- |
| `public/` | Nothing else is reachable over HTTP: `src/`, `.env` and `vendor/` never are |
| `src/Http/` | The only place that reads `$_GET`, `$_POST` and `$_SERVER` (`Request::fromGlobals()`) |
| `src/Controller/` | No SQL and no HTML: requests in, responses out |
| `src/Product/` | The only code that knows FakeStore's URLs and JSON is `HttpProductProvider` |
| `src/Comment/` | The only SQL of the application is in `CommentRepository` |
| `src/Security/` | `Csrf` is the only class that touches the session |
| `templates/` | Display only; every value is escaped with `e()` |
| `tests/` | Three PHPUnit suites, see « Test suites » |

## HTTP endpoints

The application is server-rendered: each route returns an HTML page, except `/health` (JSON). Forms are plain HTML forms and work without JavaScript.

| Method | Path | Parameters | Responses | HTTP cache |
| --- | --- | --- | --- | --- |
| `GET` | `/` | `page` (1, 2…), `per_page` (10, 20 or 30) | 200 catalogue, 304 unchanged, 404 page out of range, 503 API down | `public, max-age=300`, weak `ETag`, no cookie |
| `GET` | `/products/{id}` | `comments_page` (1, 2…), `review` (`published`, `updated`, `deleted`: confirmation) | 200, 404 unknown product or review page, 503 | `no-store` |
| `POST` | `/products/{id}/comments` | form: `csrf_token`, `author`, `content` | 303 to the product page, 422 invalid input, 403 invalid token, 404 unknown product | `no-store` |
| `GET` | `/products/{id}/comments/{commentId}/edit` | `comments_page` (page to go back to) | 200 pre-filled form (`noindex`), 404 | `no-store` |
| `POST` | `/products/{id}/comments/{commentId}` | form: `csrf_token`, `author`, `content`, `comments_page` | 303, 422, 403, 404 | `no-store` |
| `POST` | `/products/{id}/comments/{commentId}/delete` | form: `csrf_token`, `comments_page` | 303, 403, 404 | `no-store` |
| `GET` | `/health` | | 200 `{"status":"ok","database":"up"}`, 503 when MySQL is down | `no-store` |

- **Ids** are plain positive integers: `0`, `01`, `abc` or 20 digits are a 404, checked before any API call or query.
- **A review is reached through its product**: a review of product 1 requested under `/products/2/...` is a 404, the same as an unknown review.
- **Static files** (`/assets/style.css`, `/assets/app.js`, `/favicon.svg`) are served by Apache; every other URL goes to `public/index.php`.
- **Security headers** on every response: `Content-Security-Policy` (scripts, styles and forms from the site only, images from FakeStore), `Referrer-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`. The session cookie is `HttpOnly` and `SameSite=Lax`.

**FakeStore endpoints used**: `GET /products` (the whole catalogue, 20 products) and `GET /products/{id}`. FakeStore answers an unknown id with `200` and an empty body (checked on 31 October 2026): it is handled as « not found », like a 404.

## Design patterns

Each pattern answers a need of this project. None is there « just in case ».

| Pattern | Where | Why |
| --- | --- | --- |
| **Adapter** | `HttpProductProvider` | FakeStore's format stays in one class, which turns its JSON into typed `Product` objects and handles every API error (timeouts, retry, invalid data). Changing the API means writing another provider, nothing else. |
| **Decorator** | `CachedProductProvider` | Adds the cache, and the stale copy during an outage, around the HTTP provider, with the same interface and without changing it. Controllers do not know whether a product comes from the cache. |
| **Repository** | `CommentRepository` | All the SQL of the reviews in one place, with prepared statements; every read and write checks the review id **and** the product id. |
| **DTO** | `Product`, `Comment` | Typed, read-only objects instead of raw arrays: a template cannot mistype a key, PHPStan checks every use. |
| **Value Object** | `Money` | An amount never exists without its currency; cents avoid float errors; formatting (`12,90 €`) and checks (no negative amount, ISO code) live in one place. |
| **Post/Redirect/Get** | `CommentController` | Every form answers with a 303 redirect: a refresh or a double click never posts a review twice. |
| **Front Controller** | `public/index.php` and `Application` | One entry point: routing, security headers and error pages are handled once for every URL. |
| **Composition Root** | `public/index.php` | The concrete classes are chosen in one place; the rest of the code depends on interfaces, so tests swap in fakes without any container. |

Small idioms that go with them: **named constructors** (`Money::fromCents()`, `Money::fromDecimal()`), **enums for closed lists** (`PerPage`, `CommentNotice`: a URL can only pick an allowed value), **one exception per business case** (`ProductNotFoundException`, `ProductApiUnavailableException`, `DatabaseUnavailableException`, `CommentNotFoundException`, `InvalidCsrfTokenException`), thrown where the problem is found and caught only in `Application`.

**Not used, on purpose**: a dependency injection container, a service locator, abstract factories, an ORM, an empty « service » layer between controllers and the repository, CQRS. At this size they would add ceremony, not value.

**SOLID in practice**

| Principle | In this code |
| --- | --- |
| Single responsibility | The provider talks to the API, the decorator caches, the repository does SQL, the controllers do HTTP, the validator checks input, `Csrf` handles the token |
| Open/closed | Redis or another API means a new class implementing an existing interface, without changing the others |
| Liskov substitution | The HTTP provider, the cached provider and the test fake honour the same contract, exceptions included |
| Interface segregation | Short interfaces: `ProductProviderInterface` (2 methods), `CacheInterface` (3), `HttpClientInterface` (1), `ClockInterface` (1) |
| Dependency inversion | Controllers depend on `ProductProviderInterface`; the concrete classes are chosen in `public/index.php` |

## Configuration

Every setting comes from the environment (`.env`, read by Docker Compose). An invalid value stops the application at startup with a clear message in the logs.

| Variable | Value in `.env.example` | Role |
| --- | --- | --- |
| `APP_PORT` | `8050` | Port of the site on your machine |
| `DB_PORT` | `3350` | Port of MySQL on your machine, for a GUI client |
| `APP_URL` | `http://localhost:8050` | Base of canonical links, Open Graph and structured data |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | `db`, `catalog`, `app`, `app_password` | Application database (tests force `catalog_test`) |
| `MYSQL_ROOT_PASSWORD` | `root_password` | MySQL administrator |
| `PRODUCTS_API_URL` | `https://fakestoreapi.com` | Products API |
| `PRODUCTS_API_CURRENCY` | `EUR` | Currency of the API prices (FakeStore gives none) |
| `PRODUCTS_PER_PAGE` | `10` | Default catalogue page size (visitors can pick 10, 20 or 30) |
| `CACHE_TTL` | `300` | Lifetime of the products cache, in seconds |

## Requirements in detail

| Tool | Version | Why |
| --- | --- | --- |
| Docker Engine | 20.10 or later | Runs PHP 8.5 with Apache, and MySQL 8.0 |
| Docker Compose | v2.1.1 or later (`docker compose`, not `docker-compose`) | `up --wait` waits until both services are healthy |
| Git | any | To clone the repository |
| Free ports | `8050` (site) and `3350` (MySQL) | Both can be changed in `.env` |
| Internet access | to `https://fakestoreapi.com` | The product source; the cache keeps the catalogue up during a short outage |

- **Nothing else on the host**: no PHP, Composer, MySQL or Node. Dependencies are installed inside the container on the first start.
- **Operating systems**: Linux, macOS, or Windows with Docker Desktop (WSL 2 back end). Tested with Docker 20.10.16 and Compose 2.6.0 on Windows 11, and on the GitHub Actions Ubuntu runner.
- **Windows**: `.gitattributes` keeps LF line endings on checkout, so the container shell scripts run whatever your Git settings.
- About 1 GB of disk for the images (`php:8.5-apache`, `mysql:8.0`).

## First start

The first start takes a few minutes:

1. Docker builds the PHP image (Apache, extensions `pdo_mysql` and `intl`, Composer).
2. MySQL starts with an empty volume and runs the init scripts in `docker/mysql/init/`: it creates `catalog` and `catalog_test`, loads `database/blissim.sql` (part 1) into `blissim`, and `database/catalog.sql` (the reviews table) into `catalog` and `catalog_test`.
3. The PHP container runs `composer install` (vendor/ is missing on a fresh clone), creates `var/cache` and `var/log`, then starts Apache.
4. `--wait` returns once MySQL answers and `GET /health` returns 200.

## Running and troubleshooting

| Command | Effect |
| --- | --- |
| `docker compose up -d --wait` | Start (or restart after a change in `.env`: `up` recreates the container, `restart` does not reload `.env`) |
| `docker compose logs -f app` | PHP and Apache logs: API errors, stale copies served, 503 causes |
| `docker compose stop` | Stop, keep the data |
| `docker compose down -v` | Stop and delete the databases |

- Site: http://localhost:8050
- Health check: http://localhost:8050/health

| Symptom | Fix |
| --- | --- |
| `port is already allocated` | Change `APP_PORT` or `DB_PORT` in `.env` (and `APP_URL` with it), then `docker compose up -d --wait` |
| The catalogue shows « momentanément indisponible » | FakeStore cannot be reached from the container: check the network; the cause is in `docker compose logs app` |
| The reviews table is missing after a schema change | The init scripts only run on an empty volume: `docker compose down -v && docker compose up -d --wait` (this deletes local data) |
| Files in `var/` or `vendor/` belong to root (Linux) | Run Composer as the web user: `docker compose exec -u www-data app composer ...` |

## Test suites

```bash
docker compose exec app composer test              # the three suites
docker compose exec app composer test:unit         # no network, no database
docker compose exec app composer test:integration  # CommentRepository on catalog_test
docker compose exec app composer test:functional   # the application with simulated requests
docker compose exec app composer phpstan           # static analysis, level 8
docker compose exec app composer cs:check          # code style (PER Coding Style 3.0), read-only
docker compose exec app composer cs                # fix the code style
docker compose exec app composer check             # style, PHPStan and every test: what the CI runs
```

| Suite | What it covers |
| --- | --- |
| Unit | `Money`, the HTTP provider with a fake HTTP client (timeouts, retry, invalid JSON, FakeStore's empty body), the cache decorator with a frozen clock (fresh, stale copy served, too old, not found), `FileCache`, `Pagination`, `Router`, `Config`, `CommentValidator`, `Csrf`, escaping, French dates, JSON-LD |
| Integration | `CommentRepository` on the real `catalog_test` database: order, pagination, single-query counts (checked with MySQL's query counter), CHECK constraints, UTC dates |
| Functional | Catalogue, pagination, page size, product page, the whole review flow, 404 and 503 pages, HTTP cache and 304, no cookie on the list, security headers, CSRF, structured data |

[`RECETTE.md`](RECETTE.md) is the manual acceptance checklist: 30 scenarios run in Docker against the real FakeStore API (setup, catalogue, pagination, reviews, validation, security, cache, API and database outages, HTTP cache, SEO, responsive, part 1, CI), each with its steps and expected result.
