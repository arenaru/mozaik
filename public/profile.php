<?php
/**
 * Public profile: avatar, name, bio + tabs "Created" (pins) and "Boards".
 * Private boards are listed only for the owner. Blocked users' profiles are
 * 404 for everyone except admins.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$username = input('u', 'GET');
if (v_username($username) !== null) {
    abort(404);
}

$profile = db_query(
    'SELECT id, username, display_name, bio, avatar_path, role, is_active, created_at
       FROM users WHERE username = ?',
    [$username]
)->fetch() ?: abort(404);

if ((int) $profile['is_active'] !== 1 && !is_admin()) {
    abort(404);
}

$profileId = (int) $profile['id'];
$isOwner = current_user_id() === $profileId;
$tab = input('tab', 'GET') === 'boards' ? 'boards' : 'created';
$name = $profile['display_name'] ?: $profile['username'];

$pins = [];
$moreUrl = null;
$boards = [];

if ($tab === 'created') {
    $before = feed_cursor();
    $rows = db_query(
        'SELECT id, title, image_path FROM pins
          WHERE user_id = ? AND (? IS NULL OR id < ?)
          ORDER BY id DESC
          LIMIT ?',
        [$profileId, $before, $before, PAGE_SIZE + 1]
    )->fetchAll();
    [$pins, $hasMore] = split_page($rows);
    if ($hasMore) {
        $moreUrl = url_with('/profile.php', ['u' => $profile['username'], 'before' => (int) end($pins)['id']]);
    }
} else {
    // Visibility filter is in SQL: non-owners never even load private boards.
    $boards = db_query(
        "SELECT b.id, b.name, b.visibility,
                COUNT(bp.pin_id) AS pin_count,
                (SELECT p.image_path FROM board_pins bp2
                   JOIN pins p  ON p.id = bp2.pin_id
                   JOIN users pu ON pu.id = p.user_id AND pu.is_active = 1
                  WHERE bp2.board_id = b.id ORDER BY bp2.saved_at DESC LIMIT 1) AS cover
           FROM boards b
           LEFT JOIN board_pins bp ON bp.board_id = b.id
          WHERE b.user_id = ? AND (b.visibility = 'public' OR ? = 1)
          GROUP BY b.id
          ORDER BY b.name",
        [$profileId, $isOwner ? 1 : 0]
    )->fetchAll();
}

view('header', ['title' => $name . ' (@' . $profile['username'] . ')']);
?>
<header class="profile-header">
    <img class="avatar avatar-lg" src="<?= e(upload_url($profile['avatar_path'])) ?>" alt="" width="120" height="120">
    <h1><?= e($name) ?></h1>
    <p class="muted">@<?= e($profile['username']) ?>
        <?php if ($profile['role'] === 'admin'): ?><span class="badge">Admin</span><?php endif; ?>
        <?php if ((int) $profile['is_active'] !== 1): ?><span class="badge badge-danger">Blocked</span><?php endif; ?>
    </p>
    <?php if (!empty($profile['bio'])): ?>
        <p class="profile-bio"><?= nl2br(e($profile['bio'])) ?></p>
    <?php endif; ?>
    <?php if ($isOwner): ?>
        <p class="actions actions-center">
            <a class="btn" href="/settings.php">Edit profile</a>
            <a class="btn" href="/board_create.php">New board</a>
        </p>
    <?php endif; ?>
</header>

<nav class="tabs" aria-label="Profile sections">
    <?php foreach (['created' => 'Created', 'boards' => 'Boards'] as $key => $label): ?>
        <a class="tab<?= $tab === $key ? ' is-active' : '' ?>"
           href="<?= e(url_with('/profile.php', ['u' => $profile['username'], 'tab' => $key === 'boards' ? 'boards' : null])) ?>"
           <?= $tab === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'created'): ?>
    <?php view('partials/pin_grid', [
        'pins'      => $pins,
        'moreUrl'   => $moreUrl,
        'emptyText' => $isOwner ? 'You have not created any pins yet.' : 'No pins yet.',
    ]); ?>
<?php elseif (!$boards): ?>
    <p class="empty-state"><?= $isOwner ? 'You have no boards yet.' : 'No public boards.' ?></p>
<?php else: ?>
    <ul class="board-grid">
        <?php foreach ($boards as $board): ?>
            <li>
                <a class="board-card" href="/board.php?id=<?= e((int) $board['id']) ?>">
                    <span class="board-cover">
                        <?php if ($board['cover']): ?>
                            <img src="<?= e(upload_url($board['cover'])) ?>" alt="" loading="lazy">
                        <?php endif; ?>
                    </span>
                    <strong><?= e($board['name']) ?></strong>
                    <?php if ($board['visibility'] === 'private'): ?><span class="badge">Private</span><?php endif; ?>
                    <span class="muted"><?= e((int) $board['pin_count']) ?> <?= (int) $board['pin_count'] === 1 ? 'pin' : 'pins' ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php view('footer'); ?>
