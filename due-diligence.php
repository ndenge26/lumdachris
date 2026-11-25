<?php
declare(strict_types=1);
require __DIR__ . '/i18n.php';

$issueTypes = [
	'boundary_dispute' => t('issue_boundary'),
	'certificate_verification' => t('issue_certificate'),
	'tenancy_issue' => t('issue_tenancy'),
];
$requestTypes = [
	'due_diligence' => [
		'title' => t('due_request_title'),
		'heading' => $currentLanguage === 'fr' ? 'La clarté avant <em>la prochaine étape.</em>' : 'Clarity before <em>the next step.</em>',
		'intro' => t('due_intro'),
		'form_label' => t('due_form_label'),
	],
	'property_dispute' => [
		'title' => t('dispute_request_title'),
		'heading' => $currentLanguage === 'fr' ? 'Une voie plus <em>claire.</em>' : 'A clearer way <em>forward.</em>',
		'intro' => t('dispute_intro'),
		'form_label' => t('dispute_form_label'),
	],
];
$requestedType = $_GET['request'] ?? '';
$requestType = $requestedType === 'dispute' ? 'property_dispute' : 'due_diligence';
$maximumUploadBytes = 5 * 1024 * 1024;
$configuredUploadLimit = trim((string) ini_get('upload_max_filesize'));
if (preg_match('/^(\d+(?:\.\d+)?)\s*([KMG])?$/i', $configuredUploadLimit, $uploadLimitMatch) === 1) {
	$uploadLimitMultiplier = [
		'K' => 1024,
		'M' => 1024 * 1024,
		'G' => 1024 * 1024 * 1024,
	][strtoupper($uploadLimitMatch[2] ?? '')] ?? 1;
	$maximumUploadBytes = min($maximumUploadBytes, (int) ((float) $uploadLimitMatch[1] * $uploadLimitMultiplier));
}
$errors = [];
$formValues = [
	'request_type' => $requestType,
	'full_name' => '',
	'phone' => '',
	'property_location' => '',
	'issue_type' => '',
	'request_details' => '',
];

if (!isset($_SESSION['intake_csrf_token'])) {
	$_SESSION['intake_csrf_token'] = bin2hex(random_bytes(32));
}

$submittedSuccessfully = !empty($_SESSION['intake_success']);
unset($_SESSION['intake_success']);

