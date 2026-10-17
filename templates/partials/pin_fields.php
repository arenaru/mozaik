<?php
/**
 * Shared title / description / source link fields for create + edit.
 * @var array{title:string,description:string,source_url:string} $fields
 * @var array<string,string> $errors
 */
?>
<label>
    Title
    <input type="text" name="title" value="<?= e($fields['title']) ?>" maxlength="100"
           required<?= field_aria($errors, 'title') ?>>
    <?= field_error($errors, 'title') ?>
</label>

<label>
    Description
    <textarea name="description" rows="4" maxlength="2000"<?= field_aria($errors, 'description') ?>><?= e($fields['description']) ?></textarea>
    <?= field_error($errors, 'description') ?>
</label>

<label>
    Source link (optional)
    <input type="url" name="source_url" value="<?= e($fields['source_url']) ?>" maxlength="2048"
           placeholder="https://"<?= field_aria($errors, 'source_url') ?>>
    <span class="hint">Only http:// and https:// links are allowed.</span>
    <?= field_error($errors, 'source_url') ?>
</label>
