<?php
/**
 * Admin: read-only audit log viewer.
 *
 * The log contains attacker-controlled strings (e.g. usernames typed into the
 * login form, user agents), so every value is escaped on output.
 */

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

require_admin();

const AUDIT_ACTIONS = [
    'login_success', 'login_failed', 'login_locked', 'logout', 'register',
    'password_changed', 'email_changed', 'account_deleted', 'pin_deleted',
    'admin_user_blocked', 'admin_user_unblocked', 'admin_pin_deleted',
    'admin_access_denied', 'admin_created_cli', 'admin_promoted_cli',
];

$action = input('action', 'GET');
if (!in_array($action, AUDIT_ACTIONS, true)) {
    $action = '';
}
$userId = input_int('user_id');
$before = feed_cursor();

$rows = db_query(
    'SELECT a.id, a.user_id, a.action, a.detail, a.ip_address, a.user_agent, a.created_at, u.username
       FROM audit_logs a
       LEFT JOIN users u ON u.id = a.user_id
      WHERE (? = \'\' OR a.action = ?)
        AND (? IS NULL OR a.user_id = ?)
        AND (? IS NULL OR a.id < ?)
      ORDER BY a.id DESC
      LIMIT ?',
    [$action, $action, $userId, $userId, $before, $before, PAGE_SIZE + 1]
)->fetchAll();
[$logs, $hasMore] = split_page($rows);

$filters = ['action' => $action, 'user_id' => $userId];

view('header', ['title' => 'Admin · Audit log']);
view('partials/admin_nav', ['active' => 'audit']);
?>
<form class="admin-filter" method="get" action="/admin/audit.php">
    <label>
        Action
        <select name="action">
            <option value="">All actions</option>
            <?php foreach (AUDIT_ACTIONS as $a): ?>
                <option value="<?= e($a) ?>"<?= $action === $a ? ' selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        User ID
        <input type="number" name="user_id" min="1" value="<?= e($userId ?? '') ?>">
    </label>
    <button type="submit" class="btn">Filter</button>
    <?php if ($action !== '' || $userId !== null): ?>
        <a class="btn-link" href="/admin/audit.php">Clear</a>
    <?php endif; ?>
</form>

<?php if (!$logs): ?>
    <p class="empty-state">No log entries.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
            <tr>
                <th scope="col">Time (UTC)</th>
                <th scope="col">Action</th>
                <th scope="col">User</th>
                <th scope="col">Detail</th>
                <th scope="col">IP</th>
                <th scope="col">User agent</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="nowrap"><?= e($log['created_at']) ?></td>
                    <td><a href="<?= e(url_with('/admin/audit.php', ['action' => $log['action']])) ?>"><?= e($log['action']) ?></a></td>
                    <td>
                        <?php if ($log['user_id'] !== null): ?>
                            <a href="<?= e(url_with('/admin/audit.php', ['user_id' => (int) $log['user_id']])) ?>">#<?= e($log['user_id']) ?></a>
                            <?= $log['username'] !== null ? '@' . e($log['username']) : '<span class="muted">(deleted)</span>' ?>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="wrap"><?= e($log['detail'] ?? '') ?></td>
                    <td class="nowrap"><?= e($log['ip_address'] ?? '') ?></td>
                    <td class="ua" title="<?= e($log['user_agent'] ?? '') ?>"><?= e(mb_strimwidth((string) ($log['user_agent'] ?? ''), 0, 40, '…')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($hasMore): ?>
        <p class="load-more">
            <a class="btn" href="<?= e(url_with('/admin/audit.php', $filters + ['before' => (int) end($logs)['id']])) ?>">Older entries</a>
        </p>
    <?php endif; ?>
<?php endif; ?>
<?php view('footer'); ?>
