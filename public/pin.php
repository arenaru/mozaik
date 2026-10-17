<?php
/**
 * Pin detail page.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$pinId = input_int('id') ?? abort(404);
$pin = find_pin($pinId) ?? abort(404);

$user = current_user();
$isOwner = $user !== null && (int) $user['id'] === (int) $pin['user_id'];
$canDelete = $isOwner || ($user !== null && $user['role'] === 'admin');
$boards = $user ? boards_of_user((int) $user['id']) : [];
$authorName = $pin['display_name'] ?: $pin['username'];

view('header', ['title' => $pin['title']]);
?>
<article class="pin-detail card">
    <div class="pin-detail-image">
        <img src="<?= e(upload_url($pin['image_path'])) ?>" alt="<?= e($pin['title']) ?>">
    </div>

    <div class="pin-detail-body">
        <div class="pin-detail-actions">
            <?php if ($user && $boards): ?>
                <form method="post" action="/pin_save.php" class="save-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="pin_id" value="<?= e($pinId) ?>">
                    <label class="visually-hidden" for="board_id">Board</label>
                    <select id="board_id" name="board_id" required>
                        <?php foreach ($boards as $board): ?>
                            <option value="<?= e($board['id']) ?>"><?= e($board['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
            <?php elseif ($user): ?>
                <a class="btn" href="/board_create.php">Create a board to save pins</a>
            <?php else: ?>
                <a class="btn btn-primary" href="/login.php?next=<?= e(rawurlencode('/pin.php?id=' . $pinId)) ?>">Log in to save</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($pin['source_url'])): ?>
            <?php // Re-validate on output too: defense in depth if a bad URL ever reaches the DB. ?>
            <?php if (is_safe_http_url((string) $pin['source_url'])): ?>
                <a class="source-link" href="<?= e($pin['source_url']) ?>" target="_blank" rel="noopener noreferrer nofollow ugc">
                    <?= e(url_host((string) $pin['source_url'])) ?> &#8599;
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <h1><?= e($pin['title']) ?></h1>

        <?php if (!empty($pin['description'])): ?>
            <p class="pin-description"><?= nl2br(e($pin['description'])) ?></p>
        <?php endif; ?>

        <a class="author" href="/profile.php?u=<?= e(rawurlencode((string) $pin['username'])) ?>">
            <img class="avatar avatar-sm" src="<?= e(upload_url($pin['avatar_path'])) ?>" alt="" width="40" height="40">
            <span>
                <strong><?= e($authorName) ?></strong><br>
                <span class="muted">@<?= e($pin['username']) ?></span>
            </span>
        </a>

        <p class="muted">Posted <?= e(date('M j, Y', strtotime((string) $pin['created_at']))) ?></p>

        <?php if ($isOwner || $canDelete): ?>
            <div class="actions">
                <?php if ($isOwner): ?>
                    <a class="btn" href="/pin_edit.php?id=<?= e($pinId) ?>">Edit</a>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                    <form method="post" action="/pin_delete.php" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e($pinId) ?>">
                        <button type="submit" class="btn btn-danger"
                                data-confirm="<?= $isOwner ? 'Delete this pin?' : 'Remove this pin as moderator?' ?>">
                            <?= $isOwner ? 'Delete' : 'Remove (admin)' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</article>
<?php view('footer'); ?>