function intakeEscape(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function intakePostString(string $key): string
{
	$value = $_POST[$key] ?? '';
	return is_string($value) ? trim($value) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$formValues['full_name'] = intakePostString('full_name');
	$formValues['phone'] = intakePostString('phone');
	$formValues['property_location'] = intakePostString('property_location');
	$formValues['issue_type'] = intakePostString('issue_type');
	$formValues['request_details'] = intakePostString('request_details');
	$submittedRequestType = intakePostString('request_type');
	if (array_key_exists($submittedRequestType, $requestTypes)) {
		$requestType = $submittedRequestType;
		$formValues['request_type'] = $requestType;
	} else {
		$errors[] = t('error_valid_request_type');
	}

	$csrfToken = $_POST['csrf_token'] ?? '';
	if (!is_string($csrfToken) || !hash_equals($_SESSION['intake_csrf_token'], $csrfToken)) {
		$errors[] = t('error_form_expired');
	}

	$nameLength = preg_match_all('/./us', $formValues['full_name']);
	if ($nameLength === false || $nameLength < 2 || $nameLength > 150 || preg_match('/[\x00-\x1F\x7F]/', $formValues['full_name'])) {
		$errors[] = t('error_full_name');
	}

	$phoneDigits = preg_replace('/\D+/', '', $formValues['phone']);
	if (!preg_match('/^\+?[0-9(). -]{7,30}$/D', $formValues['phone']) || $phoneDigits === null || strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
		$errors[] = t('error_phone');
	}

	$locationLength = preg_match_all('/./us', $formValues['property_location']);
	if ($locationLength === false || $locationLength < 2 || $locationLength > 255 || preg_match('/[\x00-\x1F\x7F]/', $formValues['property_location'])) {
		$errors[] = t('error_location');
	}

	if (!array_key_exists($formValues['issue_type'], $issueTypes)) {
		$errors[] = t('error_issue_type');
	}

	$detailsLength = preg_match_all('/./us', $formValues['request_details']);
	if ($detailsLength === false || $detailsLength > 3000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $formValues['request_details'])) {
		$errors[] = t('error_details_length');
	}

	$upload = $_FILES['land_document'] ?? null;
	$mimeType = '';
	$safeOriginalName = '';
	$uploadSize = 0;
	$storageName = null;
	$extensionByMime = [
		'application/pdf' => 'pdf',
		'image/jpeg' => 'jpg',
	];

	if (!is_array($upload) || !isset($upload['error'], $upload['tmp_name'], $upload['name'], $upload['size'])) {
		$errors[] = t('error_upload_read');
	} elseif ((int) $upload['error'] !== UPLOAD_ERR_OK) {
		if ((int) $upload['error'] !== UPLOAD_ERR_NO_FILE) {
			$errors[] = ((int) $upload['error'] === UPLOAD_ERR_INI_SIZE || (int) $upload['error'] === UPLOAD_ERR_FORM_SIZE)
				? t('error_upload_limit', ['size' => number_format($maximumUploadBytes / (1024 * 1024), 0)])
				: t('error_upload_failed');
		}
	} elseif (!is_uploaded_file((string) $upload['tmp_name'])) {
		$errors[] = t('error_upload_verify');
	} elseif ((string) $upload['name'] !== '') {
		$actualSize = filesize((string) $upload['tmp_name']);
		if ($actualSize === false || $actualSize < 1 || $actualSize > $maximumUploadBytes) {
			$errors[] = t('error_document_size', ['size' => number_format($maximumUploadBytes / (1024 * 1024), 0)]);
		} else {
			$uploadSize = $actualSize;
			$fileInfo = new finfo(FILEINFO_MIME_TYPE);
			$detectedMime = $fileInfo->file((string) $upload['tmp_name']);
			$originalExtension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
			$allowedExtensions = [
				'application/pdf' => ['pdf'],
				'image/jpeg' => ['jpg', 'jpeg'],
			];

			if (!is_string($detectedMime) || !isset($allowedExtensions[$detectedMime]) || !in_array($originalExtension, $allowedExtensions[$detectedMime], true)) {
				$errors[] = t('error_document_type');
			} else {
				$mimeType = $detectedMime;
				$baseName = basename(str_replace('\\', '/', (string) $upload['name']));
				$safeOriginalName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $baseName) ?? '';
				$safeOriginalName = trim(substr($safeOriginalName, 0, 180), '._-');
				if ($safeOriginalName === '') {
					$safeOriginalName = 'land-document.' . $originalExtension;
				}
			}
		}
	}

	if ($errors === []) {
		$storedPath = null;

		try {
			require __DIR__ . '/config.php';

			if ($mimeType !== '') {
				$uploadDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lumdachris-private-uploads';
				if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0700, true) && !is_dir($uploadDirectory)) {
					throw new RuntimeException('The private upload directory could not be created.');
				}
				if (!is_writable($uploadDirectory)) {
					throw new RuntimeException('The private upload directory is not writable.');
				}

				$storageName = bin2hex(random_bytes(16)) . '.' . $extensionByMime[$mimeType];
				$storedPath = $uploadDirectory . DIRECTORY_SEPARATOR . $storageName;
				if (!move_uploaded_file((string) $upload['tmp_name'], $storedPath)) {
					throw new RuntimeException('The uploaded document could not be stored.');
				}
			}

			$statement = $pdo->prepare(
				'INSERT INTO service_intakes '
				. '(request_type, full_name, phone, property_location, issue_type, request_details, document_original_name, document_storage_name, document_mime, document_size) '
				. 'VALUES (:request_type, :full_name, :phone, :property_location, :issue_type, :request_details, :document_original_name, :document_storage_name, :document_mime, :document_size)'
			);
			$statement->execute([
				':request_type' => $requestType,
				':full_name' => $formValues['full_name'],
				':phone' => $formValues['phone'],
				':property_location' => $formValues['property_location'],
				':issue_type' => $formValues['issue_type'],
				':request_details' => $formValues['request_details'] !== '' ? $formValues['request_details'] : null,
				':document_original_name' => $safeOriginalName !== '' ? $safeOriginalName : null,
				':document_storage_name' => $storageName,
				':document_mime' => $mimeType !== '' ? $mimeType : null,
				':document_size' => $mimeType !== '' ? $uploadSize : null,
			]);

			$_SESSION['intake_success'] = true;
			unset($_SESSION['intake_csrf_token']);
			header('Location: due-diligence.php?request=' . ($requestType === 'property_dispute' ? 'dispute' : 'due-diligence'));
			exit;
		} catch (Throwable $exception) {
			if ($storedPath !== null && is_file($storedPath)) {
				@unlink($storedPath);
			}
			error_log('Due diligence intake failed: ' . $exception->getMessage());
			$errors[] = t('error_save_request');
		}
	}
}
?>
<!doctype html>
<html lang="<?= $currentLanguage ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#183b31">
	<meta name="description" content="<?= h(t('meta_description')) ?>">
	<title><?= intakeEscape($requestTypes[$requestType]['title']) ?> | Lumda Chris</title>
	<link rel="icon" type="image/png" href="logo.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&amp;family=DM+Serif+Display&amp;display=swap" rel="stylesheet">
	<link rel="stylesheet" href="style.css">
	<script src="chatbot.js" defer></script>
</head>
<body class="intake-page">
<header class="site-header">
	<div class="header-inner">
		<a class="brand" href="index.php" aria-label="<?= h(t('home_aria')) ?>">
			<img class="brand-logo" src="logo.png" alt="">
			<span class="brand-name"><strong>LUMDA CHRIS</strong><small>Achat et vente de terrains</small></span>
		</a>
		<?php require __DIR__ . '/language-switch.php'; ?>
		<a class="intake-back-link" href="index.php?lang=<?= $currentLanguage ?>"><?= h(t('back_properties')) ?> <span aria-hidden="true">↗</span></a>
	</div>
