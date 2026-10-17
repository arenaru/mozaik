<?php
/**
 * Save a pin to one of your boards, or remove it from one.
 * Both the pin (must be visible) and the board (must be yours) are checked.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

require_method('POST');
$user = require_login();
csrf_verify();

$action = input('action');
if (!in_array($action, ['save', 'remove'], true)) {
    abort(400);
}

$pinId = input_int('pin_id', 'POST') ?? abort(404);
$pin = find_pin($pinId) ?? abort(404);

$boardId = owned_board_id_from_input(input('board_id'), (int) $user['id']);
if (!is_int($boardId)) {
    flash('error', 'Please choose one of your boards.');
    redirect('/pin.php?id=' . $pinId);
}

if ($action === 'save') {
    // INSERT IGNORE: saving twice is a no-op, not an error.
    $stmt = db_query('INSERT IGNORE INTO board_pins (board_id, pin_id) VALUES (?, ?)', [$boardId, $pinId]);
    flash('success', $stmt->rowCount() > 0 ? 'Saved to board.' : 'Already in that board.');
} else {
    db_query('DELETE FROM board_pins WHERE board_id = ? AND pin_id = ?', [$boardId, $pinId]);
    flash('success', 'Removed from board.');
}

// Return to the page the form came from, if it's an internal path.
$back = input('back');
redirect(is_safe_internal_path($back) ? $back : '/pin.php?id=' . $pinId);
