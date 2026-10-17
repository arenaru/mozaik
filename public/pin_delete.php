<?php
/**
 * Delete a pin. Allowed for the owner, or an admin (moderation, audited).
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

require_method('POST');
$user = require_login();
csrf_verify();

$pinId = input_int('id', 'POST') ?? abort(404);
$pin = find_pin($pinId) ?? abort(404);

$isOwner = (int) $pin['user_id'] === (int) $user['id'];
if (!$isOwner && $user['role'] !== 'admin') {
    abort(403);
}

db_query('DELETE FROM pins WHERE id = ?', [$pinId]); // board_pins rows cascade
delete_uploaded_image((string) $pin['image_path']);

if ($isOwner) {
    log_action('pin_deleted', 'pin #' . $pinId);
    flash('success', 'Pin deleted.');
    redirect('/profile.php?u=' . rawurlencode((string) $user['username']));
}

log_action(
    'admin_pin_deleted',
    'pin #' . $pinId . ' "' . mb_substr((string) $pin['title'], 0, 60) . '" by ' . $pin['username']
);
flash('success', 'Pin removed by moderator.');
redirect('/');
