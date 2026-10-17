<?php
/**
 * Edit a pin's text fields. Owner only. The image can't be replaced
 * (delete and re-create instead), which keeps the upload surface small.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$pinId = input_int('id', is_post() ? 'POST' : 'GET') ?? abort(404);
$pin = require_own_pin($pinId);

$fields = [
    'title'       => (string) $pin['title'],
    'description' => (string) ($pin['description'] ?? ''),
    'source_url'  => (string) ($pin['source_url'] ?? ''),
];
$errors = [];

if (is_post()) {
    csrf_verify();
    [$fields, $errors] = validate_pin_fields();

    if (!$errors) {
        // Hardcoded columns; user_id in WHERE as defense in depth.
        db_query(
            'UPDATE pins SET title = ?, description = ?, source_url = ? WHERE id = ? AND user_id = ?',
            [
                $fields['title'],
                $fields['description'] === '' ? null : $fields['description'],
                $fields['source_url'] === '' ? null : $fields['source_url'],
                $pinId,
                (int) $user['id'],
            ]
        );
        flash('success', 'Pin updated.');
        redirect('/pin.php?id=' . $pinId);
    }
    http_response_code(422);
}

view('header', ['title' => 'Edit pin']);
?>
<form class="form card form-wide" method="post" action="/pin_edit.php" novalidate>
    <h1>Edit pin</h1>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($pinId) ?>">

    <img class="pin-edit-thumb" src="<?= e(upload_url($pin['image_path'])) ?>" alt="">

    <?php view('partials/pin_fields', ['fields' => $fields, 'errors' => $errors]); ?>

    <div class="actions">
        <a class="btn" href="/pin.php?id=<?= e($pinId) ?>">Cancel</a>
        <button type="submit" class="btn btn-primary">Save changes</button>
    </div>
</form>
<?php view('footer'); ?>
