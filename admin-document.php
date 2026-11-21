<?php
declare(strict_types=1);

require __DIR__ . '/admin-common.php';
require __DIR__ . '/config.php';

$agentId = $_SESSION['agent_user_id'] ?? null;
if (!is_int($agentId)) {
    http_response_code(403);
    exit('Agent sign-in required.');
}
$agentQuery = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'agent'");
$agentQuery->execute([':id' => $agentId]);
if (!$agentQuery->fetch()) {
    unset($_SESSION['agent_user_id']);
    http_response_code(403);
    exit('Agent sign-in required.');
}

$document = null;
if (isset($_GET['listing']) && is_string($_GET['listing']) && ctype_digit($_GET['listing'])) {
    $query = $pdo->prepare(
        'SELECT certificate_storage_name AS storage_name, certificate_mime AS mime, certificate_original_name AS original_name '
        . 'FROM listings WHERE id = :id AND owner_user_id = :owner'
    );
    $query->execute([':id' => (int) $_GET['listing'], ':owner' => $agentId]);
    $document = $query->fetch() ?: null;
} elseif (isset($_GET['intake']) && is_string($_GET['intake']) && ctype_digit($_GET['intake'])) {
    $query = $pdo->prepare(
        'SELECT document_storage_name AS storage_name, document_mime AS mime, document_original_name AS original_name '
        . 'FROM service_intakes WHERE id = :id'
    );
    $query->execute([':id' => (int) $_GET['intake']]);
    $document = $query->fetch() ?: null;
}

if (!$document || !is_string($document['storage_name']) || !is_string($document['mime'])
    || !in_array($document['mime'], ['application/pdf', 'image/jpeg'], true)
    || !preg_match('/^[a-f0-9]{32}\.(pdf|jpg)$/D', $document['storage_name'])) {
    http_response_code(404);
    exit('Document not found.');
}

$path = adminPrivateUploadDirectory() . DIRECTORY_SEPARATOR . $document['storage_name'];
if (!is_file($path) || !is_readable($path)) {
    error_log('Private document is missing: ' . $document['storage_name']);
    http_response_code(404);
    exit('Document not found.');
}

$downloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $document['original_name']) ?? 'property-document';
header('Content-Type: ' . $document['mime']);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('Cache-Control: private, no-store, max-age=0');
readfile($path);
