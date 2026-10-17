<?php
/**
 * Admin: block / unblock a user.
 *
 * Admin accounts (including yourself) can't be blocked from the UI, so one admin
 * can't lock out another. Blocking takes effect on the user's next request,
 * because current_user() re-checks is_active every time.
 */

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

require_method('POST');
$admin = require_admin();
csrf_verify();

$action = input('action');
if (!in_array($action, ['block', 'unblock'], true)) {
    abort(400);
}
$targetId = input_int('id', 'POST') ?? abort(404);

$target = db_query('SELECT id, username, role, is_active FROM users WHERE id = ?', [$targetId])->fetch() ?: abort(404);

if ((int) $target['id'] === (int) $admin['id'] || $target['role'] === 'admin') {
    log_action('admin_access_denied', 'tried to ' . $action . ' admin @' . $target['username']);
    flash('error', 'Admin accounts cannot be blocked here.');
    redirect('/admin/');
}

$newState = $action === 'block' ? 0 : 1;
db_query("UPDATE users SET is_active = ? WHERE id = ? AND role = 'user'", [$newState, $targetId]);

log_action($action === 'block' ? 'admin_user_blocked' : 'admin_user_unblocked', '@' . $target['username'] . ' (#' . $targetId . ')');
flash('success', '@' . $target['username'] . ($action === 'block' ? ' has been blocked.' : ' has been unblocked.'));
redirect('/admin/');