</header>

<main class="intake-main">
	<div class="intake-layout">
		<section class="intake-intro" aria-labelledby="intake-title">
			<p class="eyebrow eyebrow-dark"><span class="eyebrow-line"></span> <?= h(t('property_support')) ?></p>
			<h1 id="intake-title"><?= $requestTypes[$requestType]['heading'] ?></h1>
			<p><?= intakeEscape($requestTypes[$requestType]['intro']) ?></p>
			<div class="intake-assurance"><span class="assurance-mark" aria-hidden="true">✓</span><span><?= h(t('assurance_documents')) ?></span></div>
		</section>

		<section class="intake-form-panel" aria-label="<?= intakeEscape($requestTypes[$requestType]['form_label']) ?>">
			<?php if ($submittedSuccessfully): ?>
				<div class="intake-success" role="status">
					<span class="success-mark" aria-hidden="true">✓</span>
					<div><h2><?= h(t('request_received')) ?></h2><p><?= h(t('request_thanks')) ?></p></div>
				</div>
			<?php endif; ?>

			<?php if ($errors !== []): ?>
				<div class="intake-errors" role="alert">
					<p><?= h(t('review_following')) ?></p>
					<ul>
						<?php foreach ($errors as $error): ?>
							<li><?= intakeEscape($error) ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<form class="intake-form" action="due-diligence.php" method="post" enctype="multipart/form-data">
				<input type="hidden" name="csrf_token" value="<?= intakeEscape($_SESSION['intake_csrf_token']) ?>">
				<input type="hidden" name="request_type" value="<?= intakeEscape($requestType) ?>">
				<div class="intake-form-heading"><span><?= intakeEscape(strtoupper($requestTypes[$requestType]['title'])) ?></span><p><?= h(t('required_fields')) ?></p></div>

				<div class="intake-fields">
					<label class="intake-field" for="full-name">
						<span><?= h(t('full_name')) ?> <b>*</b></span>
						<input id="full-name" name="full_name" type="text" maxlength="150" autocomplete="name" required value="<?= intakeEscape($formValues['full_name']) ?>" placeholder="<?= h($currentLanguage === 'fr' ? 'Votre nom complet' : 'Your full name') ?>">
					</label>
					<label class="intake-field" for="phone">
						<span><?= h(t('phone_number')) ?> <b>*</b></span>
						<input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" required value="<?= intakeEscape($formValues['phone']) ?>" placeholder="+237 6XX XXX XXX">
					</label>
					<label class="intake-field intake-field-wide" for="property-location">
						<span><?= h(t('property_location')) ?> <b>*</b></span>
						<input id="property-location" name="property_location" type="text" maxlength="255" required value="<?= intakeEscape($formValues['property_location']) ?>" placeholder="<?= h($currentLanguage === 'fr' ? 'Ville, quartier ou repère proche' : 'City, neighborhood, or nearby landmark') ?>">
					</label>
					<label class="intake-field intake-field-wide" for="issue-type">
						<span><?= h(t('need_help_with')) ?> <b>*</b></span>
						<select id="issue-type" name="issue_type" required>
							<option value="" disabled <?= $formValues['issue_type'] === '' ? 'selected' : '' ?>><?= h(t('choose_match')) ?></option>
							<?php foreach ($issueTypes as $value => $label): ?>
								<option value="<?= intakeEscape($value) ?>" <?= $formValues['issue_type'] === $value ? 'selected' : '' ?>><?= intakeEscape($label) ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="intake-field intake-field-wide" for="request-details">
						<span><?= h(t('tell_more')) ?> <small>(<?= h(t('optional')) ?>)</small></span>
						<textarea id="request-details" name="request_details" maxlength="3000" rows="4" placeholder="<?= h($currentLanguage === 'fr' ? 'Ajoutez toute information utile ou vos questions. N’indiquez aucun mot de passe ni numéro sensible.' : 'Share any helpful background or questions. Please do not include passwords or sensitive identity numbers.') ?>"><?= intakeEscape($formValues['request_details']) ?></textarea>
					</label>
					<label class="intake-field intake-field-wide" for="land-document">
						<span><?= h(t('supporting_document')) ?> <small>(<?= h(t('optional')) ?>)</small></span>
						<input id="land-document" name="land_document" type="file" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg">
						<small><?= h(t('document_help', ['size' => number_format($maximumUploadBytes / (1024 * 1024), 0)])) ?></small>
					</label>
				</div>

				<button class="intake-submit" type="submit"><?= h(t('send_request')) ?> <span aria-hidden="true">↗</span></button>
				<p class="intake-privacy"><?= h(t('privacy_note')) ?></p>
			</form>
		</section>
	</div>
</main>

<footer class="intake-footer"><span>© <?= date('Y') ?> Lumda Chris Cameroon</span><a href="index.php?lang=<?= $currentLanguage ?>"><?= h(t('return_home')) ?> <span aria-hidden="true">↗</span></a></footer>
<?php require __DIR__ . '/chatbot-widget.php'; ?>
</body>
</html>
