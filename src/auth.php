<?php
/**
 * Authentication & authorization helpers.
 *
 * The session only stores the user id. The user row is re-loaded from the DB
 * on every request, so blocking a user or changing their role takes effect
 * immediately.
 */

declare(strict_types=1);

const PUBLIC_USER_COLUMNS = 'id, username, email, display_name, bio, avatar_path, role, is_active, created_at';

function hash_password(string $password): string
{
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    return password_hash($password, $algo);
}

/**
 * Verify a password and transparently upgrade the hash if the algorithm/cost changed.
 */
function verify_password(string $password, string $hash, ?int $userId = null): bool
{
    if (!password_verify($password, $hash)) {
        return false;
    }
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    if ($userId !== null && password_needs_rehash($hash, $algo)) {
        db_query('UPDATE users SET password_hash = ? WHERE id = ?', [hash_password($password), $userId]);
    }
    return true;
}

/** Hash used to keep login timing constant when the username does not exist. */
function dummy_password_hash(): string
{
    static $hash = null;
    return $hash ??= hash_password('dummy-password-for-timing');
}

/** @return array<string,mixed>|null */
function current_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }

    $row = db_query('SELECT ' . PUBLIC_USER_COLUMNS . ' FROM users WHERE id = ?', [$id])->fetch();
    if (!$row || (int) $row['is_active'] !== 1) {
        // Deleted or blocked while logged in: kill the session.
        logout_user();
        return null;
    }

    return $user = $row;
}

function current_user_id(): ?int
{
    $user = current_user();
    return $user ? (int) $user['id'] : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('info', 'Please log in to continue.');
        $next = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        redirect('/login.php' . (is_safe_internal_path($next) ? '?next=' . rawurlencode($next) : ''));
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        log_action('admin_access_denied', 'URI: ' . mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 200));
        abort(403);
    }
    return $user;
}

/**
 * Ownership check for edit/delete. Use for every resource with a user_id.
 *     require_owner($pin['user_id']);
 */
function require_owner(int|string $ownerId): void
{
    $user = require_login();
    if ((int) $ownerId !== (int) $user['id']) {
        abort(403);
    }
}

/** Call after a successful password check. */
function login_user(int $userId): void
{
    session_regenerate_id(true); // prevent session fixation
    $_SESSION['user_id'] = $userId;
    $_SESSION['login_at'] = time();
    csrf_rotate();
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies') && PHP_SAPI !== 'cli') {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
}
