<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $sessionIsSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $sessionIsSecure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

if (!isset($_SESSION['chatbot_csrf_token'])) {
    $_SESSION['chatbot_csrf_token'] = bin2hex(random_bytes(32));
}

$listings = [];
try {
    require __DIR__ . '/config.php';
    $listingQuery = $pdo->query(
        'SELECT id, listing_type, property_type, title, description, city, location_details, price_xaf, dimensions_sqm, bedrooms, certificate_status, '
        . '(SELECT id FROM listing_images WHERE listing_id = listings.id ORDER BY id LIMIT 1) AS image_id '
        . 'FROM listings WHERE is_active = 1 ORDER BY created_at DESC'
    );
    foreach ($listingQuery->fetchAll() as $record) {
        $isRental = in_array($record['listing_type'], ['lease', 'tenancy'], true);
        $certificateLabels = [
            'verified' => 'Certificate verified',
            'pending' => 'Certificate under review',
            'rejected' => 'Certificate not verified',
            'not_submitted' => 'Certificate not submitted',
        ];
        $location = trim((string) $record['city'] . (!empty($record['location_details']) ? ', ' . $record['location_details'] : ''));
        $bedrooms = $record['bedrooms'] === null ? null : (int) $record['bedrooms'];
        $listings[] = [
            'title' => (string) $record['title'],
            'city' => (string) $record['city'],
            'neighborhood' => $location,
            'transaction' => $isRental ? 'lease' : 'buy',
            'transaction_label' => $isRental ? 'For rent' : 'For sale',
            'property' => (string) $record['property_type'],
            'price' => (int) $record['price_xaf'],
            'size' => $record['property_type'] === 'land'
                ? number_format((float) $record['dimensions_sqm'], 2, '.', ',') . ' m²'
                : ($bedrooms === null ? number_format((float) $record['dimensions_sqm'], 2, '.', ',') . ' m²' : $bedrooms . ' bedrooms'),
            'certificate' => $certificateLabels[$record['certificate_status']] ?? 'Certificate not submitted',
            'image' => $record['image_id'] !== null ? 'listing-image.php?id=' . (int) $record['image_id'] : '',
            'alt' => (string) $record['title'],
            'description' => (string) ($record['description'] ?? ''),
        ];
    }
} catch (Throwable $exception) {
    error_log('Public listings query failed: ' . $exception->getMessage());
}

$availableCities = array_values(array_unique(array_column($listings, 'city')));
sort($availableCities, SORT_NATURAL | SORT_FLAG_CASE);
$filterOptions = [
    'transaction' => ['buy', 'lease'],
    'location' => $availableCities,
    'property' => ['land', 'house', 'apartment'],
];

$selected = [];
foreach ($filterOptions as $name => $allowedValues) {
    $value = $_GET[$name] ?? '';
    $selected[$name] = is_string($value) && in_array($value, $allowedValues, true) ? $value : '';
}

$filteredListings = $listings;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#183b31">
    <meta name="description" content="Find land and homes across Cameroon, with clear listing details and property due diligence support.">
    <title>Lumda Chris — Terrains au Cameroun</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="listings.js" defer></script>
    <script src="chatbot.js" defer></script>
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="index.php" aria-label="Lumda Chris home">
            <img class="brand-logo" src="logo.png" alt="">
            <span class="brand-name"><strong>LUMDA CHRIS</strong><small>Achat et vente de terrains</small></span>
        </a>
        <nav class="primary-nav" aria-label="Main navigation">
            <a href="?transaction=buy&amp;property=land#listings">Buy / Sell Land</a>
            <a href="?transaction=lease#listings">Leases &amp; Tenancy</a>
            <a href="#due-diligence">Due Diligence</a>
            <a href="#dispute-resolution">Dispute Resolution</a>
        </nav>
        <a class="header-action" href="#listings">Explore properties <span aria-hidden="true">↗</span></a>
    </div>
</header>

