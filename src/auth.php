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
 * $hash is updated in place when a rehash happens, so callers can pass the
 * current hash on to login_user().
 */
function verify_password(string $password, string &$hash, ?int $userId = null): bool
{
    if (!password_verify($password, $hash)) {
        return false;
    }
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    if ($userId !== null && password_needs_rehash($hash, $algo)) {
        $hash = hash_password($password);
        db_query('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $userId]);
    }
    return true;
}

/**
 * Precomputed Argon2id hash (same cost as real hashes) of a random throwaway string.
 * Verified against when the account doesn't exist or is locked, so response time
 * doesn't reveal which accounts exist.
 */
const DUMMY_PASSWORD_HASH = '$argon2id$v=19$m=65536,t=4,p=1$Qm01SnBqVk9NOHpUTURoNg$sW8msJ7v001yKPw7XEHZ1GG8TmnTnJWCaUV/IvL2RcM';

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

    $row = db_query('SELECT ' . PUBLIC_USER_COLUMNS . ', password_hash FROM users WHERE id = ?', [$id])->fetch();
    $fingerprint = $_SESSION['pw_fp'] ?? '';
    if (!$row
        || (int) $row['is_active'] !== 1
        // Password changed elsewhere: sessions created with the old password are invalid.
        || !is_string($fingerprint)
        || !hash_equals(password_fingerprint($row['password_hash']), $fingerprint)) {
        logout_user();
        return null;
    }

    unset($row['password_hash']); // never keep the hash around in page code
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

/**
 * Short, keyed-free fingerprint of the stored hash. Stored in the session so that
 * changing the password invalidates every other session (the hash changes).
 * The full hash is never put in the session.
 */
function password_fingerprint(string $passwordHash): string
{
    return substr(hash('sha256', $passwordHash), 0, 16);
}

/**
 * Look up a user by username OR email for login.
 * @return array<string,mixed>|null
 */
function find_user_for_login(string $identifier): ?array
{
    if ($identifier === '' || strlen($identifier) > 255) {
        return null;
    }
    $row = db_query(
        'SELECT id, username, password_hash, is_active, failed_login_count, locked_until,
                (locked_until IS NOT NULL AND locked_until > UTC_TIMESTAMP()) AS is_locked
           FROM users
          WHERE username = ? OR email = ?
          LIMIT 1',
        [$identifier, $identifier]
    )->fetch();
    return $row ?: null;
}

/**
 * Re-authenticate the logged-in user (settings: change email/password, delete account).
 * If the hash gets upgraded during verification, the session fingerprint is updated
 * so the current session stays valid.
 */
function verify_current_password(int $userId, string $password): bool
{
    $hash = db_query('SELECT password_hash FROM users WHERE id = ?', [$userId])->fetchColumn();
    if (!is_string($hash)) {
        return false;
    }
    $original = $hash;
    if (!verify_password($password, $hash, $userId)) {
        return false;
    }
    if ($hash !== $original) {
        $_SESSION['pw_fp'] = password_fingerprint($hash);
    }
    return true;
}

/** Call after a successful password check. */
function login_user(int $userId, string $passwordHash): void
{
    session_regenerate_id(true); // prevent session fixation
    $_SESSION['user_id'] = $userId;
    $_SESSION['pw_fp'] = password_fingerprint($passwordHash);
    $_SESSION['login_at'] = time();
    csrf_rotate();
}

/** After this user changes their own password: keep THIS session, kill all others. */
function refresh_session_after_password_change(string $newPasswordHash): void
{
    session_regenerate_id(true);
    $_SESSION['pw_fp'] = password_fingerprint($newPasswordHash);
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
