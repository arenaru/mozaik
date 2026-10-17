<?php
/**
 * Standalone error page. Deliberately does NOT use header.php or touch the DB,
 * so it still renders when the database is down.
 * @var int $errorStatus
 * @var string $errorMessage
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error <?= e($errorStatus) ?> · <?= e(config('app.name', 'Mozaic')) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<main id="main" class="container error-page">
    <h1><?= e($errorStatus) ?></h1>
    <p><?= e($errorMessage) ?></p>
    <p><a class="btn btn-primary" href="/">Back to home</a></p>
</main>
</body>
</html>
