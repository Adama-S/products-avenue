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

/**
 * A UTC date shown in French, in Paris time: « 31 octobre 2026 à 00:26 » (US-31).
 *
 * Dates are stored and handled in UTC; this is the only place where they become local
 * time, so a stored date is never ambiguous around a daylight saving change.
 */
function french_datetime(DateTimeImmutable $date): string
{
    $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Europe/Paris', IntlDateFormatter::GREGORIAN, "d MMMM y 'à' HH:mm");

    return (string) $formatter->format($date);
}
