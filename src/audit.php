<?php
/**
 * Audit log (append-only; the app DB user has no UPDATE/DELETE on audit_logs).
 *
 * Suggested actions:
 *   login_success, login_failed, login_locked, logout, register,
 *   password_changed, email_changed, account_deleted,
 *   admin_user_blocked, admin_user_unblocked, admin_pin_deleted, admin_access_denied
 *
 * Never put passwords, tokens or full session IDs in $detail.
 */

declare(strict_types=1);

function log_action(string $action, ?string $detail = null, ?int $userId = null): void
{
    try {
        if ($userId === null && PHP_SAPI !== 'cli' && isset($_SESSION['user_id']) && is_int($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        }
        db_query(
            'INSERT INTO audit_logs (user_id, action, detail, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)',
            [
                $userId,
                mb_substr($action, 0, 50),
                $detail === null ? null : mb_substr($detail, 0, 500),
                PHP_SAPI === 'cli' ? 'cli' : client_ip(),
                PHP_SAPI === 'cli' ? 'cli' : user_agent(),
            ]
        );
    } catch (Throwable $e) {
        // Logging must never break the request, but the failure itself is recorded.
        error_log('[mozaic] audit log failed for ' . $action . ': ' . $e->getMessage());
    }
}
