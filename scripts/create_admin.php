<?php
/**
 * Create (or promote) an admin account from the command line.
 *
 *   docker compose exec app php scripts/create_admin.php
 *
 * The password is read from an interactive prompt, so it never ends up in
 * shell history or source control.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

function prompt(string $label, bool $hidden = false): string
{
    fwrite(STDOUT, $label);
    if ($hidden && DIRECTORY_SEPARATOR === '/') {
        shell_exec('stty -echo');
        $value = fgets(STDIN);
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    } else {
        $value = fgets(STDIN);
    }
    return trim((string) $value);
}

$username = prompt('Admin username: ');
$email    = prompt('Admin email: ');
$password = prompt('Password (min 8 chars): ', true);
$confirm  = prompt('Confirm password: ', true);

$errors = array_filter([
    v_username($username),
    v_email($email),
    v_password($password),
    $password !== $confirm ? 'Passwords do not match.' : null,
]);
if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

$existing = db_query('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email])->fetch();

if ($existing) {
    db_query(
        "UPDATE users SET role = 'admin', is_active = 1, password_hash = ? WHERE id = ?",
        [hash_password($password), (int) $existing['id']]
    );
    log_action('admin_promoted_cli', 'username: ' . $username, (int) $existing['id']);
    fwrite(STDOUT, "Existing user promoted to admin and password reset." . PHP_EOL);
} else {
    db_query(
        "INSERT INTO users (username, email, password_hash, display_name, role) VALUES (?, ?, ?, ?, 'admin')",
        [$username, $email, hash_password($password), $username]
    );
    $id = (int) db()->lastInsertId();
    log_action('admin_created_cli', 'username: ' . $username, $id);
    fwrite(STDOUT, "Admin user created (id $id)." . PHP_EOL);
}
