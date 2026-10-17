<?php foreach (take_flashes() as $flash): ?>
    <?php $type = in_array($flash['type'], ['success', 'error', 'info'], true) ? $flash['type'] : 'info'; ?>
    <div class="flash flash-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
        <?= e($flash['message']) ?>
    </div>
<?php endforeach; ?>
