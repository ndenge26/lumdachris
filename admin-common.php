<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function adminEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function adminCsrfToken(): string
{
    if (!isset($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function adminRequireCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !isset($_SESSION['admin_csrf_token']) || !hash_equals($_SESSION['admin_csrf_token'], $token)) {
        throw new RuntimeException('Your session expired. Reload the page and try again.');
    }
}

function adminPrivateUploadDirectory(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lumdachris-private-uploads';
}

function adminStorePrivateUpload(array $upload, array $allowedTypes, int $maximumBytes): array
{
    if (!isset($upload['error'], $upload['tmp_name'], $upload['name']) || !is_string($upload['name'])) {
        throw new RuntimeException('The uploaded file could not be read.');
    }
    if ((int) $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $upload['tmp_name'])) {
        throw new RuntimeException('Choose a valid file and try again.');
    }

    $size = filesize((string) $upload['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
    if ($size === false || $size < 1 || $size > $maximumBytes || !is_string($mime) || !isset($allowedTypes[$mime])) {
        throw new RuntimeException('The file type is not supported or the file exceeds the upload limit.');
    }
    $extensions = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
    ];
    $originalExtension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
    if (!in_array($originalExtension, $extensions[$mime], true)) {
        throw new RuntimeException('The file extension does not match its verified document type.');
    }

    $originalName = basename(str_replace('\\', '/', $upload['name']));
    $originalName = trim(substr(preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName) ?? '', 0, 180), '._-');
    if ($originalName === '') {
        $originalName = 'property-document.' . $allowedTypes[$mime];
    }

    $directory = adminPrivateUploadDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('The private document directory could not be created.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('The private document directory is not writable.');
    }

    $storageName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
    if (!move_uploaded_file((string) $upload['tmp_name'], $directory . DIRECTORY_SEPARATOR . $storageName)) {
        throw new RuntimeException('The file could not be stored securely.');
    }

    return [
        'name' => $originalName,
        'storage_name' => $storageName,
        'mime' => $mime,
        'size' => $size,
    ];
}

function adminRemovePrivateFile(?string $storageName): void
{
    if ($storageName === null || !preg_match('/^[a-f0-9]{32}\.(pdf|jpg)$/D', $storageName)) {
        return;
    }

    $path = adminPrivateUploadDirectory() . DIRECTORY_SEPARATOR . $storageName;
    if (is_file($path) && !unlink($path)) {
        error_log('Unable to remove private upload: ' . $storageName);
    }
}

function adminRemoveImageFile(string $storageName): void
{
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D', $storageName)) {
        return;
    }

    $path = __DIR__ . DIRECTORY_SEPARATOR . 'property-media' . DIRECTORY_SEPARATOR . $storageName;
    if (is_file($path) && !unlink($path)) {
        error_log('Unable to remove listing image: ' . $storageName);
    }
}
