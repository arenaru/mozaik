<?php
/**
 * Admin section navigation.
 * @var string $active 'users' | 'audit'
 */
?>
<div class="admin-head">
    <h1>Admin</h1>
    <nav class="tabs" aria-label="Admin sections">
        <a class="tab<?= $active === 'users' ? ' is-active' : '' ?>" href="/admin/"<?= $active === 'users' ? ' aria-current="page"' : '' ?>>Users</a>
        <a class="tab<?= $active === 'audit' ? ' is-active' : '' ?>" href="/admin/audit.php"<?= $active === 'audit' ? ' aria-current="page"' : '' ?>>Audit log</a>
    </nav>
    <p class="muted">To remove a pin, open it and use “Remove (admin)”. The action is logged.</p>
</div>
