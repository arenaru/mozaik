# Mozaic

Pinterest-style image sharing app built on LAMP for the **Secure Programming** course.
The focus is security controls, not feature count. See the project scope document for the full feature list.

## Quick start (Docker)

Requirements: Docker Desktop. On Windows/WSL, enable *Settings → Resources → WSL integration* for your distro.

```bash
cp .env.example .env          # then change every password in .env
docker compose up -d --build
docker compose exec app php scripts/create_admin.php
```

Open http://localhost:8080.

Useful commands:

```bash
docker compose logs -f app                       # PHP/Apache errors (never shown in the browser)
docker compose exec app php -l public/index.php  # lint a file
docker compose down -v                           # wipe DB + uploads (schema re-runs on next up)
```

`database/schema.sql` only runs when the DB volume is empty. After schema changes, run `docker compose down -v`.

## Structure

```
config/      config.example.php (env-driven). config/config.php is a git-ignored local override.
src/         bootstrap, db, auth, csrf, helpers, validation, upload, audit (not web-accessible)
templates/   header, footer, error page, partials
public/      DOCUMENT ROOT: pages, assets/, uploads/
database/    schema.sql + init script for the least-privilege DB user
docker/      Dockerfile, Apache vhost, PHP hardening ini
scripts/     CLI tools (create_admin.php)
```

## Writing a page

```php
<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();                       // or require_admin()
$id   = input_int('id') ?? abort(404);

$pin = db_query('SELECT * FROM pins WHERE id = ?', [$id])->fetch() ?: abort(404);

if (is_post()) {
    csrf_verify();
    require_owner($pin['user_id']);            // authorization on the server
    $title = clean_text(input('title'));
    $errors = array_filter(['title' => v_length($title, 'Title', 1, 100)]);
    if (!$errors) {
        // Whitelisted columns only; never build UPDATE from $_POST keys.
        db_query('UPDATE pins SET title = ? WHERE id = ?', [$title, $id]);
        flash('success', 'Saved.');
        redirect('/pin.php?id=' . $id);
    }
}

view('header', ['title' => $pin['title']]);
?>
<form method="post">
    <?= csrf_field() ?>
    <input name="title" value="<?= e($pin['title']) ?>">
</form>
<?php view('footer'); ?>
```

## Foundation API

| Function | Purpose |
|---|---|
| `e($v)` | HTML-escape. Use on all user data in output |
| `db_query($sql, $params)` | Prepared statement; ints bound as `PARAM_INT` |
| `csrf_field()` / `csrf_verify()` | CSRF token in forms / check in POST handlers |
| `current_user()`, `require_login()`, `require_admin()`, `require_owner($id)` | Authn/authz |
| `login_user($id, $hash)` / `logout_user()` | Session regeneration + CSRF rotation + password fingerprint |
| `verify_current_password($id, $pw)` | Re-auth for sensitive settings |
| `input_raw($k)` | Untrimmed input (passwords) |
| `field_error($errors, $f)` / `field_aria($errors, $f)` | Accessible per-field errors |
| `reject_oversized_post()` | 413 instead of a confusing CSRF error on huge uploads |
| `hash_password()` / `verify_password()` | Argon2id, with auto-rehash |
| `input($k)`, `input_int($k)` | Scalar-only request input |
| `v_length`, `v_username`, `v_email`, `v_password`, `v_in`, `v_url_optional` | Validators (return error or null) |
| `like_escape($q)` | Escape `%`/`_` for `LIKE ? ESCAPE '\\'` |
| `store_uploaded_image($_FILES['x'], 'pins')` | finfo + GD re-encode + random name |
| `delete_uploaded_image($path)` | Deletes only well-formed upload paths |
| `log_action($action, $detail)` | Append-only audit log |
| `redirect($path)` | Internal paths only (no open redirect) |
| `abort($code)` | Generic error page, no internals leaked |

## Security baseline already in place

- PDO with real prepared statements (`EMULATE_PREPARES=false`). The app DB user has only SELECT/INSERT/UPDATE/DELETE, and `audit_logs` is INSERT-only
- Session cookie is `HttpOnly`, `SameSite=Lax`, and `Secure` (via `COOKIE_SECURE=1`), with strict mode, idle timeout, and ID regeneration on login
- Headers: strict CSP (`'self'`, no inline script/style), `nosniff`, `X-Frame-Options: DENY`, Referrer-Policy, Permissions-Policy, COOP
- Errors are logged and never displayed. `expose_php=Off`, `ServerTokens Prod`, no directory listing, dotfiles denied
- Uploads cannot execute PHP and only serve random-hex image filenames, sandboxed by CSP
- Code is mounted read-only into the container. Only the uploads volume is writable
- The DB port is not published. The app binds to `127.0.0.1` only

## Auth behaviour

- Login accepts a username or an email. Every failure (unknown user, wrong password, locked, blocked) returns the same message, and each one runs one Argon2id verify so response time doesn't reveal which case happened
- After 5 failed attempts the account is locked for 15 minutes. The counter update is a single atomic `UPDATE`. While locked, the real password isn't checked at all
- Changing the password logs out every other session, because each session stores a fingerprint of the password hash. The current session stays logged in
- Changing email, changing password and deleting the account all require the current password. The last active admin can't delete their own account
- **Accepted risk:** the per-account lockout can be abused to lock out a known user (DoS). Mitigations such as per-IP throttling or CAPTCHA are out of scope

## Definition of Done (per feature)

- [ ] All queries use prepared statements (no string concatenation)
- [ ] All user output is escaped with `e()`
- [ ] All POST forms use CSRF. No state changes via GET
- [ ] Authorization is checked on the server (ownership, private boards, admin role)
- [ ] Input is validated on the server (length, format, whitelist)
- [ ] Updates use whitelisted columns (users can't set `role`, `user_id`, ...)
- [ ] No DB errors are shown to the user

## Team conventions

- Branch per feature (`feature/auth`, `feature/pins`, ...). Merge to `main` via PR with at least 1 review against the checklist above
- Code and comments are in English
- No inline `<script>`/`style=""`/`onclick` (CSP blocks them). Put JS in `public/assets/js/app.js` using `data-*` attributes
- Never commit `.env`, `config/config.php` or uploads
