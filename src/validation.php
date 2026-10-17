<?php
/**
 * Server-side input validation. Each validator returns an error message or null.
 *
 *     $errors = array_filter([
 *         'title' => v_length($title, 'Title', 1, 100),
 *         'url'   => v_url_optional($url),
 *     ]);
 */

declare(strict_types=1);

function v_length(string $value, string $label, int $min, int $max): ?string
{
    if (!mb_check_encoding($value, 'UTF-8')) {
        return "$label contains invalid characters.";
    }
    $len = mb_strlen($value);
    if ($len < $min) {
        return $min === 1 ? "$label is required." : "$label must be at least $min characters.";
    }
    if ($len > $max) {
        return "$label must be at most $max characters.";
    }
    return null;
}

function v_username(string $value): ?string
{
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $value)) {
        return 'Username must be 3-30 characters: letters, numbers and underscore only.';
    }
    return null;
}

function v_email(string $value): ?string
{
    if (strlen($value) > 255 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
        return 'Please enter a valid email address.';
    }
    return null;
}

function v_password(string $value): ?string
{
    if (strlen($value) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (strlen($value) > 128) {
        return 'Password must be at most 128 characters.';
    }
    return null;
}

/** Value must be one of a fixed set (e.g. board visibility). */
function v_in(string $value, string $label, array $allowed): ?string
{
    return in_array($value, $allowed, true) ? null : "Invalid $label.";
}

/**
 * Optional external link: http/https only, must have a host, no credentials.
 * Blocks javascript:, data:, vbscript:, file:, etc.
 */
function v_url_optional(string $value): ?string
{
    if ($value === '') {
        return null;
    }
    return is_safe_http_url($value) ? null : 'Link must be a valid http:// or https:// URL.';
}

function is_safe_http_url(string $url): bool
{
    if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F]/', $url)) {
        return false;
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $parts = parse_url($url);
    if ($parts === false) {
        return false;
    }
    $scheme = strtolower($parts['scheme'] ?? '');
    return in_array($scheme, ['http', 'https'], true)
        && !empty($parts['host'])
        && !isset($parts['user'])
        && !isset($parts['pass']);
}

/** Normalize a free-text field: trim, unify newlines, drop control chars except \n and \t. */
function clean_text(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", trim($value));
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
}

/** Escape LIKE wildcards so user search input is matched literally. Use with ESCAPE '\\'. */
function like_escape(string $value): string
{
    return addcslashes($value, '\\%_');
}
