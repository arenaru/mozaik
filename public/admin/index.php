<?php
/**
 * Admin: user list with block / unblock.
 */

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

$admin = require_admin();

$q = clean_text(input('q', 'GET'));
if (mb_strlen($q) > 100) {
    $q = '';
}
$status = input('status', 'GET');
if (!in_array($status, ['all', 'active', 'blocked'], true)) {
    $status = 'all';
}
$page = current_page();

$like = '%' . like_escape($q) . '%';
$rows = db_query(
    'SELECT u.id, u.username, u.email, u.display_name, u.role, u.is_active, u.locked_until,
            u.created_at, COUNT(p.id) AS pin_count
       FROM users u
       LEFT JOIN pins p ON p.user_id = u.id
      WHERE (? = \'\' OR u.username LIKE ? ESCAPE \'\\\\\' OR u.email LIKE ? ESCAPE \'\\\\\')
        AND (? = \'all\' OR (? = \'active\' AND u.is_active = 1) OR (? = \'blocked\' AND u.is_active = 0))
      GROUP BY u.id
      ORDER BY u.id DESC
      LIMIT ? OFFSET ?',
    [$q, $like, $like, $status, $status, $status, PAGE_SIZE + 1, ($page - 1) * PAGE_SIZE]
)->fetchAll();
[$users, $hasMore] = split_page($rows);
$hasMore = $hasMore && $page < MAX_SEARCH_PAGE;

view('header', ['title' => 'Admin · Users']);
view('partials/admin_nav', ['active' => 'users']);
?>
<form class="admin-filter" method="get" action="/admin/">
    <label>
        Search
        <input type="search" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Username or email">
    </label>
    <label>
        Status
        <select name="status">
            <?php foreach (['all' => 'All', 'active' => 'Active', 'blocked' => 'Blocked'] as $key => $label): ?>
                <option value="<?= e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="btn">Filter</button>
</form>

<?php if (!$users): ?>
    <p class="empty-state">No users found.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">User</th>
                <th scope="col">Email</th>
                <th scope="col">Role</th>
                <th scope="col">Pins</th>
                <th scope="col">Status</th>
                <th scope="col">Joined</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <?php
                $active = (int) $row['is_active'] === 1;
                $locked = $row['locked_until'] !== null && strtotime((string) $row['locked_until'] . ' UTC') > time();
                $canToggle = $row['role'] !== 'admin' && (int) $row['id'] !== (int) $admin['id'];
                ?>
                <tr>
                    <td><?= e($row['id']) ?></td>
                    <td>
                        <a href="/profile.php?u=<?= e(rawurlencode((string) $row['username'])) ?>">@<?= e($row['username']) ?></a>
                        <?php if (!empty($row['display_name']) && $row['display_name'] !== $row['username']): ?>
                            <br><span class="muted"><?= e($row['display_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($row['email']) ?></td>
                    <td><?= e($row['role']) ?></td>
                    <td><?= e($row['pin_count']) ?></td>
                    <td>
                        <?= $active ? 'Active' : '<span class="badge badge-danger">Blocked</span>' ?>
                        <?php if ($locked): ?><span class="badge">Locked</span><?php endif; ?>
                    </td>
                    <td><?= e(date('Y-m-d', strtotime((string) $row['created_at']))) ?></td>
                    <td>
                        <?php if ($canToggle): ?>
                            <form method="post" action="/admin/user_status.php" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                <input type="hidden" name="action" value="<?= $active ? 'block' : 'unblock' ?>">
                                <button type="submit" class="btn <?= $active ? 'btn-danger' : '' ?>"
                                        data-confirm="<?= e(($active ? 'Block' : 'Unblock') . ' @' . $row['username'] . '?') ?>">
                                    <?= $active ? 'Block' : 'Unblock' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($hasMore): ?>
        <p class="load-more">
            <a class="btn" href="<?= e(url_with('/admin/', ['q' => $q, 'status' => $status !== 'all' ? $status : null, 'page' => $page + 1])) ?>">Next page</a>
        </p>
    <?php endif; ?>
<?php endif; ?>
<?php view('footer'); ?>
