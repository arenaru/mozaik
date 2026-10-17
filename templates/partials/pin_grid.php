<?php
/**
 * Masonry grid of pins + optional "Load more" link.
 * @var list<array{id:int|string,title:string,image_path:string}> $pins
 * @var string|null $moreUrl     Internal URL for the next page, or null
 * @var string|null $emptyText
 * @var array|null  $removeForm  ['board_id' => int, 'back' => string] to show "Remove" buttons
 */
$moreUrl ??= null;
$emptyText ??= 'No pins yet.';
$removeForm ??= null;
?>
<?php if (!$pins): ?>
    <p class="empty-state"><?= e($emptyText) ?></p>
<?php else: ?>
    <section class="masonry">
        <?php foreach ($pins as $pin): ?>
            <?php view('partials/pin_card', ['pin' => $pin]); ?>
            <?php if ($removeForm): ?>
                <form method="post" action="/pin_save.php" class="pin-remove-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="pin_id" value="<?= e((int) $pin['id']) ?>">
                    <input type="hidden" name="board_id" value="<?= e((int) $removeForm['board_id']) ?>">
                    <input type="hidden" name="back" value="<?= e($removeForm['back']) ?>">
                    <button type="submit" class="btn-link">Remove from board</button>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
    <?php if ($moreUrl !== null && is_safe_internal_path($moreUrl)): ?>
        <p class="load-more"><a class="btn" href="<?= e($moreUrl) ?>">Load more</a></p>
    <?php endif; ?>
<?php endif; ?>
