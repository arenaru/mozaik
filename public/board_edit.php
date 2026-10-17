<?php
/**
 * Edit a board. Owner only (others get 404 so private boards aren't revealed).
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$userId = (int) $user['id'];
$boardId = input_int('id', is_post() ? 'POST' : 'GET') ?? abort(404);
$board = require_own_board($boardId);

$fields = [
    'name'        => (string) $board['name'],
    'description' => (string) ($board['description'] ?? ''),
    'visibility'  => (string) $board['visibility'],
];
$errors = [];

if (is_post()) {
    csrf_verify();
    [$fields, $errors] = validate_board_fields($userId, $boardId);

    if (!$errors) {
        db_query(
            'UPDATE boards SET name = ?, description = ?, visibility = ? WHERE id = ? AND user_id = ?',
            [$fields['name'], $fields['description'] === '' ? null : $fields['description'], $fields['visibility'], $boardId, $userId]
        );
        flash('success', 'Board updated.');
        redirect('/board.php?id=' . $boardId);
    }
    http_response_code(422);
}

view('header', ['title' => 'Edit board']);
?>
<form class="form card form-wide" method="post" action="/board_edit.php" novalidate>
    <h1>Edit board</h1>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($boardId) ?>">
    <?php view('partials/board_fields', ['fields' => $fields, 'errors' => $errors]); ?>
    <div class="actions">
        <a class="btn" href="/board.php?id=<?= e($boardId) ?>">Cancel</a>
        <button type="submit" class="btn btn-primary">Save changes</button>
    </div>
</form>
<?php view('footer'); ?>
