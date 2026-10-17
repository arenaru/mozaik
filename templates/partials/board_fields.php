<?php
/**
 * Shared board fields for create + edit.
 * @var array{name:string,description:string,visibility:string} $fields
 * @var array<string,string> $errors
 */
?>
<label>
    Name
    <input type="text" name="name" value="<?= e($fields['name']) ?>" maxlength="50"
           required<?= field_aria($errors, 'name') ?>>
    <?= field_error($errors, 'name') ?>
</label>

<label>
    Description (optional)
    <textarea name="description" rows="3" maxlength="300"<?= field_aria($errors, 'description') ?>><?= e($fields['description']) ?></textarea>
    <?= field_error($errors, 'description') ?>
</label>

<fieldset class="radio-group"<?= field_aria($errors, 'visibility') ?>>
    <legend>Visibility</legend>
    <label class="checkbox">
        <input type="radio" name="visibility" value="public"<?= $fields['visibility'] !== 'private' ? ' checked' : '' ?>>
        Public: anyone can see this board
    </label>
    <label class="checkbox">
        <input type="radio" name="visibility" value="private"<?= $fields['visibility'] === 'private' ? ' checked' : '' ?>>
        Private: only you can see this board
    </label>
    <?= field_error($errors, 'visibility') ?>
</fieldset>
