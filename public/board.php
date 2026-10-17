<?php
/**
 * Board page: pins saved in a board. Private boards are only visible to the owner;
 * everyone else (including admins) gets a 404.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$boardId = input_int('id') ?? abort(404);
$board = find_visible_board($boardId) ?? abort(404);

$isOwner = current_user_id() === (int) $board['user_id'];
$page = current_page();

$rows = db_query(
    'SELECT p.id, p.title, p.image_path
       FROM board_pins bp
       JOIN pins p  ON p.id = bp.pin_id
       JOIN users u ON u.id = p.user_id AND u.is_active = 1
      WHERE bp.board_id = ?
      ORDER BY bp.saved_at DESC, bp.pin_id DESC
      LIMIT ? OFFSET ?',
    [$boardId, PAGE_SIZE + 1, ($page - 1) * PAGE_SIZE]
)->fetchAll();
[$pins, $hasMore] = split_page($rows);
$hasMore = $hasMore && $page < MAX_SEARCH_PAGE;

$pinCount = (int) db_query(
    'SELECT COUNT(*) FROM board_pins bp
       JOIN pins p ON p.id = bp.pin_id
       JOIN users u ON u.id = p.user_id AND u.is_active = 1
      WHERE bp.board_id = ?',
    [$boardId]
)->fetchColumn();

$selfUrl = url_with('/board.php', ['id' => $boardId, 'page' => $page > 1 ? $page : null]);

view('header', ['title' => $board['name']]);
?>
<header class="board-header">
    <h1>
        <?= e($board['name']) ?>
        <?php if ($board['visibility'] === 'private'): ?>
            <span class="badge" title="Only you can see this board">Private</span>
        <?php endif; ?>
    </h1>
    <?php if (!empty($board['description'])): ?>
        <p><?= e($board['description']) ?></p>
    <?php endif; ?>
    <p class="muted">
        by <a href="/profile.php?u=<?= e(rawurlencode((string) $board['username'])) ?>"><?= e($board['display_name'] ?: $board['username']) ?></a>
        · <?= e($pinCount) ?> <?= $pinCount === 1 ? 'pin' : 'pins' ?>
    </p>

    <?php if ($isOwner): ?>
        <div class="actions actions-center">
            <a class="btn" href="/board_edit.php?id=<?= e($boardId) ?>">Edit board</a>
            <form method="post" action="/board_delete.php" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($boardId) ?>">
                <button type="submit" class="btn btn-danger"
                        data-confirm="Delete this board? Pins themselves are not deleted.">Delete board</button>
            </form>
        </div>
    <?php endif; ?>
</header>

<?php view('partials/pin_grid', [
    'pins'       => $pins,
    'moreUrl'    => $hasMore ? url_with('/board.php', ['id' => $boardId, 'page' => $page + 1]) : null,
    'emptyText'  => $isOwner ? 'This board is empty. Open a pin and press Save to add it here.' : 'This board is empty.',
    'removeForm' => $isOwner ? ['board_id' => $boardId, 'back' => $selfUrl] : null,
]); ?>
<?php view('footer'); ?>
