<?php
// includes/upload_helper.php
// Shared safe image-upload handler: validates real file content (not just the
// client-supplied name/extension) before moving it into a web-served folder.

/**
 * Validate and move an uploaded image into $targetDir.
 * Returns the generated server-side filename on success, or false on failure.
 */
function handle_image_upload(array $file, string $targetDir, string $prefix): string|false
{
    if (empty($file['name']) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed_mimes[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return false;
    }

    $ext = $allowed_mimes[$mime];

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], rtrim($targetDir, '/') . '/' . $filename)) {
        return false;
    }

    return $filename;
}
