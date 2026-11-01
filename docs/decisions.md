# Decisions

The choices behind Products Avenue, each with its context and the alternative that was rejected. Back to the [README](../README.md).

## FakeStore rather than DummyJSON

- Context: the brief asks for products from a public API, shown with their title, image and price, and reviews stored through PDO.
- Choice: FakeStore, whose products hold just what the pages need (7 fields: id, title, price, description, category, image, rating), and which has no reviews: our database is the only source of truth for reviews.
- Rejected: DummyJSON, whose products carry 22 fields, most of them of no use here (stock, brand, SKU, weight, dimensions, warranty, shipping and return policies, minimum order, metadata), and ship their own reviews: more data to map and ignore, and two sources of reviews to reconcile.

## The API is the product source, no products table

- Context: products are read-only here and FakeStore is the reference.
- Choice: read them from the API behind a cache; only reviews are stored.
- Rejected: importing products into MySQL, a synchronisation to maintain for 20 read-only products (see « Going further »).

## File cache rather than Redis

- Context: one server, and a cache that must survive an API outage.
- Choice: one file per key, atomic writes, behind `CacheInterface`.
- Rejected: Redis, one more service to run for no gain on one server; it becomes the first step when the site runs on several servers.

## Native PHP rather than Symfony

- Context: the brief asks for a simple project that shows the fundamentals.
- Choice: native PHP 8.5 with Composer for autoloading and dev tools only; a router, a few controllers and templates.
- Rejected: Symfony, the right choice on a real project, but it would hide the routing, CSRF, validation and caching this test is meant to show.

## No foreign key on reviews

- Context: a review points to a FakeStore product, which is not in the database.
- Choice: `product_external_id` without a foreign key; the application checks that the product exists in the API before saving (404 otherwise).
- Rejected: a local products table only to hold a foreign key, a copy of the API to keep in sync.

## Stale copy served for 24 hours at most

- Context: a catalogue that goes down loses sales, but an old price is a problem too.
- Choice: during an API outage, a copy that expired less than 24 hours ago is served and a warning logged; a product not found is never served from an old copy.
- Rejected: no fallback (a 503 at the first outage) or no limit (prices days old).

## Product page in `no-store`

- Context: the product page holds a CSRF token of its own for each visitor, confirmation messages and reviews that change.
- Choice: `Cache-Control: no-store`; only the catalogue is publicly cacheable, and it opens no session.
- Rejected: a public cache on the product page, which would serve one visitor's token to another.

## Dates in UTC

- Context: MySQL runs in UTC in Docker, and local times are ambiguous when clocks change.
- Choice: the connection sets `time_zone = '+00:00'`, PHP works in UTC, and dates become Europe/Paris only when displayed (`french_datetime()`).
- Rejected: storing Paris time, where 02:30 on the last Sunday of October happens twice.

## Numeric ids in URLs

- Context: `/products/5` and `/products/5/comments/12/edit` expose sequential ids.
- Choice: keep them; security relies on server checks (CSRF token, POST only for changes, review checked by id and product, `noindex`), not on ids hard to guess.
- Rejected: UUID or ULID, a heavier key and bigger indexes for no gain here. In production: the product name in the URL for SEO (`/products/5-product-name`, 301 when the name changes), and UUIDs for any private URL shared without a login.

## Native CSS rather than Bootstrap or Tailwind

- Context: the brief asks for a light style, as a bonus.
- Choice: one native CSS file with variables, Grid and Flexbox; fluid `clamp()` values instead of breakpoints.
- Rejected: Bootstrap (about 200 KB for a few components, a recognisable template look, JavaScript components not needed here) and Tailwind (a build step with Node to stay light, or a CDN not meant for production, and utility classes that weigh on templates). Native CSS is enough at this size, reads without tools and fits the strict CSP; with a team and a design system, Tailwind or in-house components would be worth discussing.

## Reviews editable by anyone

- Context: the brief requires no authentication.
- Choice: anonymous reviews with CSRF protection and validation, but no owner.
- Rejected: an account or an ownership token, which proves nothing about the purchase. In production, only a customer who ordered the product would review it: the part 1 schema already allows it through `orders` and `order_lines`.

## Part 1: statements the database refuses

**The database refuses absurd data on its own.** Each of these statements must fail, and nothing is changed:

| Rule | Statement | Error |
| --- | --- | --- |
| Quantity of at least 1 | `INSERT INTO order_lines (order_id, product_id, quantity, unit_price_cents) VALUES (9, 1, 0, 2490);` | `chk_order_lines_quantity` is violated |
| No negative price | `INSERT INTO products (name, price_cents) VALUES ('Test', -100);` | Out of range value for `price_cents` |
| No empty name | `INSERT INTO products (name, price_cents) VALUES ('   ', 100);` | `chk_products_name` is violated |
| ISO 4217 currency | `INSERT INTO orders (customer_id, currency) VALUES (1, 'eur');` | `chk_orders_currency` is violated |
| Unique email, whatever the case | `INSERT INTO customers (first_name, last_name, email) VALUES ('Sophie', 'Martin', 'SOPHIE.MARTIN@example.com');` | Duplicate entry for `uq_customers_email` |
| Known customer | `INSERT INTO orders (customer_id) VALUES (999);` | Foreign key `fk_orders_customer` fails |
| A sold product is kept | `DELETE FROM products WHERE id = 1;` | Foreign key `fk_order_lines_product` fails |
| A customer with orders is kept | `DELETE FROM customers WHERE id = 1;` | Foreign key `fk_orders_customer` fails |

Run them with `docker compose exec db mysql --default-character-set=utf8mb4 -uapp -papp_password blissim`.
