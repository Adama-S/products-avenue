<?php

declare(strict_types=1);

/**
 * Escapes a value for HTML text and attributes (US-22).
 *
 * Every value shown in a template goes through e(), data from the products API included:
 * this is what blocks XSS. ENT_QUOTES covers attributes in single and double quotes;
 * ENT_SUBSTITUTE replaces invalid UTF-8 instead of returning an empty string.
 */
function e(string|int $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
