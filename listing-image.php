<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$imageId = $_GET['id'] ?? '';
if (!is_string($imageId) || !ctype_digit($imageId)) {
    http_response_code(404);
    exit;
}

$query = $pdo->prepare(
    'SELECT images.storage_name FROM listing_images images '
    . 'JOIN listings ON listings.id = images.listing_id '
    . 'WHERE images.id = :id AND listings.is_active = 1'
);
$query->execute([':id' => (int) $imageId]);
$storageName = $query->fetchColumn();
if (!is_string($storageName) || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D', $storageName)) {
    http_response_code(404);
    exit;
}

$mimeTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$extension = pathinfo($storageName, PATHINFO_EXTENSION);
$path = __DIR__ . DIRECTORY_SEPARATOR . 'property-media' . DIRECTORY_SEPARATOR . $storageName;
if (!isset($mimeTypes[$extension]) || !is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mimeTypes[$extension]);
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
