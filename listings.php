<?php
declare(strict_types=1);
require __DIR__ . '/i18n.php';

function escapeListingValue(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$listings = [];
$listingLoadFailed = false;

try {
	require __DIR__ . '/config.php';

	$statement = $pdo->prepare(
		'SELECT id, title, description, city, location_details, price_xaf, dimensions_sqm, has_land_certificate '
		. 'FROM listings WHERE is_active = :is_active ORDER BY created_at DESC'
	);
	$statement->execute([':is_active' => 1]);
	$listings = $statement->fetchAll();
} catch (Throwable $exception) {
	error_log('Active listings query failed: ' . $exception->getMessage());
	$listingLoadFailed = true;
}
?>
<section class="database-listings" aria-labelledby="database-listings-title">
	<h2 id="database-listings-title"><?= h($currentLanguage === 'fr' ? 'Biens disponibles' : 'Available properties') ?></h2>

	<?php if ($listingLoadFailed): ?>
		<p role="status"><?= h($currentLanguage === 'fr' ? 'Les annonces sont temporairement indisponibles. Veuillez réessayer plus tard.' : 'Listings are temporarily unavailable. Please try again later.') ?></p>
	<?php elseif ($listings === []): ?>
		<p><?= h($currentLanguage === 'fr' ? 'Aucune annonce active n’est disponible actuellement.' : 'No active listings are available right now.') ?></p>
	<?php else: ?>
		<div class="database-listing-grid">
			<?php foreach ($listings as $listing): ?>
				<article class="database-listing-card">
					<h3><?= escapeListingValue((string) $listing['title']) ?></h3>
					<p><?= escapeListingValue((string) $listing['city']) ?><?php if (!empty($listing['location_details'])): ?>, <?= escapeListingValue((string) $listing['location_details']) ?><?php endif; ?></p>

					<?php if (!empty($listing['description'])): ?>
						<p><?= nl2br(escapeListingValue((string) $listing['description'])) ?></p>
					<?php endif; ?>

					<p><strong><?= number_format((float) $listing['price_xaf'], 0, ',', ' ') ?> FCFA</strong></p>
					<p><?= number_format((float) $listing['dimensions_sqm'], 2, ',', ' ') ?> m²</p>

					<?php if ((int) $listing['has_land_certificate'] === 1): ?>
						<p class="certificate-badge">Titre Foncier Verified</p>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