<main>
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-inner">
            <div class="hero-copy">
                <p class="eyebrow"><span class="eyebrow-line"></span> A better way to find your place</p>
                <h1 id="hero-title">A place to put down <em>roots.</em></h1>
                <p class="hero-description">Find land and homes across Cameroon, with the details you need to move forward clearly.</p>
                <a class="text-link" href="#listings">Explore featured places <span aria-hidden="true">↓</span></a>
            </div>
            <figure class="hero-photo">
                <img src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&amp;fit=crop&amp;w=1200&amp;q=85" alt="Contemporary home framed by tropical greenery">
                <figcaption><span class="photo-pin" aria-hidden="true"></span> Find a place that feels like yours</figcaption>
            </figure>
            <div class="hero-index" aria-hidden="true"><span>01</span><span class="index-rule"></span><span>03</span></div>
        </div>
        <div class="hero-bottom" aria-hidden="true"><span>CAMEROON</span><span>4° 03′ N &nbsp; 9° 42′ E</span></div>
    </section>

    <section class="search-section" aria-label="Search properties">
        <form class="search-form" action="index.php" method="get">
            <label class="search-field">
                <span class="field-label">I want to</span>
                <select name="transaction" aria-label="Transaction type">
                    <option value="">Buy or lease</option>
                    <option value="buy" <?= $selected['transaction'] === 'buy' ? 'selected' : '' ?>>Buy</option>
                    <option value="lease" <?= $selected['transaction'] === 'lease' ? 'selected' : '' ?>>Lease</option>
                </select>
            </label>
            <span class="field-divider" aria-hidden="true"></span>
            <label class="search-field">
                <span class="field-label">In and around</span>
                <select name="location" aria-label="Location">
                    <option value="">Any location</option>
                    <?php foreach ($availableCities as $city): ?>
                        <option value="<?= h($city) ?>" <?= $selected['location'] === $city ? 'selected' : '' ?>><?= h($city) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <span class="field-divider" aria-hidden="true"></span>
            <label class="search-field">
                <span class="field-label">Property type</span>
                <select name="property" aria-label="Property type">
                    <option value="">Land, house or apartment</option>
                    <option value="land" <?= $selected['property'] === 'land' ? 'selected' : '' ?>>Land</option>
                    <option value="house" <?= $selected['property'] === 'house' ? 'selected' : '' ?>>House</option>
                    <option value="apartment" <?= $selected['property'] === 'apartment' ? 'selected' : '' ?>>Apartment</option>
                </select>
            </label>
            <button class="search-button" type="submit"><span class="search-icon" aria-hidden="true"></span> Find a place</button>
        </form>
    </section>

    <section class="listings-section" id="listings" aria-labelledby="listings-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow eyebrow-dark"><span class="eyebrow-line"></span> A considered collection</p>
                <h2 id="listings-title">Places worth <em>a closer look.</em></h2>
            </div>
            <p class="section-note">A few good places to begin. Each listing includes clear information on size, price, and documentation.</p>
        </div>

        <div class="listing-results-layout">
            <form class="listing-sidebar" id="listing-filters" aria-label="Filter properties">
                <div class="sidebar-heading">
                    <h3>Refine your search</h3>
                    <button class="clear-filters" type="button" data-clear-filters>Clear</button>
                </div>
                <p class="filter-result-count" id="filter-result-count" aria-live="polite"></p>
                <label class="sidebar-field">
                    <span>Transaction</span>
                    <select data-filter="type">
                        <option value="">Buy or lease</option>
                        <option value="sale" <?= $selected['transaction'] === 'buy' ? 'selected' : '' ?>>For sale</option>
                        <option value="lease" <?= $selected['transaction'] === 'lease' ? 'selected' : '' ?>>For lease</option>
                    </select>
                </label>
                <label class="sidebar-field">
                    <span>Location</span>
                    <select data-filter="city">
                        <option value="">All locations</option>
                        <?php foreach ($availableCities as $city): ?>
                            <option value="<?= h($city) ?>" <?= $selected['location'] === $city ? 'selected' : '' ?>><?= h($city) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="sidebar-field">
                    <span>Property type</span>
                    <select data-filter="category">
                        <option value="">All property types</option>
                        <option value="land" <?= $selected['property'] === 'land' ? 'selected' : '' ?>>Land</option>
                        <option value="house" <?= $selected['property'] === 'house' ? 'selected' : '' ?>>House</option>
                        <option value="apartment" <?= $selected['property'] === 'apartment' ? 'selected' : '' ?>>Apartment</option>
                    </select>
                </label>
                <label class="sidebar-field">
                    <span>Land certificate</span>
                    <select data-filter="certificate">
                        <option value="">Any status</option>
                        <option value="true">Verified documents</option>
                        <option value="false">Not verified</option>
                    </select>
                </label>
            </form>

            <div class="listing-results">
            <div class="listing-grid">
                <?php foreach ($filteredListings as $index => $listing): ?>
                    <article class="listing-card" data-listing data-type="<?= $listing['transaction'] === 'buy' ? 'sale' : 'lease' ?>" data-city="<?= h($listing['city']) ?>" data-category="<?= h($listing['property']) ?>" data-certificate="<?= in_array($listing['certificate'], ['Certificate verified', 'Title documents verified'], true) ? 'true' : 'false' ?>" style="--card-index: <?= (int) $index ?>">
                        <a class="listing-image" href="#due-diligence" aria-label="Learn more about <?= h($listing['title']) ?>">
                            <img src="<?= $listing['image'] !== '' ? h($listing['image']) : 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&amp;fit=crop&amp;w=900&amp;q=80' ?>" alt="<?= h($listing['alt']) ?>" loading="lazy">
                            <span class="listing-tag"><?= h($listing['transaction_label']) ?></span>
                            <span class="listing-arrow" aria-hidden="true">↗</span>
                        </a>
                        <div class="listing-body">
                            <div class="listing-location"><span class="location-dot" aria-hidden="true"></span><?= h($listing['neighborhood']) ?></div>
                            <h3><?= h($listing['title']) ?></h3>
                            <?php if ($listing['description'] !== ''): ?><p class="listing-description"><?= h($listing['description']) ?></p><?php endif; ?>
                            <div class="listing-specs"><span><?= h(ucfirst($listing['property'])) ?></span><span><?= h($listing['size']) ?></span></div>
                            <div class="certificate <?= $listing['certificate'] === 'Certificate under review' ? 'certificate-review' : '' ?>">
                                <span class="certificate-icon" aria-hidden="true"><?= $listing['certificate'] === 'Certificate under review' ? '!' : '✓' ?></span>
                                <?= h($listing['certificate']) ?>
                            </div>
                            <div class="listing-price"><span><?= $listing['transaction'] === 'lease' ? 'Monthly rent' : 'Asking price' ?></span><strong><?= number_format($listing['price'], 0, '.', ',') ?> <small>XAF</small></strong></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
                <p class="listing-empty" id="listing-empty" hidden>No places match these filters. Try adjusting your selection.</p>
            </div>
        </div>
        <p class="sample-note">Availability, prices, and document review status should be confirmed directly before a commitment.</p>
    </section>

    <section class="services-section" aria-label="Property support">
        <article class="service-block" id="due-diligence">
            <span class="service-number">01 / BEFORE YOU COMMIT</span>
            <h2>Know what you're stepping into.</h2>
            <p>Request a due diligence review to help verify a property's documents, ownership history, and survey details.</p>
            <a href="due-diligence.php?request=due-diligence" class="service-link">Ask about due diligence <span aria-hidden="true">↗</span></a>
        </article>
        <article class="service-block service-block-light" id="dispute-resolution">
            <span class="service-number">02 / WHEN YOU NEED GUIDANCE</span>
            <h2>Get the right advice, early.</h2>
            <p>Speak with a property legal expert about a land matter or a dispute that needs a considered next step.</p>
            <a href="due-diligence.php?request=dispute" class="service-link">Discuss a property matter <span aria-hidden="true">↗</span></a>
        </article>
    </section>
