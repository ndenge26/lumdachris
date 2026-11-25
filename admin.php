<?php
declare(strict_types=1);
require __DIR__ . '/i18n.php';

require __DIR__ . '/admin-common.php';
require __DIR__ . '/config.php';

$error = '';
$success = (string) ($_SESSION['admin_flash'] ?? '');
unset($_SESSION['admin_flash']);
$agent = null;

if (isset($_SESSION['agent_user_id'])) {
    $agentQuery = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = :id AND role = 'agent'");
    $agentQuery->execute([':id' => (int) $_SESSION['agent_user_id']]);
    $agent = $agentQuery->fetch() ?: null;
    if ($agent === null) {
        unset($_SESSION['agent_user_id']);
    }
}

function adminPostString(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function adminRedirect(string $message, string $anchor = ''): void
{
    $_SESSION['admin_flash'] = $message;
    header('Location: admin.php' . ($anchor !== '' ? '#' . $anchor : ''));
    exit;
}

function adminStoreListingImages(array $files, array &$createdFiles): array
{
    $stored = [];
    if (!isset($files['name']) || !is_array($files['name'])) {
        return $stored;
    }
    if (count($files['name']) > 6) {
        throw new RuntimeException('Upload no more than six property images at a time.');
    }

    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'property-media';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The property image directory could not be created.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('The property image directory is not writable.');
    }

    foreach ($files['name'] as $index => $name) {
        $uploadError = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $tmpName = $files['tmp_name'][$index] ?? '';
        if ($uploadError !== UPLOAD_ERR_OK || !is_string($tmpName) || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('One of the property images could not be uploaded.');
        }
        $size = filesize($tmpName);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
        if ($size === false || $size < 1 || $size > 5 * 1024 * 1024 || !is_string($mime) || !isset($extensions[$mime])) {
            throw new RuntimeException('Property images must be genuine JPEG, PNG, or WebP files up to 5 MB each.');
        }
        $storageName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($tmpName, $directory . DIRECTORY_SEPARATOR . $storageName)) {
            throw new RuntimeException('A property image could not be stored.');
        }
        $createdFiles[] = $storageName;
        $stored[] = ['storage_name' => $storageName, 'alt_text' => ''];
    }

    return $stored;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $createdImageFiles = [];
    $createdPrivateFiles = [];
    try {
        adminRequireCsrf();
        $action = adminPostString('action');

        if ($action === 'login') {
            $email = adminPostString('email');
            $password = $_POST['password'] ?? '';
            if (!is_string($password) || $email === '' || strlen($password) > 4096) {
                throw new RuntimeException('Enter your agent email and password.');
            }
            $loginQuery = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = :email AND role = 'agent' LIMIT 1");
            $loginQuery->execute([':email' => $email]);
            $account = $loginQuery->fetch();
            if (!$account || !password_verify($password, (string) $account['password_hash'])) {
                throw new RuntimeException('Those agent credentials could not be verified.');
            }
            session_regenerate_id(true);
            $_SESSION['agent_user_id'] = (int) $account['id'];
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            adminRedirect('Welcome to the management dashboard.');
        }

        if ($agent === null) {
            throw new RuntimeException('Sign in with an agent account to continue.');
        }

        if ($action === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            adminRedirect('You have signed out.');
        }

        if ($action === 'save_listing') {
            $listingIdText = adminPostString('listing_id');
            $listingId = $listingIdText === '' ? null : filter_var($listingIdText, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($listingId === false) {
                throw new RuntimeException('Select a valid property to update.');
            }
            $isNewListing = $listingId === null;
            $listingType = adminPostString('listing_type');
            $propertyType = adminPostString('property_type');
            $title = adminPostString('title');
            $city = adminPostString('city');
            $location = adminPostString('location_details');
            $description = adminPostString('description');
            $price = filter_var(adminPostString('price_xaf'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $dimensions = adminPostString('dimensions_sqm');
            $bedroomsText = adminPostString('bedrooms');
            $certificateStatus = adminPostString('certificate_status');

            if (!in_array($listingType, ['buy_land', 'sell_land', 'lease', 'tenancy'], true)
                || !in_array($propertyType, ['land', 'house', 'apartment'], true)) {
                throw new RuntimeException('Select a valid transaction and property type.');
            }
            if ((in_array($listingType, ['buy_land', 'sell_land'], true) && $propertyType !== 'land')
                || (in_array($listingType, ['lease', 'tenancy'], true) && $propertyType === 'land')) {
                throw new RuntimeException('Land transactions require the land type; rental and tenancy listings must be a house or apartment.');
            }
            $titleLength = preg_match_all('/./us', $title);
            $cityLength = preg_match_all('/./us', $city);
            $locationLength = preg_match_all('/./us', $location);
            $descriptionLength = preg_match_all('/./us', $description);
            if ($titleLength === false || $titleLength < 2 || $titleLength > 200
                || $cityLength === false || $cityLength < 2 || $cityLength > 100
                || $locationLength === false || $locationLength > 255
                || $descriptionLength === false || $descriptionLength > 5000) {
                throw new RuntimeException('Check the title, location, and description lengths.');
            }
            if ($price === false || !is_numeric($dimensions) || (float) $dimensions <= 0 || (float) $dimensions > 9999999999.99) {
                throw new RuntimeException('Enter a non-negative XAF price and a valid property size.');
            }
            $bedrooms = null;
            if ($bedroomsText !== '') {
                $bedrooms = filter_var($bedroomsText, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 65535]]);
                if ($bedrooms === false) {
                    throw new RuntimeException('Enter a valid number of bedrooms.');
                }
            }
            if (!in_array($certificateStatus, ['not_submitted', 'pending', 'verified', 'rejected'], true)) {
                throw new RuntimeException('Select a valid certificate review status.');
            }

            $existingImages = [];
            $oldCertificate = null;
            if ($listingId !== null) {
                $existingQuery = $pdo->prepare('SELECT certificate_storage_name FROM listings WHERE id = :id AND owner_user_id = :owner');
                $existingQuery->execute([':id' => $listingId, ':owner' => (int) $agent['id']]);
                $existing = $existingQuery->fetch();
                if (!$existing) {
                    throw new RuntimeException('That property could not be found in your listings.');
                }
                $oldCertificate = $existing['certificate_storage_name'] !== null ? (string) $existing['certificate_storage_name'] : null;
                $imageQuery = $pdo->prepare('SELECT id, storage_name FROM listing_images WHERE listing_id = :listing');
                $imageQuery->execute([':listing' => $listingId]);
                $existingImages = $imageQuery->fetchAll();
            }

            $removeImageIds = $_POST['remove_images'] ?? [];
            if (!is_array($removeImageIds)) {
                throw new RuntimeException('The selected property images could not be read.');
            }
            $removeImageIds = array_values(array_filter($removeImageIds, static function ($id): bool {
                return is_string($id) && ctype_digit($id);
            }));
            $removeImageLookup = array_fill_keys($removeImageIds, true);
            $imagesToRemove = array_values(array_filter($existingImages, static function (array $image) use ($removeImageLookup): bool {
                return isset($removeImageLookup[(string) $image['id']]);
            }));
            $newImages = adminStoreListingImages($_FILES['listing_images'] ?? [], $createdImageFiles);
            if (count($existingImages) - count($imagesToRemove) + count($newImages) > 10) {
                throw new RuntimeException('A property can have no more than ten images.');
            }

            $certificateUpload = null;
            $uploadedCertificate = $_FILES['certificate_document'] ?? null;
            if (is_array($uploadedCertificate) && (int) ($uploadedCertificate['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $certificateUpload = adminStorePrivateUpload(
                    $uploadedCertificate,
                    ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg'],
                    5 * 1024 * 1024
                );
                $createdPrivateFiles[] = $certificateUpload['storage_name'];
                $certificateStatus = 'pending';
            }

            $pdo->beginTransaction();
            if ($listingId === null) {
                $save = $pdo->prepare(
                    'INSERT INTO listings (owner_user_id, listing_type, property_type, title, description, city, location_details, price_xaf, dimensions_sqm, bedrooms, has_land_certificate, certificate_status, certificate_original_name, certificate_storage_name, certificate_mime, is_active) '
                    . 'VALUES (:owner, :listing_type, :property_type, :title, :description, :city, :location, :price, :dimensions, :bedrooms, :verified, :certificate_status, :document_name, :document_storage, :document_mime, :active)'
                );
            } else {
                $save = $pdo->prepare(
                    'UPDATE listings SET listing_type = :listing_type, property_type = :property_type, title = :title, description = :description, city = :city, location_details = :location, price_xaf = :price, dimensions_sqm = :dimensions, bedrooms = :bedrooms, has_land_certificate = :verified, certificate_status = :certificate_status, is_active = :active'
                    . ($certificateUpload !== null ? ', certificate_original_name = :document_name, certificate_storage_name = :document_storage, certificate_mime = :document_mime' : '')
                    . ' WHERE id = :id AND owner_user_id = :owner'
                );
            }
            $values = [
                ':owner' => (int) $agent['id'],
                ':listing_type' => $listingType,
                ':property_type' => $propertyType,
                ':title' => $title,
                ':description' => $description !== '' ? $description : null,
                ':city' => $city,
                ':location' => $location !== '' ? $location : null,
                ':price' => $price,
                ':dimensions' => $dimensions,
                ':bedrooms' => $bedrooms,
                ':verified' => $certificateStatus === 'verified' ? 1 : 0,
                ':certificate_status' => $certificateStatus,
                ':active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($listingId === null || $certificateUpload !== null) {
                $values[':document_name'] = $certificateUpload['name'] ?? null;
                $values[':document_storage'] = $certificateUpload['storage_name'] ?? null;
                $values[':document_mime'] = $certificateUpload['mime'] ?? null;
            }
            if ($listingId !== null) {
                $values[':id'] = $listingId;
                $values[':owner'] = (int) $agent['id'];
            }
            $save->execute($values);
            if ($listingId === null) {
                $listingId = (int) $pdo->lastInsertId();
            }

            foreach ($imagesToRemove as $image) {
                $remove = $pdo->prepare('DELETE FROM listing_images WHERE id = :id AND listing_id = :listing');
                $remove->execute([':id' => (int) $image['id'], ':listing' => $listingId]);
            }
            foreach ($newImages as $image) {
                $insertImage = $pdo->prepare('INSERT INTO listing_images (listing_id, storage_name, alt_text) VALUES (:listing, :storage, :alt)');
                $insertImage->execute([':listing' => $listingId, ':storage' => $image['storage_name'], ':alt' => $title]);
            }
            $pdo->commit();

            foreach ($imagesToRemove as $image) {
                adminRemoveImageFile((string) $image['storage_name']);
            }
            if ($certificateUpload !== null) {
                adminRemovePrivateFile($oldCertificate);
            }
            adminRedirect($isNewListing ? 'Property added.' : 'Property details saved.', 'listings');
        }

        if ($action === 'delete_listing') {
            $listingId = filter_var(adminPostString('listing_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($listingId === false) {
                throw new RuntimeException('Select a valid property to delete.');
            }
            $pdo->beginTransaction();
            $lookup = $pdo->prepare('SELECT certificate_storage_name FROM listings WHERE id = :id AND owner_user_id = :owner');
            $lookup->execute([':id' => $listingId, ':owner' => (int) $agent['id']]);
            $listing = $lookup->fetch();
            if (!$listing) {
                throw new RuntimeException('That property could not be found in your listings.');
            }
            $imageQuery = $pdo->prepare('SELECT storage_name FROM listing_images WHERE listing_id = :listing');
            $imageQuery->execute([':listing' => $listingId]);
            $imagesToDelete = $imageQuery->fetchAll();
            $delete = $pdo->prepare('DELETE FROM listings WHERE id = :id AND owner_user_id = :owner');
            $delete->execute([':id' => $listingId, ':owner' => (int) $agent['id']]);
            $pdo->commit();
            foreach ($imagesToDelete as $image) {
                adminRemoveImageFile((string) $image['storage_name']);
            }
            adminRemovePrivateFile($listing['certificate_storage_name'] !== null ? (string) $listing['certificate_storage_name'] : null);
            adminRedirect('Property deleted.', 'listings');
        }

        if ($action === 'update_intake') {
            $intakeId = filter_var(adminPostString('intake_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $status = adminPostString('status');
            if ($intakeId === false || !in_array($status, ['received', 'in_review', 'resolved'], true)) {
                throw new RuntimeException('Choose a valid request and status.');
            }
            $documentReviewStatus = adminPostString('document_review_status');
            if ($documentReviewStatus !== '' && !in_array($documentReviewStatus, ['not_reviewed', 'verified', 'rejected'], true)) {
                throw new RuntimeException('Choose a valid document review status.');
            }
            $intakeQuery = $pdo->prepare('SELECT document_storage_name FROM service_intakes WHERE id = :id');
            $intakeQuery->execute([':id' => $intakeId]);
            $intakeRecord = $intakeQuery->fetch();
            if (!$intakeRecord) {
                throw new RuntimeException('That client request could not be found.');
            }
            if ($documentReviewStatus !== '') {
                if ($intakeRecord['document_storage_name'] === null) {
                    throw new RuntimeException('That request has no uploaded document to review.');
                }
                $update = $pdo->prepare(
                    'UPDATE service_intakes SET status = :status, document_review_status = :document_status '
                    . 'WHERE id = :id AND document_storage_name IS NOT NULL'
                );
                $update->execute([
                    ':status' => $status,
                    ':document_status' => $documentReviewStatus,
                    ':id' => $intakeId,
                ]);
            } else {
                $update = $pdo->prepare('UPDATE service_intakes SET status = :status WHERE id = :id');
                $update->execute([':status' => $status, ':id' => $intakeId]);
            }
            adminRedirect('Request status updated.', 'requests');
        }

        if ($action === 'update_lead') {
            $leadId = filter_var(adminPostString('lead_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $status = adminPostString('status');
            if ($leadId === false || !in_array($status, ['received', 'contacted', 'closed'], true)) {
                throw new RuntimeException('Choose a valid lead and status.');
            }
            $update = $pdo->prepare('UPDATE chatbot_leads SET status = :status WHERE id = :id');
            $update->execute([':status' => $status, ':id' => $leadId]);
            adminRedirect('Assistant enquiry status updated.', 'assistant-leads');
        }

        throw new RuntimeException('That management action is not available.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($createdImageFiles as $storageName) {
            adminRemoveImageFile($storageName);
        }
        foreach ($createdPrivateFiles as $storageName) {
            adminRemovePrivateFile($storageName);
        }
        error_log('Agent management request failed: ' . $exception->getMessage());
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'The request could not be completed. Please try again.';
    }
}

$csrfToken = adminCsrfToken();
$isLogin = $agent === null;
$editListing = null;
$editImages = [];
$listings = [];
$intakes = [];
$leads = [];
$stats = ['listings' => 0, 'requests' => 0, 'leads' => 0, 'pending_documents' => 0];

if (!$isLogin) {
    $activeListingCount = $pdo->prepare('SELECT COUNT(*) FROM listings WHERE is_active = 1 AND owner_user_id = :owner');
    $activeListingCount->execute([':owner' => (int) $agent['id']]);
    $stats['listings'] = (int) $activeListingCount->fetchColumn();
    $stats['requests'] = (int) $pdo->query("SELECT COUNT(*) FROM service_intakes WHERE status != 'resolved'")->fetchColumn();
    $stats['leads'] = (int) $pdo->query("SELECT COUNT(*) FROM chatbot_leads WHERE status = 'received'")->fetchColumn();
    $pendingDocuments = $pdo->prepare("SELECT COUNT(*) FROM listings WHERE certificate_status = 'pending' AND owner_user_id = :owner");
    $pendingDocuments->execute([':owner' => (int) $agent['id']]);
    $stats['pending_documents'] = (int) $pendingDocuments->fetchColumn()
        + (int) $pdo->query("SELECT COUNT(*) FROM service_intakes WHERE document_storage_name IS NOT NULL AND document_review_status = 'not_reviewed'")->fetchColumn();

    $listingQuery = $pdo->prepare(
        'SELECT id, listing_type, property_type, title, city, price_xaf, certificate_status, is_active, created_at '
        . 'FROM listings WHERE owner_user_id = :owner ORDER BY updated_at DESC'
    );
    $listingQuery->execute([':owner' => (int) $agent['id']]);
    $listings = $listingQuery->fetchAll();

    $intakes = $pdo->query('SELECT * FROM service_intakes ORDER BY created_at DESC')->fetchAll();
    $leads = $pdo->query('SELECT * FROM chatbot_leads ORDER BY created_at DESC')->fetchAll();

    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (($editId === false || $editId === null) && $_SERVER['REQUEST_METHOD'] === 'POST' && adminPostString('action') === 'save_listing') {
        $postedEditId = adminPostString('listing_id');
        if ($postedEditId !== '') {
            $editId = filter_var($postedEditId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        }
    }
    if ($editId !== false && $editId !== null) {
        $editQuery = $pdo->prepare('SELECT * FROM listings WHERE id = :id AND owner_user_id = :owner');
        $editQuery->execute([':id' => $editId, ':owner' => (int) $agent['id']]);
        $editListing = $editQuery->fetch() ?: null;
        if ($editListing !== null) {
            $imageQuery = $pdo->prepare('SELECT id, storage_name FROM listing_images WHERE listing_id = :listing ORDER BY id');
            $imageQuery->execute([':listing' => $editId]);
            $editImages = $imageQuery->fetchAll();
        }
    }
}

$form = $editListing ?: [
    'id' => '',
    'listing_type' => 'sell_land',
    'property_type' => 'land',
    'title' => '',
    'description' => '',
    'city' => '',
    'location_details' => '',
    'price_xaf' => '',
    'dimensions_sqm' => '',
    'bedrooms' => '',
    'certificate_status' => 'not_submitted',
    'is_active' => 1,
];
if ($error !== '' && $_SERVER['REQUEST_METHOD'] === 'POST' && adminPostString('action') === 'save_listing') {
    foreach (['listing_type', 'property_type', 'title', 'description', 'city', 'location_details', 'price_xaf', 'dimensions_sqm', 'bedrooms', 'certificate_status'] as $field) {
        if (isset($_POST[$field]) && is_string($_POST[$field])) {
            $form[$field] = trim($_POST[$field]);
        }
    }
    $form['id'] = adminPostString('listing_id');
    $form['is_active'] = isset($_POST['is_active']) ? 1 : 0;
}
?>
<!doctype html>
<html lang="<?= $currentLanguage ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= h($currentLanguage === 'fr' ? 'Gestion des agents' : 'Agent management') ?> | LumidaChris</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-page">
<?php if ($isLogin): ?>
    <main class="login-shell">
        <a class="admin-brand" href="index.php"><img src="logo.png" alt=""> <span><strong>LUMIDACHRIS</strong><small>AGENT MANAGEMENT</small></span></a>
        <section class="login-card">
            <p class="eyebrow">Secure workspace</p>
            <?php require __DIR__ . '/language-switch.php'; ?>
            <h1><?= h(t('agent_sign_in')) ?></h1>
            <p><?= h($currentLanguage === 'fr' ? 'Utilisez un compte agent créé dans la base du site.' : 'Use an agent account provisioned in the site database.') ?></p>
            <?php if ($success !== ''): ?><p class="notice notice-success" role="status"><?= adminEscape($success) ?></p><?php endif; ?>
            <?php if ($error !== ''): ?><p class="notice notice-error" role="alert"><?= adminEscape($error) ?></p><?php endif; ?>
            <form method="post" action="admin.php">
                <input type="hidden" name="csrf_token" value="<?= adminEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="login">
                <label><?= h($currentLanguage === 'fr' ? 'Adresse électronique' : 'Email address') ?><input name="email" type="email" autocomplete="username" maxlength="254" required></label>
                <label><?= h($currentLanguage === 'fr' ? 'Mot de passe' : 'Password') ?><input name="password" type="password" autocomplete="current-password" maxlength="4096" required></label>
                <button class="button button-primary" type="submit"><?= h($currentLanguage === 'fr' ? 'Se connecter' : 'Sign in') ?></button>
            </form>
            <a class="return-link" href="index.php?lang=<?= $currentLanguage ?>">← <?= h($currentLanguage === 'fr' ? 'Retour au site' : 'Return to the website') ?></a>
        </section>
    </main>
<?php else: ?>
    <header class="admin-header">
        <a class="admin-brand" href="admin.php"><img src="logo.png" alt=""><span><strong>LUMIDACHRIS</strong><small>AGENT MANAGEMENT</small></span></a>
        <?php require __DIR__ . '/language-switch.php'; ?>
        <div class="agent-session"><span><?= adminEscape((string) $agent['full_name']) ?></span>
            <form method="post" action="admin.php">
                <input type="hidden" name="csrf_token" value="<?= adminEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="logout">
                <button class="button button-quiet" type="submit"><?= h($currentLanguage === 'fr' ? 'Se déconnecter' : 'Sign out') ?></button>
            </form>
        </div>
    </header>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <p><?= h($currentLanguage === 'fr' ? 'ESPACE DE TRAVAIL' : 'WORKSPACE') ?></p>
            <a href="#overview"><?= h($currentLanguage === 'fr' ? 'Vue d’ensemble' : 'Overview') ?></a><a href="#listings"><?= h($currentLanguage === 'fr' ? 'Biens' : 'Properties') ?></a><a href="#requests"><?= h($currentLanguage === 'fr' ? 'Demandes clients' : 'Client requests') ?></a><a href="#assistant-leads"><?= h($currentLanguage === 'fr' ? 'Demandes de l’assistant' : 'Assistant enquiries') ?></a>
            <a class="site-link" href="index.php?lang=<?= $currentLanguage ?>"><?= h($currentLanguage === 'fr' ? 'Voir le site public' : 'View public website') ?> ↗</a>
        </aside>
        <main class="admin-main">
            <section id="overview" class="admin-intro">
                <div><p class="eyebrow"><?= h($currentLanguage === 'fr' ? 'Espace agent' : 'Agent workspace') ?></p><h1><?= h($currentLanguage === 'fr' ? 'Gestion immobilière' : 'Property management') ?></h1><p><?= h($currentLanguage === 'fr' ? 'Gérez les annonces, les vérifications de documents et le suivi des clients.' : 'Keep listings, document reviews, and client follow-ups in one place.') ?></p></div>
            </section>
            <?php if ($success !== ''): ?><p class="notice notice-success" role="status"><?= adminEscape($success) ?></p><?php endif; ?>
            <?php if ($error !== ''): ?><p class="notice notice-error" role="alert"><?= adminEscape($error) ?></p><?php endif; ?>
            <section class="stat-grid" aria-label="Workspace summary">
                <article><span>Active properties</span><strong><?= $stats['listings'] ?></strong></article>
                <article><span>Open client requests</span><strong><?= $stats['requests'] ?></strong></article>
                <article><span>New assistant enquiries</span><strong><?= $stats['leads'] ?></strong></article>
                <article><span>Certificates to review</span><strong><?= $stats['pending_documents'] ?></strong></article>
            </section>

            <section id="listings" class="admin-section">
                <div class="section-title"><div><p class="eyebrow">Inventory</p><h2><?= $editListing ? 'Edit property' : 'Add a property' ?></h2></div>
                    <?php if ($editListing): ?><a class="button button-quiet" href="admin.php#listings">Cancel edit</a><?php endif; ?>
                </div>
                <form class="listing-form" method="post" enctype="multipart/form-data" action="admin.php#listings">
                    <input type="hidden" name="csrf_token" value="<?= adminEscape($csrfToken) ?>">
                    <input type="hidden" name="action" value="save_listing">
                    <input type="hidden" name="listing_id" value="<?= adminEscape((string) $form['id']) ?>">
                    <div class="form-grid">
                        <label>Transaction
                            <select name="listing_type" required>
                                <option value="buy_land" <?= $form['listing_type'] === 'buy_land' ? 'selected' : '' ?>>Land to buy</option>
                                <option value="sell_land" <?= $form['listing_type'] === 'sell_land' ? 'selected' : '' ?>>Land for sale</option>
                                <option value="lease" <?= $form['listing_type'] === 'lease' ? 'selected' : '' ?>>House / property for rent</option>
                                <option value="tenancy" <?= $form['listing_type'] === 'tenancy' ? 'selected' : '' ?>>Tenancy</option>
                            </select>
                        </label>
                        <label>Property type
                            <select name="property_type" required>
                                <?php foreach (['land' => 'Land / plot', 'house' => 'House', 'apartment' => 'Apartment'] as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $form['property_type'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="wide">Title<input name="title" maxlength="200" required value="<?= adminEscape((string) $form['title']) ?>"></label>
                        <label>City<input name="city" maxlength="100" required value="<?= adminEscape((string) $form['city']) ?>"></label>
                        <label>Neighborhood / address<input name="location_details" maxlength="255" value="<?= adminEscape((string) $form['location_details']) ?>"></label>
                        <label>Price (XAF)<input name="price_xaf" type="number" min="0" step="1" required value="<?= adminEscape((string) $form['price_xaf']) ?>"></label>
                        <label>Plot / property size (m²)<input name="dimensions_sqm" type="number" min="0.01" step="0.01" required value="<?= adminEscape((string) $form['dimensions_sqm']) ?>"></label>
                        <label>Bedrooms (optional)<input name="bedrooms" type="number" min="0" max="65535" step="1" value="<?= adminEscape((string) ($form['bedrooms'] ?? '')) ?>"></label>
                        <label>Certificate review
                            <select name="certificate_status">
                                <?php foreach (['not_submitted' => 'Not submitted', 'pending' => 'Pending review', 'verified' => 'Verified by agent', 'rejected' => 'Not verified'] as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $form['certificate_status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="wide">Description<textarea name="description" rows="4" maxlength="5000"><?= adminEscape((string) $form['description']) ?></textarea></label>
                        <label class="wide">Property images (JPEG, PNG, or WebP; up to 6 per upload, 5 MB each)
                            <input name="listing_images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
                        </label>
                        <label class="wide">Title / certificate document (private PDF or JPEG, max 5 MB)
                            <input name="certificate_document" type="file" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg">
                        </label>
                        <?php if ($editListing && !empty($form['certificate_storage_name'])): ?>
                            <p class="wide document-link">Current document: <a href="admin-document.php?listing=<?= (int) $form['id'] ?>">View <?= adminEscape((string) $form['certificate_original_name']) ?></a></p>
                        <?php endif; ?>
                        <?php if ($editImages !== []): ?>
                            <fieldset class="wide image-list"><legend>Current images — check any to remove</legend>
                                <?php foreach ($editImages as $image): ?>
                                    <label><img src="admin-image.php?id=<?= (int) $image['id'] ?>" alt=""><span><input type="checkbox" name="remove_images[]" value="<?= (int) $image['id'] ?>"> Remove</span></label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php endif; ?>
                        <label class="checkbox-field wide"><input type="checkbox" name="is_active" value="1" <?= (int) $form['is_active'] === 1 ? 'checked' : '' ?>> Publish this property on the public website</label>
                    </div>
                    <button class="button button-primary" type="submit"><?= $editListing ? 'Save property' : 'Add property' ?></button>
                </form>

                <div class="table-wrap">
                    <table><thead><tr><th>Property</th><th>Type</th><th>Price</th><th>Certificate</th><th>Published</th><th>Actions</th></tr></thead><tbody>
                    <?php foreach ($listings as $listing): ?>
                        <tr>
                            <td><strong><?= adminEscape((string) $listing['title']) ?></strong><small><?= adminEscape((string) $listing['city']) ?></small></td>
                            <td><?= adminEscape(str_replace('_', ' ', (string) $listing['listing_type'])) ?><small><?= adminEscape((string) $listing['property_type']) ?></small></td>
                            <td><?= number_format((float) $listing['price_xaf'], 0, ',', ' ') ?> XAF</td>
                            <td><span class="status status-<?= adminEscape((string) $listing['certificate_status']) ?>"><?= adminEscape(str_replace('_', ' ', (string) $listing['certificate_status'])) ?></span></td>
                            <td><?= (int) $listing['is_active'] === 1 ? 'Yes' : 'No' ?></td>
                            <td class="table-actions"><a class="button button-small button-quiet" href="admin.php?edit=<?= (int) $listing['id'] ?>#listings">Edit</a>
                                <form method="post" action="admin.php#listings" onsubmit="return confirm('Delete this property and its images?')">
                                    <input type="hidden" name="csrf_token" value="<?= adminEscape($csrfToken) ?>"><input type="hidden" name="action" value="delete_listing"><input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                                    <button class="button button-small button-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($listings === []): ?><tr><td colspan="6">No properties yet. Add one above.</td></tr><?php endif; ?>
                    </tbody></table>
                </div>
            </section>

            <section id="requests" class="admin-section">
                <div class="section-title"><div><p class="eyebrow">Client submissions</p><h2>Due diligence &amp; property matters</h2></div></div>
                <div class="request-list">
                <?php foreach ($intakes as $intake): ?>
                    <article class="request-card">
                        <div class="request-heading"><div><span class="status status-<?= adminEscape((string) $intake['status']) ?>"><?= adminEscape(str_replace('_', ' ', (string) $intake['status'])) ?></span>
                            <?php if (!empty($intake['document_storage_name'])): ?><span class="status status-<?= adminEscape((string) $intake['document_review_status']) ?>">Document: <?= adminEscape(str_replace('_', ' ', (string) $intake['document_review_status'])) ?></span><?php endif; ?>
                            <h3><?= adminEscape((string) $intake['full_name']) ?> <small>· <?= adminEscape((string) $intake['phone']) ?></small></h3></div>
                            <time><?= adminEscape((string) $intake['created_at']) ?></time></div>
                        <p><strong><?= $intake['request_type'] === 'due_diligence' ? 'Due diligence' : 'Property matter' ?></strong> · <?= adminEscape(str_replace('_', ' ', (string) $intake['issue_type'])) ?></p>
                        <p><?= adminEscape((string) $intake['property_location']) ?></p>
                        <?php if (!empty($intake['request_details'])): ?><p class="submission-details"><?= nl2br(adminEscape((string) $intake['request_details'])) ?></p><?php endif; ?>
                        <?php if (!empty($intake['document_storage_name'])): ?><p class="document-link">Supporting file: <a href="admin-document.php?intake=<?= (int) $intake['id'] ?>"><?= adminEscape((string) $intake['document_original_name']) ?></a> (<?= number_format((int) $intake['document_size'] / 1024) ?> KB)</p><?php endif; ?>
                        <form class="status-form" method="post" action="admin.php#requests">
                            <input type="hidden" name="csrf_token" value="<?= adminEscape($csrfToken) ?>"><input type="hidden" name="action" value="update_intake"><input type="hidden" name="intake_id" value="<?= (int) $intake['id'] ?>">
                            <label>Update status<select name="status"><?php foreach (['received', 'in_review', 'resolved'] as $status): ?><option value="<?= $status ?>" <?= $intake['status'] === $status ? 'selected' : '' ?>><?= adminEscape(str_replace('_', ' ', $status)) ?></option><?php endforeach; ?></select></label>
                            <?php if (!empty($intake['document_storage_name'])): ?>
                                <label>Review document<select name="document_review_status"><?php foreach (['not_reviewed' => 'Not reviewed', 'verified' => 'Verified', 'rejected' => 'Not verified'] as $value => $label): ?><option value="<?= $value ?>" <?= $intake['document_review_status'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
                            <?php endif; ?>
                            <button class="button button-small button-primary" type="submit">Save status</button>
                        </form>
                    </article>
                <?php endforeach; ?>
                <?php if ($intakes === []): ?><p class="empty-state">No client requests have been submitted.</p><?php endif; ?>
                </div>
            </section>

            <section id="assistant-leads" class="admin-section">
                <div class="section-title"><div><p class="eyebrow">AI assistant hand-offs</p><h2>Enquiries for an agent</h2></div></div>
                <p class="section-help">These are saved when a visitor completes the assistant’s contact hand-off. The assistant does not contact an agent automatically.</p>
                <div class="request-list">
                <?php foreach ($leads as $lead): ?>
                    <article class="request-card">
                        <div class="request-heading"><div><span class="status status-<?= adminEscape((string) $lead['status']) ?>"><?= adminEscape((string) $lead['status']) ?></span><h3><?= adminEscape((string) $lead['full_name']) ?> <small>· <?= adminEscape((string) $lead['phone']) ?></small></h3></div><time><?= adminEscape((string) $lead['created_at']) ?></time></div>
                        <p><strong>Looking to <?= $lead['intent'] === 'sell' ? 'sell' : 'buy' ?></strong> · <?= adminEscape((string) $lead['budget_location']) ?></p>
                        <p>Language: <?= $lead['language'] === 'fr' ? 'French' : 'English' ?></p>
                        <form class="status-form" method="post" action="admin.php#assistant-leads">
                            <input type="hidden" name="csrf_token" value="<?= adminEscape($csrfToken) ?>"><input type="hidden" name="action" value="update_lead"><input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>">
                            <label>Update status<select name="status"><?php foreach (['received', 'contacted', 'closed'] as $status): ?><option value="<?= $status ?>" <?= $lead['status'] === $status ? 'selected' : '' ?>><?= adminEscape(ucfirst($status)) ?></option><?php endforeach; ?></select></label>
                            <button class="button button-small button-primary" type="submit">Save status</button>
                        </form>
                    </article>
                <?php endforeach; ?>
                <?php if ($leads === []): ?><p class="empty-state">No assistant hand-offs have been submitted.</p><?php endif; ?>
                </div>
            </section>
            <footer class="admin-footer">Property certificate status records the agent’s review; it is not an official government verification.</footer>
        </main>
    </div>
<?php endif; ?>
</body>
</html>
