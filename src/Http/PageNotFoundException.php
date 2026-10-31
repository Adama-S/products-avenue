<?php

declare(strict_types=1);

namespace App\Http;

/**
 * No page at this URL: unknown route, or a page number that does not exist (US-22).
 * The visitor sees the « Page introuvable » 404 page.
 */
final class PageNotFoundException extends \RuntimeException {}
