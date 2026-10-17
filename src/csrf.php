<?php
/**
 * CSRF protection (synchronizer token, one per session).
 *
 * In every POST form:     <?= csrf_field() ?>
 * At the top of handler:  if (is_post()) { csrf_verify(); ... }
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Abort with 403 if the submitted token is missing or wrong. */
function csrf_verify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submitted) || !is_string($expected) || $expected === ''
        || !hash_equals($expected, $submitted)) {
        error_log('[mozaic] CSRF token mismatch from ' . client_ip());
        // 403, not 419: Apache turns non-standard status codes into a 500.
        abort(403, 'Your session expired or the form is invalid. Please go back, reload the page and try again.');
    }
}

/** New token after privilege changes (login/logout). */
function csrf_rotate(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
