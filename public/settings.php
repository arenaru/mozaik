<?php
/**
 * Account settings: profile, email, password, delete account.
 *
 * Each form posts a "form" field, matched against a fixed whitelist.
 * Every UPDATE uses a hardcoded column list (no mass assignment), and the
 * target row is always the logged-in user's id from the session, never from input.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$userId = (int) $user['id'];

$errors = ['profile' => [], 'email' => [], 'password' => [], 'delete' => []];
$profileInput = [
    'display_name' => (string) ($user['display_name'] ?? ''),
    'bio'          => (string) ($user['bio'] ?? ''),
];
$emailInput = (string) $user['email'];

if (is_post()) {
    reject_oversized_post();
    csrf_verify();

    $form = input('form');
    if (!in_array($form, ['profile', 'email', 'password', 'delete'], true)) {
        abort(400);
    }

    // ---------------------------------------------------------------- profile
    if ($form === 'profile') {
        $displayName = clean_text(input('display_name'));
        $bio = clean_text(input('bio'));
        $removeAvatar = input('remove_avatar') === '1';
        $profileInput = ['display_name' => $displayName, 'bio' => $bio];

        $err = array_filter([
            'display_name' => v_length($displayName, 'Display name', 1, 50),
            'bio'          => v_length($bio, 'Bio', 0, 300),
        ]);

        $newAvatar = null;
        $avatarFile = $_FILES['avatar'] ?? null;
        $hasUpload = is_array($avatarFile) && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$err && $hasUpload) {
            try {
                $newAvatar = store_uploaded_image($avatarFile, 'avatars', 512);
            } catch (UploadException $e) {
                $err['avatar'] = $e->getMessage();
            }
        }

        if (!$err) {
            $oldAvatar = $user['avatar_path'];
            $avatarPath = $newAvatar ?? ($removeAvatar ? null : $oldAvatar);
            db_query(
                'UPDATE users SET display_name = ?, bio = ?, avatar_path = ? WHERE id = ?',
                [$displayName, $bio === '' ? null : $bio, $avatarPath, $userId]
            );
            // Remove the old file only after the DB update succeeded.
            if ($oldAvatar !== null && $oldAvatar !== $avatarPath) {
                delete_uploaded_image($oldAvatar);
            }
            flash('success', 'Profile updated.');
            redirect('/settings.php');
        }
        $errors['profile'] = $err;
    }

    // ------------------------------------------------------------------ email
    if ($form === 'email') {
        $newEmail = strtolower(input('email'));
        $emailInput = $newEmail;
        $err = array_filter(['email' => v_email($newEmail)]);

        if (!$err && !verify_current_password($userId, input_raw('current_password'))) {
            $err['current_password'] = 'Current password is incorrect.';
        }
        if (!$err && $newEmail === strtolower((string) $user['email'])) {
            $err['email'] = 'That is already your email.';
        }
        if (!$err) {
            try {
                db_query('UPDATE users SET email = ? WHERE id = ?', [$newEmail, $userId]);
                log_action('email_changed', mask_email((string) $user['email']) . ' -> ' . mask_email($newEmail), $userId);
                flash('success', 'Email updated.');
                redirect('/settings.php');
            } catch (PDOException $e) {
                if (!is_duplicate_key($e)) {
                    throw $e;
                }
                $err['email'] = 'That email is already registered.';
            }
        }
        $errors['email'] = $err;
    }

    // --------------------------------------------------------------- password
    if ($form === 'password') {
        $current = input_raw('current_password');
        $new = input_raw('new_password');
        $confirm = input_raw('new_password_confirm');

        $err = array_filter([
            'new_password'         => v_password($new),
            'new_password_confirm' => $new !== $confirm ? 'Passwords do not match.' : null,
        ]);
        if (!$err && !verify_current_password($userId, $current)) {
            $err['current_password'] = 'Current password is incorrect.';
        }
        if (!$err && hash_equals($current, $new)) {
            $err['new_password'] = 'New password must be different from the current one.';
        }
        if (!$err) {
            $hash = hash_password($new);
            db_query(
                'UPDATE users SET password_hash = ?, failed_login_count = 0, locked_until = NULL WHERE id = ?',
                [$hash, $userId]
            );
            // Keeps this session; every other session fails the fingerprint check.
            refresh_session_after_password_change($hash);
            log_action('password_changed', null, $userId);
            flash('success', 'Password changed. You have been logged out on other devices.');
            redirect('/settings.php');
        }
        $errors['password'] = $err;
    }

    // ----------------------------------------------------------------- delete
    if ($form === 'delete') {
        $err = [];
        if (input('confirm_delete') !== '1') {
            $err['confirm_delete'] = 'Please tick the box to confirm.';
        }
        if (!$err && !verify_current_password($userId, input_raw('current_password'))) {
            $err['delete_password'] = 'Password is incorrect.';
        }
        if (!$err && $user['role'] === 'admin') {
            $otherAdmins = (int) db_query(
                "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?",
                [$userId]
            )->fetchColumn();
            if ($otherAdmins === 0) {
                $err['delete_password'] = 'You are the last active admin and cannot delete this account.';
            }
        }

        if (!$err) {
            // Collect files first; the rows disappear with ON DELETE CASCADE.
            $files = db_query('SELECT image_path FROM pins WHERE user_id = ?', [$userId])->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($user['avatar_path'])) {
                $files[] = $user['avatar_path'];
            }

            db_query('DELETE FROM users WHERE id = ?', [$userId]);
            foreach ($files as $file) {
                delete_uploaded_image((string) $file);
            }

            log_action('account_deleted', 'username: ' . $user['username'] . ', files removed: ' . count($files), $userId);
            logout_user();
            flash('success', 'Your account has been deleted.');
            redirect('/');
        }
        $errors['delete'] = $err;
    }

    http_response_code(422);
}

view('header', ['title' => 'Settings']);
?>
<div class="settings">
    <h1>Settings</h1>

    <!-- Profile -->
    <section class="card" aria-labelledby="h-profile">
        <h2 id="h-profile">Profile</h2>
        <?php $err = $errors['profile']; ?>
        <form class="form" method="post" action="/settings.php" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="profile">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= e(config('upload.max_bytes')) ?>">

            <div class="avatar-row">
                <img class="avatar" src="<?= e(upload_url($user['avatar_path'])) ?>" alt="" width="80" height="80">
                <label>
                    Avatar (JPG, PNG or WebP, max 5 MB)
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"<?= field_aria($err, 'avatar') ?>>
                    <?= field_error($err, 'avatar') ?>
                </label>
            </div>
            <?php if (!empty($user['avatar_path'])): ?>
                <label class="checkbox">
                    <input type="checkbox" name="remove_avatar" value="1"> Remove current avatar
                </label>
            <?php endif; ?>

            <label>
                Display name
                <input type="text" name="display_name" value="<?= e($profileInput['display_name']) ?>"
                       maxlength="50" required<?= field_aria($err, 'display_name') ?>>
                <?= field_error($err, 'display_name') ?>
            </label>

            <label>
                Bio
                <textarea name="bio" rows="3" maxlength="300"<?= field_aria($err, 'bio') ?>><?= e($profileInput['bio']) ?></textarea>
                <?= field_error($err, 'bio') ?>
            </label>

            <p class="muted">Username: <strong><?= e($user['username']) ?></strong> (cannot be changed)</p>
            <button type="submit" class="btn btn-primary">Save profile</button>
        </form>
    </section>

    <!-- Email -->
    <section class="card" aria-labelledby="h-email">
        <h2 id="h-email">Email</h2>
        <?php $err = $errors['email']; ?>
        <form class="form" method="post" action="/settings.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="email">
            <label>
                New email
                <input type="email" name="email" value="<?= e($emailInput) ?>" maxlength="255"
                       autocomplete="email" required<?= field_aria($err, 'email') ?>>
                <?= field_error($err, 'email') ?>
            </label>
            <label>
                Current password
                <input type="password" name="current_password" maxlength="128"
                       autocomplete="current-password" required<?= field_aria($err, 'current_password') ?>>
                <?= field_error($err, 'current_password') ?>
            </label>
            <button type="submit" class="btn btn-primary">Change email</button>
        </form>
    </section>

    <!-- Password -->
    <section class="card" aria-labelledby="h-password">
        <h2 id="h-password">Password</h2>
        <?php $err = $errors['password']; ?>
        <form class="form" method="post" action="/settings.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="password">
            <input type="text" name="username" value="<?= e($user['username']) ?>" autocomplete="username" hidden>
            <label>
                Current password
                <input type="password" name="current_password" maxlength="128"
                       autocomplete="current-password" required<?= field_aria($err, 'current_password') ?>>
                <?= field_error($err, 'current_password') ?>
            </label>
            <label>
                New password
                <input type="password" name="new_password" minlength="8" maxlength="128"
                       autocomplete="new-password" required<?= field_aria($err, 'new_password') ?>>
                <?= field_error($err, 'new_password') ?>
            </label>
            <label>
                Confirm new password
                <input type="password" name="new_password_confirm" minlength="8" maxlength="128"
                       autocomplete="new-password" required<?= field_aria($err, 'new_password_confirm') ?>>
                <?= field_error($err, 'new_password_confirm') ?>
            </label>
            <button type="submit" class="btn btn-primary">Change password</button>
        </form>
    </section>

    <!-- Delete -->
    <section class="card card-danger" aria-labelledby="h-delete">
        <h2 id="h-delete">Delete account</h2>
        <?php $err = $errors['delete']; ?>
        <p>This permanently deletes your account, pins and boards. This cannot be undone.</p>
        <form class="form" method="post" action="/settings.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="delete">
            <label>
                Password
                <input type="password" name="current_password" maxlength="128"
                       autocomplete="current-password" required<?= field_aria($err, 'delete_password') ?>>
                <?= field_error($err, 'delete_password') ?>
            </label>
            <label class="checkbox">
                <input type="checkbox" name="confirm_delete" value="1" required<?= field_aria($err, 'confirm_delete') ?>>
                I understand this cannot be undone.
            </label>
            <?= field_error($err, 'confirm_delete') ?>
            <button type="submit" class="btn btn-danger" data-confirm="Delete your account permanently?">Delete account</button>
        </form>
    </section>
</div>
<?php view('footer'); ?>
