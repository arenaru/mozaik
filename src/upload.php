<?php
/**
 * Secure image upload.
 *
 *   1. Check PHP upload error + is_uploaded_file()
 *   2. Enforce size limit
 *   3. Detect MIME from file CONTENT (finfo), whitelist JPG/PNG/WebP
 *   4. Check dimensions (decompression-bomb guard)
 *   5. Re-encode with GD -> strips metadata and any embedded payload
 *   6. Save under a random name; extension comes from the detected type, never the client
 *
 *     try {
 *         $path = store_uploaded_image($_FILES['image'] ?? null, 'pins');
 *     } catch (UploadException $e) {
 *         $errors['image'] = $e->getMessage();
 *     }
 */

declare(strict_types=1);

final class UploadException extends RuntimeException
{
}

const UPLOAD_ALLOWED_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

const UPLOAD_SUBDIRS = ['pins', 'avatars'];

/**
 * @param array<string,mixed>|null $file  An entry from $_FILES
 * @param string $subdir                  'pins' or 'avatars'
 * @param int|null $maxSide               Optional downscale (e.g. 512 for avatars)
 * @return string Relative path to store in the DB, e.g. "pins/3f2a....jpg"
 */
function store_uploaded_image(?array $file, string $subdir, ?int $maxSide = null): string
{
    if (!in_array($subdir, UPLOAD_SUBDIRS, true)) {
        throw new InvalidArgumentException('Invalid upload subdir');
    }
    if ($file === null || !isset($file['error']) || is_array($file['error'])) {
        throw new UploadException('Please choose an image to upload.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new UploadException('Please choose an image to upload.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new UploadException('Image must be 5 MB or smaller.');
        default:
            error_log('[mozaic] Upload error code ' . (int) $file['error']);
            throw new UploadException('Upload failed. Please try again.');
    }

    $tmp = (string) $file['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        throw new UploadException('Upload failed. Please try again.');
    }

    $size = filesize($tmp);
    if ($size === false || $size === 0 || $size > config('upload.max_bytes')) {
        throw new UploadException('Image must be 5 MB or smaller.');
    }

    // MIME from content, not from $file['type'] or the filename.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!is_string($mime) || !isset(UPLOAD_ALLOWED_TYPES[$mime])) {
        throw new UploadException('Only JPG, PNG and WebP images are allowed.');
    }

    $info = @getimagesize($tmp);
    if ($info === false || ($info['mime'] ?? '') !== $mime) {
        throw new UploadException('The file is not a valid image.');
    }
    [$width, $height] = $info;
    if ($width < 1 || $height < 1
        || $width > config('upload.max_width') || $height > config('upload.max_height')
        || $width * $height > config('upload.max_pixels')) {
        throw new UploadException('Image dimensions are too large.');
    }

    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png'  => @imagecreatefrompng($tmp),
        'image/webp' => @imagecreatefromwebp($tmp),
    };
    if (!$src instanceof GdImage) {
        throw new UploadException('The file is not a valid image.');
    }

    $img = $src;
    if ($maxSide !== null && max($width, $height) > $maxSide) {
        $scale = $maxSide / max($width, $height);
        $scaled = imagescale($src, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        if ($scaled instanceof GdImage) {
            $img = $scaled;
        }
    }

    if ($mime !== 'image/jpeg') {
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }

    $ext = UPLOAD_ALLOWED_TYPES[$mime];
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dir = config('upload.dir') . '/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Upload directory not writable');
    }

    // Write to a temp name first, then atomically move into place.
    $dest = $dir . '/' . $name;
    $partial = $dest . '.part';
    $ok = match ($mime) {
        'image/jpeg' => imagejpeg($img, $partial, 88),
        'image/png'  => imagepng($img, $partial, 6),
        'image/webp' => imagewebp($img, $partial, 85),
    };

    if (!$ok || !rename($partial, $dest)) {
        @unlink($partial);
        throw new RuntimeException('Failed to write uploaded image');
    }
    @chmod($dest, 0644);

    return $subdir . '/' . $name;
}

/**
 * Delete a stored upload. Only accepts paths produced by store_uploaded_image(),
 * so a tampered DB value can't delete arbitrary files (path traversal).
 */
function delete_uploaded_image(?string $relativePath): void
{
    if ($relativePath === null
        || !preg_match('#^(pins|avatars)/[a-f0-9]{32}\.(jpg|png|webp)$#', $relativePath)) {
        return;
    }
    $full = config('upload.dir') . '/' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}
