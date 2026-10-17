<?php
/**
 * Search pins (title + description) or users (username / display name).
 *
 * User input only ever reaches SQL as a bound parameter, with LIKE wildcards
 * escaped so "%" or "_" are matched literally (no wildcard DoS / match-all).
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$q = clean_text(input('q', 'GET'));
$type = input('type', 'GET');
if (!in_array($type, ['pins', 'users'], true)) {
    $type = 'pins';
}

$error = null;
if ($q !== '' && ($err = v_length($q, 'Search', 1, 100)) !== null) {
    $error = $err;
    $q = '';
}

$page = current_page();
$offset = ($page - 1) * PAGE_SIZE;
$results = [];
$hasMore = false;

if ($q !== '') {
    $like = '%' . like_escape($q) . '%';
    // PHP '\\\\' -> SQL '\\' -> a single backslash escape character.
    if ($type === 'pins') {
        $rows = db_query(
            'SELECT p.id, p.title, p.image_path
               FROM pins p
               JOIN users u ON u.id = p.user_id AND u.is_active = 1
              WHERE p.title LIKE ? ESCAPE \'\\\\\' OR p.description LIKE ? ESCAPE \'\\\\\'
              ORDER BY p.id DESC
              LIMIT ? OFFSET ?',
            [$like, $like, PAGE_SIZE + 1, $offset]
        )->fetchAll();
    } else {
        $rows = db_query(
            'SELECT username, display_name, bio, avatar_path
               FROM users
              WHERE is_active = 1
                AND (username LIKE ? ESCAPE \'\\\\\' OR display_name LIKE ? ESCAPE \'\\\\\')
              ORDER BY username = ? DESC, username
              LIMIT ? OFFSET ?',
            [$like, $like, $q, PAGE_SIZE + 1, $offset]
        )->fetchAll();
    }
    [$results, $hasMore] = split_page($rows);
}

$hasMore = $hasMore && $page < MAX_SEARCH_PAGE;
$moreUrl = $hasMore ? url_with('/search.php', ['q' => $q, 'type' => $type, 'page' => $page + 1]) : null;

view('header', ['title' => $q !== '' ? 'Search: ' . $q : 'Search']);
?>
<div class="search-page">
    <h1><?= $q !== '' ? 'Results for “' . e($q) . '”' : 'Search' ?></h1>

    <?php if ($error): ?>
        <div class="flash flash-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <nav class="tabs" aria-label="Search type">
        <?php foreach (['pins' => 'Pins', 'users' => 'People'] as $key => $label): ?>
            <a class="tab<?= $type === $key ? ' is-active' : '' ?>"
               href="<?= e(url_with('/search.php', ['q' => $q, 'type' => $key])) ?>"
               <?= $type === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($q === ''): ?>
        <p class="empty-state">Type something in the search bar above.</p>
    <?php elseif ($type === 'pins'): ?>
        <?php view('partials/pin_grid', [
            'pins'      => $results,
            'moreUrl'   => $moreUrl,
            'emptyText' => 'No pins match your search.',
        ]); ?>
    <?php else: ?>
        <?php if (!$results): ?>
            <p class="empty-state">No people match your search.</p>
        <?php else: ?>
            <ul class="user-list">
                <?php foreach ($results as $row): ?>
                    <li>
                        <a class="author" href="/profile.php?u=<?= e(rawurlencode((string) $row['username'])) ?>">
                            <img class="avatar avatar-sm" src="<?= e(upload_url($row['avatar_path'])) ?>" alt="" width="40" height="40">
                            <span>
                                <strong><?= e($row['display_name'] ?: $row['username']) ?></strong>
                                <span class="muted">@<?= e($row['username']) ?></span>
                                <?php if (!empty($row['bio'])): ?>
                                    <br><span class="muted"><?= e(mb_strimwidth((string) $row['bio'], 0, 120, '…')) ?></span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($moreUrl): ?>
                <p class="load-more"><a class="btn" href="<?= e($moreUrl) ?>">Load more</a></p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php view('footer'); ?>