</main>

<footer class="site-footer">
    <div class="footer-main">
        <div class="footer-brand-block">
            <a class="brand brand-footer" href="index.php" aria-label="Lumda Chris home">
                <img class="brand-logo" src="logo.png" alt="">
                <span class="brand-name"><strong>LUMDA CHRIS</strong><small>Achat et vente de terrains</small></span>
            </a>
            <p>Au Cameroun, votre terrain, notre priorité.</p>
        </div>
        <div class="footer-links">
            <h2>Land resources</h2>
            <a href="https://mindcaf.gov.cm/" target="_blank" rel="noopener noreferrer">MINDCAF official resources <span aria-hidden="true">↗</span></a>
            <a href="https://mindcaf.gov.cm/" target="_blank" rel="noopener noreferrer">Ministry of State Property, Surveys and Land Tenure <span aria-hidden="true">↗</span></a>
            <p>Consult MINDCAF and qualified professionals to confirm current requirements and land records.</p>
        </div>
        <div class="footer-links footer-nav">
            <h2>Explore</h2>
            <a href="?transaction=buy&amp;property=land#listings">Buy / Sell Land</a>
            <a href="?transaction=lease#listings">Leases &amp; Tenancy</a>
            <a href="#due-diligence">Due Diligence</a>
            <a href="#dispute-resolution">Dispute Resolution</a>
            <a href="admin.php">Agent sign in</a>
        </div>
    </div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> Lumda Chris Cameroon</span><span>Au Cameroun, votre terrain, notre priorité.</span></div>
</footer>

<?php require __DIR__ . '/chatbot-widget.php'; ?>
</body>
</html>