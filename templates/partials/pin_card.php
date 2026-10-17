<?php
/**
 * Single pin card for masonry grids.
 * @var array{id:int|string,title:string,image_path:string} $pin
 */
?>
<article class="pin-card">
    <a href="/pin.php?id=<?= e((int) $pin['id']) ?>">
        <img src="<?= e(upload_url($pin['image_path'])) ?>" alt="<?= e($pin['title']) ?>" loading="lazy">
        <span class="pin-title"><?= e($pin['title']) ?></span>
    </a>
</article>
