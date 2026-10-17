<?php
/**
 * Page header. Variables: $title (string, optional).
 * @var string|null $title
 */
$user = current_user();
$pageTitle = isset($title) && $title !== '' ? $title . ' · ' . config('app.name') : config('app.name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <nav class="nav" aria-label="Main">
        <a class="brand" href="/"><?= e(config('app.name')) ?></a>

        <form class="search" action="/search.php" method="get" role="search">
            <label class="visually-hidden" for="q">Search</label>
            <input id="q" type="search" name="q" maxlength="100" placeholder="Search pins or people"
                   value="<?= e(input('q', 'GET')) ?>">
        </form>

        <ul class="nav-links">
            <?php if ($user): ?>
                <li><a class="btn btn-primary" href="/pin_create.php">Create</a></li>
                <li><a href="/profile.php?u=<?= e(rawurlencode($user['username'])) ?>">Profile</a></li>
                <li><a href="/settings.php">Settings</a></li>
                <?php if ($user['role'] === 'admin'): ?>
                    <li><a href="/admin/">Admin</a></li>
                <?php endif; ?>
                <li>
                    <form action="/logout.php" method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-link">Log out</button>
                    </form>
                </li>
            <?php else: ?>
                <li><a href="/login.php">Log in</a></li>
                <li><a class="btn btn-primary" href="/register.php">Sign up</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
<main id="main" class="container">
<?php view('partials/flash'); ?>
