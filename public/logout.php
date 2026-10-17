<?php
/**
 * Log out. POST + CSRF only, so another site can't log the user out via <img src>.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

require_method('POST');
csrf_verify();

$userId = current_user_id();
if ($userId !== null) {
    log_action('logout', null, $userId);
}
logout_user();
flash('success', 'You have been logged out.');
redirect('/');
