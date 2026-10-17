<?php
/**
 * Delete a board (owner only). Pins stay; only the board_pins links are removed (CASCADE).
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

require_method('POST');
$user = require_login();
csrf_verify();

$boardId = input_int('id', 'POST') ?? abort(404);
require_own_board($boardId);

db_query('DELETE FROM boards WHERE id = ? AND user_id = ?', [$boardId, (int) $user['id']]);

flash('success', 'Board deleted.');
redirect('/profile.php?u=' . rawurlencode((string) $user['username']) . '&tab=boards');
