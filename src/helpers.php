<?php
/**
 * General helpers: output escaping, redirects, flash messages, request input.
 */

declare(strict_types=1);

/** Escape for HTML text and attribute context. Use on EVERY piece of user data. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/**
 * Redirect to an internal path only (prevents open redirects).
 * Accepts "/path?query"; anything else falls back to "/".
 */
function redirect(string $path, int $status = 303): never
{
    if (!is_safe_internal_path($path)) {
        $path = '/';
    }
    header('Location: ' . $path, true, $status);
    exit;
}

function is_safe_internal_path(string $path): bool
{
    // Must start with a single "/", no "//" or "/\" (protocol-relative), no control chars.
    return $path !== ''
        && $path[0] === '/'
        && !str_starts_with($path, '//')
        && !str_starts_with($path, '/\\')
        && !preg_match('/[\x00-\x1F\x7F]/', $path);
}

/** Render a generic error page and stop. Never pass internal details here. */
function abort(int $status = 404, ?string $message = null): never
{
    $messages = [
        400 => 'Bad request.',
        403 => 'You do not have permission to do that.',
        404 => 'Page not found.',
        405 => 'Method not allowed.',
        413 => 'The upload is too large.',
        419 => 'Your session expired. Please go back and try again.',
        429 => 'Too many requests. Please try again later.',
        500 => 'Something went wrong. Please try again later.',
    ];

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, ($message ?? $messages[$status] ?? 'Error') . PHP_EOL);
        exit(1);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
    }
    $errorStatus = $status;
    $errorMessage = $message ?? $messages[$status] ?? 'Error.';
    require APP_ROOT . '/templates/error.php';
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type:string,message:string}> */
function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flashes) ? $flashes : [];
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Allow only the given HTTP methods. State-changing actions must be POST. */
function require_method(string ...$methods): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        abort(405);
    }
}

/**
 * Read a scalar string from request input. Arrays (e.g. ?q[]=x) are rejected,
 * which avoids type-juggling bugs. Returns trimmed string, '' if missing.
 */
function input(string $key, string $source = 'POST'): string
{
    $bag = $source === 'GET' ? $_GET : $_POST;
    $value = $bag[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** Positive integer from input, or null. */
function input_int(string $key, string $source = 'GET'): ?int
{
    $value = filter_var(input($key, $source), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $value === false ? null : $value;
}

/** Client IP. Only REMOTE_ADDR is trusted (X-Forwarded-For is spoofable). */
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function user_agent(): string
{
    return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/** Public URL for a stored upload path like "pins/abc.jpg". */
function upload_url(?string $relativePath): string
{
    if ($relativePath === null || $relativePath === '') {
        return '/assets/img/placeholder.svg';
    }
    return config('upload.url_prefix') . '/' . $relativePath;
}

/** Render a template with variables. Templates must escape with e(). */
function view(string $template, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require APP_ROOT . '/templates/' . $template . '.php';
}
