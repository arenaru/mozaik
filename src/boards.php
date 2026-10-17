<?php
/**
 * Board helpers + pagination helpers for pin grids.
 *
 * Visibility rule: a private board is visible ONLY to its owner (admins included
 * in "not owner"). Non-visible boards return 404, not 403, so their existence
 * isn't revealed (IDOR / enumeration).
 */

declare(strict_types=1);

const BOARD_VISIBILITIES = ['public', 'private'];
const PAGE_SIZE = 30;
const MAX_SEARCH_PAGE = 50;

/**
 * Load a board the current viewer is allowed to see, or null.
 * @return array<string,mixed>|null
 */
function find_visible_board(int $boardId): ?array
{
    $board = db_query(
        'SELECT b.id, b.user_id, b.name, b.description, b.visibility, b.created_at,
                u.username, u.display_name, u.is_active AS owner_active
           FROM boards b
           JOIN users u ON u.id = b.user_id
          WHERE b.id = ?',
        [$boardId]
    )->fetch();

    if (!$board) {
        return null;
    }

    $viewerId = current_user_id();
    $isOwner = $viewerId !== null && $viewerId === (int) $board['user_id'];

    if ($board['visibility'] === 'private' && !$isOwner) {
        return null;
    }
    if ((int) $board['owner_active'] !== 1 && !$isOwner && !is_admin()) {
        return null;
    }
    return $board;
}

/** Board owned by the current user, or 404. (404 instead of 403: don't reveal private boards.) */
function require_own_board(int $boardId): array
{
    $user = require_login();
    $board = db_query(
        'SELECT id, user_id, name, description, visibility, created_at FROM boards WHERE id = ? AND user_id = ?',
        [$boardId, (int) $user['id']]
    )->fetch();
    return $board ?: abort(404);
}

/** Validate board form fields. Returns [cleanFields, errors]. */
function validate_board_fields(int $userId, ?int $excludeBoardId = null): array
{
    $fields = [
        'name'        => clean_text(input('name')),
        'description' => clean_text(input('description')),
        'visibility'  => input('visibility'),
    ];
    $errors = array_filter([
        'name'        => v_length($fields['name'], 'Name', 1, 50),
        'description' => v_length($fields['description'], 'Description', 0, 300),
        'visibility'  => v_in($fields['visibility'], 'visibility', BOARD_VISIBILITIES),
    ]);

    if (!isset($errors['name'])) {
        $dup = db_query(
            'SELECT 1 FROM boards WHERE user_id = ? AND name = ? AND id <> ?',
            [$userId, $fields['name'], $excludeBoardId ?? 0]
        )->fetchColumn();
        if ($dup) {
            $errors['name'] = 'You already have a board with that name.';
        }
    }
    return [$fields, $errors];
}

/**
 * Keyset pagination cursor from ?before=<pin id>. Keyset (WHERE id < ?) instead of
 * OFFSET: stable while new pins arrive and no deep-offset scans.
 */
function feed_cursor(): ?int
{
    return input_int('before');
}

/** Current page for OFFSET-based lists (search), clamped to 1..MAX_SEARCH_PAGE. */
function current_page(): int
{
    $page = input_int('page') ?? 1;
    return min($page, MAX_SEARCH_PAGE);
}

/**
 * Fetch PAGE_SIZE + 1 rows to know whether there is a next page.
 * Returns [rows (max PAGE_SIZE), hasMore].
 */
function split_page(array $rows): array
{
    $hasMore = count($rows) > PAGE_SIZE;
    return [array_slice($rows, 0, PAGE_SIZE), $hasMore];
}

/** Build an internal URL with query params (values are URL-encoded). */
function url_with(string $path, array $params): string
{
    $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');
    return $params ? $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : $path;
}
