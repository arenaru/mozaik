<?php
/**
 * Global feed: newest pins, keyset pagination via ?before=<pin id>.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$before = feed_cursor();

// One query shape; the cursor is a bound int (no SQL built from input).
$rows = db_query(
    'SELECT p.id, p.title, p.image_path
       FROM pins p
       JOIN users u ON u.id = p.user_id AND u.is_active = 1
      WHERE (? IS NULL OR p.id < ?)
      ORDER BY p.id DESC
      LIMIT ?',
    [$before, $before, PAGE_SIZE + 1]
)->fetchAll();

[$pins, $hasMore] = split_page($rows);
$moreUrl = $hasMore ? url_with('/', ['before' => (int) end($pins)['id']]) : null;

view('header', ['title' => 'Home']);
?>
<h1 class="visually-hidden">Latest pins</h1>

<?php if (!$pins && $before === null && is_logged_in()): ?>
    <section class="empty-state">
        <p>No pins yet.</p>
        <a class="btn btn-primary" href="/pin_create.php">Create the first pin</a>
    </section>
<?php else: ?>
    <?php view('partials/pin_grid', ['pins' => $pins, 'moreUrl' => $moreUrl]); ?>
<?php endif; ?>

<?php view('footer'); ?>
