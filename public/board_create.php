<?php
/**
 * Create a board.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$userId = (int) $user['id'];

$fields = ['name' => '', 'description' => '', 'visibility' => 'public'];
$errors = [];

if (is_post()) {
    csrf_verify();
    [$fields, $errors] = validate_board_fields($userId);

    if (!$errors) {
        // user_id from the session; visibility already whitelisted.
        db_query(
            'INSERT INTO boards (user_id, name, description, visibility) VALUES (?, ?, ?, ?)',
            [$userId, $fields['name'], $fields['description'] === '' ? null : $fields['description'], $fields['visibility']]
        );
        $boardId = (int) db()->lastInsertId();
        flash('success', 'Board created.');
        redirect('/board.php?id=' . $boardId);
    }
    http_response_code(422);
}

view('header', ['title' => 'Create board']);
?>
<form class="form card form-wide" method="post" action="/board_create.php" novalidate>
    <h1>Create board</h1>
    <?= csrf_field() ?>
    <?php view('partials/board_fields', ['fields' => $fields, 'errors' => $errors]); ?>
    <button type="submit" class="btn btn-primary">Create</button>
</form>
<?php view('footer'); ?>
