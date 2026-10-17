<?php
/**
 * Log in with username or email.
 *
 * Lockout: 5 failed attempts -> account locked for 15 minutes.
 * Every failure shows the same generic message and runs one password_verify(),
 * so neither the message nor the response time reveals whether an account
 * exists, is locked, or is blocked.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if (is_logged_in()) {
    redirect('/');
}

const LOGIN_GENERIC_ERROR = 'Invalid credentials, or account temporarily locked. Try again later.';

$next = input('next', is_post() ? 'POST' : 'GET');
if (!is_safe_internal_path($next) || str_starts_with($next, '/login.php')) {
    $next = '/';
}

$identifier = '';
$error = null;

if (is_post()) {
    csrf_verify();

    $identifier = input('identifier');
    $password = input_raw('password');
    $maxAttempts = (int) config('security.max_login_attempts', 5);
    $lockMinutes = (int) config('security.lockout_minutes', 15);

    $user = ($identifier !== '' && $password !== '' && strlen($password) <= 128)
        ? find_user_for_login($identifier)
        : null;

    if ($user === null) {
        password_verify($password, DUMMY_PASSWORD_HASH); // equalize timing
        log_action('login_failed', 'unknown identifier: ' . mb_substr($identifier, 0, 50), null);
        $error = LOGIN_GENERIC_ERROR;
    } elseif ((int) $user['is_locked'] === 1) {
        // Locked: don't even check the real password (no brute force while locked).
        password_verify($password, DUMMY_PASSWORD_HASH);
        log_action('login_failed', 'account locked', (int) $user['id']);
        $error = LOGIN_GENERIC_ERROR;
    } else {
        $userId = (int) $user['id'];
        $hash = (string) $user['password_hash'];

        if (!verify_password($password, $hash, $userId)) {
            // Atomic increment; MySQL evaluates SET left-to-right, so locked_until
            // is computed from the OLD count before the count itself is updated.
            // On lock the counter resets, giving 5 fresh attempts after it expires.
            db_query(
                'UPDATE users
                    SET locked_until = IF(failed_login_count + 1 >= ?, UTC_TIMESTAMP() + INTERVAL ? MINUTE, locked_until),
                        failed_login_count = IF(failed_login_count + 1 >= ?, 0, failed_login_count + 1)
                  WHERE id = ?',
                [$maxAttempts, $lockMinutes, $maxAttempts, $userId]
            );
            $nowLocked = (int) db_query(
                'SELECT (locked_until IS NOT NULL AND locked_until > UTC_TIMESTAMP()) FROM users WHERE id = ?',
                [$userId]
            )->fetchColumn();

            log_action($nowLocked ? 'login_locked' : 'login_failed', 'wrong password', $userId);
            $error = LOGIN_GENERIC_ERROR;
        } elseif ((int) $user['is_active'] !== 1) {
            log_action('login_failed', 'account blocked', $userId);
            $error = LOGIN_GENERIC_ERROR;
        } else {
            db_query('UPDATE users SET failed_login_count = 0, locked_until = NULL WHERE id = ?', [$userId]);
            login_user($userId, $hash);
            log_action('login_success', null, $userId);
            flash('success', 'Welcome back, ' . $user['username'] . '.');
            redirect($next);
        }
    }

    http_response_code(401);
}

view('header', ['title' => 'Log in']);
?>
<form class="form card" method="post" action="/login.php" novalidate>
    <h1>Log in</h1>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">

    <?php if ($error): ?>
        <div class="flash flash-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <label>
        Username or email
        <input type="text" name="identifier" value="<?= e($identifier) ?>" maxlength="255"
               autocomplete="username" required autofocus>
    </label>

    <label>
        Password
        <input type="password" name="password" maxlength="128" autocomplete="current-password" required>
    </label>

    <button type="submit" class="btn btn-primary">Log in</button>
    <p class="muted">No account yet? <a href="/register.php">Sign up</a></p>
</form>
<?php view('footer'); ?>
