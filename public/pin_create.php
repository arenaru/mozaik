<?php
/**
 * Create a pin: one image (JPG/PNG/WebP, max 5 MB), title, description,
 * optional source link and optional target board.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$userId = (int) $user['id'];
$boards = boards_of_user($userId);

$fields = ['title' => '', 'description' => '', 'source_url' => ''];
$boardInput = '';
$errors = [];

if (is_post()) {
    reject_oversized_post();
    csrf_verify();

    [$fields, $errors] = validate_pin_fields();

    $boardInput = input('board_id');
    $boardId = owned_board_id_from_input($boardInput, $userId);
    if ($boardId === false) {
        $errors['board_id'] = 'Please choose one of your boards.';
    }

    $imagePath = null;
    if (!$errors) {
        try {
            $imagePath = store_uploaded_image($_FILES['image'] ?? null, 'pins');
        } catch (UploadException $e) {
            $errors['image'] = $e->getMessage();
        }
    }

    if (!$errors && $imagePath !== null) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            // user_id comes from the session, never from the form.
            db_query(
                'INSERT INTO pins (user_id, title, description, image_path, source_url) VALUES (?, ?, ?, ?, ?)',
                [
                    $userId,
                    $fields['title'],
                    $fields['description'] === '' ? null : $fields['description'],
                    $imagePath,
                    $fields['source_url'] === '' ? null : $fields['source_url'],
                ]
            );
            $pinId = (int) $pdo->lastInsertId();
            if ($boardId !== null) {
                db_query('INSERT INTO board_pins (board_id, pin_id) VALUES (?, ?)', [$boardId, $pinId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            delete_uploaded_image($imagePath); // don't leave orphan files
            throw $e;
        }

        flash('success', 'Pin created.');
        redirect('/pin.php?id=' . $pinId);
    }

    http_response_code(422);
}

view('header', ['title' => 'Create pin']);
?>
<form class="form card form-wide" method="post" action="/pin_create.php" enctype="multipart/form-data" novalidate>
    <h1>Create pin</h1>
    <?= csrf_field() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= e(config('upload.max_bytes')) ?>">

    <label>
        Image (JPG, PNG or WebP, max 5 MB)
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required<?= field_aria($errors, 'image') ?>>
        <?= field_error($errors, 'image') ?>
    </label>

    <?php view('partials/pin_fields', ['fields' => $fields, 'errors' => $errors]); ?>

    <label>
        Board (optional)
        <select name="board_id"<?= field_aria($errors, 'board_id') ?>>
            <option value="">No board</option>
            <?php foreach ($boards as $board): ?>
                <option value="<?= e($board['id']) ?>"<?= (string) $board['id'] === $boardInput ? ' selected' : '' ?>>
                    <?= e($board['name']) ?><?= $board['visibility'] === 'private' ? ' (private)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'board_id') ?>
    </label>

    <button type="submit" class="btn btn-primary">Publish</button>
</form>
<?php view('footer'); ?>
