<?php
/**
 * Pin & board helpers shared by pin pages (and later the boards module).
 *
 * Pins of blocked users (is_active = 0) are hidden everywhere except from admins.
 */

declare(strict_types=1);

/**
 * Load a pin with its author. Returns null if missing or hidden.
 * @return array<string,mixed>|null
 */
function find_pin(int $pinId): ?array
{
    $pin = db_query(
        'SELECT p.id, p.user_id, p.title, p.description, p.image_path, p.source_url,
                p.created_at, p.updated_at,
                u.username, u.display_name, u.avatar_path, u.is_active AS author_active
           FROM pins p
           JOIN users u ON u.id = p.user_id
          WHERE p.id = ?',
        [$pinId]
    )->fetch();

    if (!$pin) {
        return null;
    }
    if ((int) $pin['author_active'] !== 1 && !is_admin()) {
        return null;
    }
    return $pin;
}

/** Load a pin the current user owns, or stop with 404/403. */
function require_own_pin(int $pinId): array
{
    $pin = find_pin($pinId) ?? abort(404);
    require_owner($pin['user_id']);
    return $pin;
}

/**
 * Boards owned by a user (for "save to board" selects).
 * @return list<array{id:int,name:string,visibility:string}>
 */
function boards_of_user(int $userId): array
{
    return db_query(
        'SELECT id, name, visibility FROM boards WHERE user_id = ? ORDER BY name',
        [$userId]
    )->fetchAll();
}

/**
 * Validate that a board id from input belongs to the user (IDOR guard).
 * Returns the board id, null when empty, or false when invalid/not owned.
 */
function owned_board_id_from_input(string $raw, int $userId): int|null|false
{
    if ($raw === '') {
        return null;
    }
    $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        return false;
    }
    $owned = db_query('SELECT 1 FROM boards WHERE id = ? AND user_id = ?', [$id, $userId])->fetchColumn();
    return $owned ? $id : false;
}

/** Validate pin form fields. Returns [cleanFields, errors]. */
function validate_pin_fields(): array
{
    $fields = [
        'title'       => clean_text(input('title')),
        'description' => clean_text(input('description')),
        'source_url'  => input('source_url'),
    ];
    $errors = array_filter([
        'title'       => v_length($fields['title'], 'Title', 1, 100),
        'description' => v_length($fields['description'], 'Description', 0, 2000),
        'source_url'  => v_url_optional($fields['source_url']),
    ]);
    return [$fields, $errors];
}

/** Hostname shown next to a source link, e.g. "example.com". */
function url_host(string $url): string
{
    $host = (string) parse_url($url, PHP_URL_HOST);
    return preg_replace('/^www\./i', '', $host) ?? $host;
}
