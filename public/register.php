<?php
/**
 * Register a new account, then log in.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if (is_logged_in()) {
    redirect('/');
}

$username = '';
$email = '';
$errors = [];

if (is_post()) {
    csrf_verify();

    $username = input('username');
    $email = strtolower(input('email'));
    $password = input_raw('password');
    $confirm = input_raw('password_confirm');

    $errors = array_filter([
        'username'         => v_username($username),
        'email'            => v_email($email),
        'password'         => v_password($password),
        'password_confirm' => $password !== $confirm ? 'Passwords do not match.' : null,
    ]);

    if (!$errors) {
        // Friendly per-field messages. The UNIQUE keys below are the real guarantee.
        $taken = db_query(
            'SELECT username = ? AS u, email = ? AS e FROM users WHERE username = ? OR email = ?',
            [$username, $email, $username, $email]
        )->fetchAll();
        foreach ($taken as $row) {
            if ((int) $row['u'] === 1) {
                $errors['username'] = 'That username is already taken.';
            }
            if ((int) $row['e'] === 1) {
                $errors['email'] = 'That email is already registered.';
            }
        }
    }

    if (!$errors) {
        $hash = hash_password($password);
        try {
            // Explicit column list: role/is_active can never come from the request.
            db_query(
                'INSERT INTO users (username, email, password_hash, display_name) VALUES (?, ?, ?, ?)',
                [$username, $email, $hash, $username]
            );
            $userId = (int) db()->lastInsertId();
        } catch (PDOException $e) {
            if (!is_duplicate_key($e)) {
                throw $e;
            }
            // Lost a race with a concurrent registration.
            $errors['username'] = 'That username or email is already registered.';
        }

        if (!$errors) {
            login_user($userId, $hash);
            log_action('register', null, $userId);
            flash('success', 'Welcome to ' . config('app.name') . ', ' . $username . '.');
            redirect('/');
        }
    }

    http_response_code(422);
}

view('header', ['title' => 'Sign up']);
?>
<form class="form card" method="post" action="/register.php" novalidate>
    <h1>Sign up</h1>
    <?= csrf_field() ?>

    <label>
        Username
        <input type="text" name="username" value="<?= e($username) ?>" maxlength="30"
               pattern="[A-Za-z0-9_]{3,30}" autocomplete="username" required autofocus<?= field_aria($errors, 'username') ?>>
        <span class="hint">3-30 characters: letters, numbers, underscore.</span>
        <?= field_error($errors, 'username') ?>
    </label>

    <label>
        Email
        <input type="email" name="email" value="<?= e($email) ?>" maxlength="255"
               autocomplete="email" required<?= field_aria($errors, 'email') ?>>
        <?= field_error($errors, 'email') ?>
    </label>

    <label>
        Password
        <input type="password" name="password" minlength="8" maxlength="128"
               autocomplete="new-password" required<?= field_aria($errors, 'password') ?>>
        <span class="hint">At least 8 characters.</span>
        <?= field_error($errors, 'password') ?>
    </label>

    <label>
        Confirm password
        <input type="password" name="password_confirm" minlength="8" maxlength="128"
               autocomplete="new-password" required<?= field_aria($errors, 'password_confirm') ?>>
        <?= field_error($errors, 'password_confirm') ?>
    </label>

    <button type="submit" class="btn btn-primary">Create account</button>
    <p class="muted">Already have an account? <a href="/login.php">Log in</a></p>
</form>
<?php view('footer'); ?>
