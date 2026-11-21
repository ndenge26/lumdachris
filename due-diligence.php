<?php
declare(strict_types=1);

$issueTypes = [
	'boundary_dispute' => 'Boundary Dispute',
	'certificate_verification' => 'Certificate Verification',
	'tenancy_issue' => 'Tenancy Issue',
];
$requestTypes = [
	'due_diligence' => [
		'title' => 'Due diligence request',
		'heading' => 'Clarity before <em>the next step.</em>',
		'intro' => 'Tell us about the property you want checked. A property specialist can review your request and follow up with you.',
		'form_label' => 'Due diligence request form',
	],
	'property_dispute' => [
		'title' => 'Property matter request',
		'heading' => 'A clearer way <em>forward.</em>',
		'intro' => 'Share the property issue you are facing. A property specialist can review your request and follow up with you.',
		'form_label' => 'Property matter request form',
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

$secureCookie = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
	'lifetime' => 0,
	'path' => '/',
	'secure' => $secureCookie,
	'httponly' => true,
	'samesite' => 'Strict',
]);
session_start();

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
		$errors[] = 'Choose a valid type of property request.';
	}

	$csrfToken = $_POST['csrf_token'] ?? '';
	if (!is_string($csrfToken) || !hash_equals($_SESSION['intake_csrf_token'], $csrfToken)) {
		$errors[] = 'Your form session expired. Reload the page and try again.';
	}

	$nameLength = preg_match_all('/./us', $formValues['full_name']);
	if ($nameLength === false || $nameLength < 2 || $nameLength > 150 || preg_match('/[\x00-\x1F\x7F]/', $formValues['full_name'])) {
		$errors[] = 'Enter your full name (up to 150 characters).';
	}

	$phoneDigits = preg_replace('/\D+/', '', $formValues['phone']);
	if (!preg_match('/^\+?[0-9(). -]{7,30}$/D', $formValues['phone']) || $phoneDigits === null || strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
		$errors[] = 'Enter a valid phone number, including its country code if needed.';
	}

	$locationLength = preg_match_all('/./us', $formValues['property_location']);
	if ($locationLength === false || $locationLength < 2 || $locationLength > 255 || preg_match('/[\x00-\x1F\x7F]/', $formValues['property_location'])) {
		$errors[] = 'Enter the property location (up to 255 characters).';
	}

	if (!array_key_exists($formValues['issue_type'], $issueTypes)) {
		$errors[] = 'Select one of the listed issue types.';
	}

	$detailsLength = preg_match_all('/./us', $formValues['request_details']);
	if ($detailsLength === false || $detailsLength > 3000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $formValues['request_details'])) {
		$errors[] = 'Keep additional details to 3,000 characters or fewer.';
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
		$errors[] = 'The document upload could not be read. Please try again.';
	} elseif ((int) $upload['error'] !== UPLOAD_ERR_OK) {
		if ((int) $upload['error'] !== UPLOAD_ERR_NO_FILE) {
			$errors[] = ((int) $upload['error'] === UPLOAD_ERR_INI_SIZE || (int) $upload['error'] === UPLOAD_ERR_FORM_SIZE)
				? 'The document exceeds this server’s upload limit of ' . number_format($maximumUploadBytes / (1024 * 1024), 0) . ' MB.'
				: 'The document could not be uploaded. Please choose it again.';
		}
	} elseif (!is_uploaded_file((string) $upload['tmp_name'])) {
		$errors[] = 'The uploaded document could not be verified.';
	} elseif ((string) $upload['name'] !== '') {
		$actualSize = filesize((string) $upload['tmp_name']);
		if ($actualSize === false || $actualSize < 1 || $actualSize > $maximumUploadBytes) {
			$errors[] = 'The document must be larger than 0 bytes and no larger than ' . number_format($maximumUploadBytes / (1024 * 1024), 0) . ' MB.';
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
				$errors[] = 'Only genuine PDF and JPEG documents are accepted.';
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
			$errors[] = 'We could not securely save your request. Please try again later.';
		}
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#183b31">
	<meta name="description" content="Request property due diligence or legal support for a land or tenancy matter in Cameroon.">
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
		<a class="brand" href="index.php" aria-label="Lumda Chris home">
			<img class="brand-logo" src="logo.png" alt="">
			<span class="brand-name"><strong>LUMDA CHRIS</strong><small>Achat et vente de terrains</small></span>
		</a>
		<a class="intake-back-link" href="index.php">Back to properties <span aria-hidden="true">↗</span></a>
	</div>
</header>

<main class="intake-main">
	<div class="intake-layout">
		<section class="intake-intro" aria-labelledby="intake-title">
			<p class="eyebrow eyebrow-dark"><span class="eyebrow-line"></span> Property support</p>
			<h1 id="intake-title"><?= $requestTypes[$requestType]['heading'] ?></h1>
			<p><?= intakeEscape($requestTypes[$requestType]['intro']) ?></p>
			<div class="intake-assurance"><span class="assurance-mark" aria-hidden="true">✓</span><span>Your documents are stored privately and reviewed only for this request.</span></div>
		</section>

		<section class="intake-form-panel" aria-label="<?= intakeEscape($requestTypes[$requestType]['form_label']) ?>">
			<?php if ($submittedSuccessfully): ?>
				<div class="intake-success" role="status">
					<span class="success-mark" aria-hidden="true">✓</span>
					<div><h2>Request received.</h2><p>Thank you. Your information has been securely submitted for review.</p></div>
				</div>
			<?php endif; ?>

			<?php if ($errors !== []): ?>
				<div class="intake-errors" role="alert">
					<p>Please review the following:</p>
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
				<div class="intake-form-heading"><span><?= intakeEscape(strtoupper($requestTypes[$requestType]['title'])) ?></span><p>Fields marked * are required.</p></div>

				<div class="intake-fields">
					<label class="intake-field" for="full-name">
						<span>Full name <b>*</b></span>
						<input id="full-name" name="full_name" type="text" maxlength="150" autocomplete="name" required value="<?= intakeEscape($formValues['full_name']) ?>" placeholder="Your full name">
					</label>
					<label class="intake-field" for="phone">
						<span>Phone number <b>*</b></span>
						<input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" required value="<?= intakeEscape($formValues['phone']) ?>" placeholder="+237 6XX XXX XXX">
					</label>
					<label class="intake-field intake-field-wide" for="property-location">
						<span>Location of the property <b>*</b></span>
						<input id="property-location" name="property_location" type="text" maxlength="255" required value="<?= intakeEscape($formValues['property_location']) ?>" placeholder="City, neighborhood, or nearby landmark">
					</label>
					<label class="intake-field intake-field-wide" for="issue-type">
						<span>What do you need help with? <b>*</b></span>
						<select id="issue-type" name="issue_type" required>
							<option value="" disabled <?= $formValues['issue_type'] === '' ? 'selected' : '' ?>>Choose the closest match</option>
							<?php foreach ($issueTypes as $value => $label): ?>
								<option value="<?= intakeEscape($value) ?>" <?= $formValues['issue_type'] === $value ? 'selected' : '' ?>><?= intakeEscape($label) ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="intake-field intake-field-wide" for="request-details">
						<span>Tell us a little more <small>(optional)</small></span>
						<textarea id="request-details" name="request_details" maxlength="3000" rows="4" placeholder="Share any helpful background or questions. Please do not include passwords or sensitive identity numbers."><?= intakeEscape($formValues['request_details']) ?></textarea>
					</label>
					<label class="intake-field intake-field-wide" for="land-document">
						<span>Supporting document <small>(optional)</small></span>
						<input id="land-document" name="land_document" type="file" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg">
						<small>PDF or JPEG, up to <?= number_format($maximumUploadBytes / (1024 * 1024), 0) ?> MB. You can also send it later.</small>
					</label>
				</div>

				<button class="intake-submit" type="submit">Send my request <span aria-hidden="true">↗</span></button>
				<p class="intake-privacy">Your contact details are used to respond to this request. A document is not required to get started.</p>
			</form>
		</section>
	</div>
</main>

<footer class="intake-footer"><span>© <?= date('Y') ?> Lumda Chris Cameroon</span><a href="index.php">Return home <span aria-hidden="true">↗</span></a></footer>
<?php require __DIR__ . '/chatbot-widget.php'; ?>
</body>
</html>
