<?php
declare(strict_types=1);

require __DIR__ . '/admin-common.php';
require __DIR__ . '/config.php';

$agentId = $_SESSION['agent_user_id'] ?? null;
$imageId = $_GET['id'] ?? '';
if (!is_int($agentId) || !is_string($imageId) || !ctype_digit($imageId)) {
    http_response_code(404);
    exit;
}

$agentQuery = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'agent'");
$agentQuery->execute([':id' => $agentId]);
if (!$agentQuery->fetch()) {
    unset($_SESSION['agent_user_id']);
    http_response_code(404);
    exit;
}

$query = $pdo->prepare(
    'SELECT images.storage_name FROM listing_images images '
    . 'JOIN listings ON listings.id = images.listing_id '
    . 'WHERE images.id = :id AND listings.owner_user_id = :owner'
);
$query->execute([':id' => (int) $imageId, ':owner' => $agentId]);
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
header('Cache-Control: private, no-store, max-age=0');
readfile($path);
