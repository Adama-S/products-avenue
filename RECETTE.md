# Acceptance testing

Manual acceptance testing of the application running in Docker, against the real FakeStore API.
Automated tests (`composer test`) check the code; this checklist checks that the application does what the brief asks.

**Setup**: fresh clone of the repository, `cp .env.example .env`, `docker compose up -d --build --wait`, then open `http://localhost:8050`.
To read HTTP status codes: browser developer tools, Network tab.
To query the database: `docker compose exec db mysql -u<user> -p catalog` (credentials in the README).
The site interface is in French: messages shown to users are quoted in French below.

**Result** column: OK or KO, with a note when KO.

| ID | Area | Steps | Expected result | Result |
| --- | --- | --- | --- | --- |
| R-01 | Setup | From a fresh clone, run `docker compose up -d --build --wait`, open `http://localhost:8050` | The three databases are created, the catalogue is displayed with no manual step | |
| R-02 | Catalogue | Browse the catalogue | The 20 FakeStore products, each with a title, an image and a price formatted in French (`12,90 €` with the default EUR); 10 products on page 1 | |
| R-03 | Pagination | Browse pages 1 and 2; then set `PRODUCTS_PER_PAGE=8` in `.env`, recreate `app` (`docker compose up -d app`; `restart` does not reload `.env`), browse again | 10 then 10 products; consistent Previous and Next links; title « Catalogue, page 2 » on page 2. With 8 per page: 8, 8 then 4 products over 3 pages | |
| R-04 | Pagination | Open `/?page=99`, `/?page=0` and `/?page=abc` | « Page introuvable » page, status 404 in all three cases | |
| R-05 | Product page | Click a product | URL `/products/{id}`, a single `h1` (product title), image, description, price, reviews from newest to oldest | |
| R-06 | Product page | Open `/products/9999`, `/products/abc` and `/products/0` | « Produit introuvable » page, status 404 in all three cases, with a « Retour au catalogue » link | |
| R-07 | Reviews | Submit a valid review, then refresh the page (F5) | Redirect to the product page, « Votre avis est publié. » message, review shown only once | |
| R-08 | Validation | Submit an empty review, then content longer than 1,000 characters | Errors shown next to the fields, input kept, status 422, nothing saved | |
| R-09 | Reviews | With a tool such as curl, post a review for product `9999` with a valid CSRF token | Status 404, nothing saved: reviews can only target existing products | |
| R-10 | Security (XSS) | Post the review `<script>alert(1)</script>` | The text is displayed as is, no dialog opens | |
| R-11 | Reviews | Edit a review | Pre-filled form, change visible on the product page | |
| R-12 | Reviews | Delete a review; repeat with JavaScript disabled | Confirmation prompt with JavaScript; deletion works in both cases | |
| R-13 | Security (CSRF) | In the inspector, change the value of the hidden CSRF token field, then submit | Status 403, « Formulaire expiré » message, nothing is saved | |
| R-14 | Cache | Empty `var/cache/products`, open the catalogue, list it (`docker compose exec app ls var/cache/products`), reload the page | Cache files appear after the first visit; the reload does not call the API again (no new API call in the logs) | |
| R-15 | API resilience | Set an invalid URL in `PRODUCTS_API_URL`, empty `var/cache/products`, recreate `app` (`docker compose up -d app`), open the catalogue | « Le catalogue est momentanément indisponible » message, status 503, no technical details; error logged with the URL, the HTTP code and the duration (`docker compose logs app`) | |
| R-16 | API outage (stale-if-error) | With the valid URL, open the catalogue to fill the cache. Set `CACHE_TTL=5` (seconds) and an invalid `PRODUCTS_API_URL`, recreate `app` (`docker compose up -d app`), wait 10 s, open the catalogue and a product already visited | The catalogue and the product are still displayed, status 200; a warning « serving stale copy » is logged. A product never visited returns 503 | |
| R-17 | Database resilience | `docker compose stop db`, open a product page; then `docker compose start db` | Clear message, status 503, no technical details; the application works again once the database is back | |
| R-18 | HTTP cache | `curl -I http://localhost:8050/`, then `curl -I -H 'If-None-Match: <ETag received>' http://localhost:8050/`, then `curl -I` on a product page | Catalogue: `Cache-Control: public, max-age=300`, an `ETag`, no `Set-Cookie`; second call: status 304. Product page: `Cache-Control: no-store` | |
| R-19 | SEO | View the source of a product page, then of the 404 page | Product page: `title`, meta description, canonical, `html lang="fr"`, JSON-LD `Product`, Open Graph. 404 page: `noindex` | |
| R-20 | Responsive | Developer tools, 390 px wide | Readable layout, no horizontal scrolling | |
| R-21 | SQL (part 1) | Load `database/blissim.sql` twice in a row, read the query results | No error; query 1: one row per customer; query 2: quantities in descending order | |
| R-22 | Quality | `docker compose exec app composer check` | Code style check, PHPStan and the three test suites pass | |
| R-23 | CI and README | Push to GitHub, open the Actions tab, then the README on GitHub | The CI workflow is green; the badge shows « passing »; the Mermaid diagram renders | |
| R-24 | Navigation | Choose 20 then 30 in the « produits par page » selector, go to page 2 with 10 per page; then open `/?per_page=100000` | 20 products on one page; the choice stays in the pagination links; changing the number goes back to page 1; the invalid value shows the default 10 per page with no error | |
| R-25 | Navigation and accessibility | Open a product page; then browse the catalogue with the Tab key only | Breadcrumb « Catalogue / product title », « Catalogue » links to the catalogue, JSON-LD `BreadcrumbList` in the source. With the keyboard: « Aller au contenu » link first, focus always visible, current page marked in the pagination | |
| R-26 | Reviews display | Open a product with no review; post two reviews, edit one; go back to the catalogue after 5 minutes (or with an empty browser cache) | « Aucun avis pour l'instant… » message at first; dates in French (« 31 octobre 2026 à 00:26 »); « modifié » on the edited review only; the product card shows « 2 avis » | |
| R-27 | Character counter | Type in the review field, with JavaScript then with JavaScript disabled | With JavaScript: « N / 1000 » updated while typing. Without: no counter, the form still works and the 1,000-character limit is still enforced by the server | |
| R-28 | Reviews pagination | Post 12 reviews on one product; browse the review pages; edit a review on page 2; delete the only review left on the last page; open `?comments_page=99` | 10 reviews then 2, « Avis 11 à 12 sur 12 »; after the edit, back on page 2; after the deletion, the last existing page is shown; page 99 returns 404. Same order on every reload | |
| R-29 | Security headers and URLs | `curl -I` on a product page; then open the edit page of a review with another product's id in the URL (`/products/2/comments/<id of a review of product 1>/edit`) | `Content-Security-Policy`, `Referrer-Policy`, `X-Content-Type-Options`, `X-Frame-Options` present; no blocked resource in the browser console; the mismatched URL returns 404 | |
| R-30 | Dates | Post a review, compare its displayed time with your clock, then read `created_at` in MySQL | Displayed in Paris time; stored in UTC (1 hour behind in winter) | |

## Summary

- Date:
- Scenarios OK: / 30
- Issues found and fixes:
