<?php
/**
 * Feed placeholder (module C replaces this with the real feed + pagination).
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$limit = 30;
$pins = db_query(
    'SELECT id, title, image_path FROM pins ORDER BY created_at DESC, id DESC LIMIT ?',
    [$limit]
)->fetchAll();

view('header', ['title' => 'Home']);
?>
<h1 class="visually-hidden">Latest pins</h1>

<?php if (!$pins): ?>
    <section class="empty-state">
        <p>No pins yet.</p>
        <?php if (is_logged_in()): ?>
            <a class="btn btn-primary" href="/pin_create.php">Create the first pin</a>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="masonry">
        <?php foreach ($pins as $pin): ?>
            <?php view('partials/pin_card', ['pin' => $pin]); ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php view('footer'); ?>
