<?php
declare(strict_types=1);
require __DIR__ . '/i18n.php';

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
            'verified' => t('certificate_verified'),
            'pending' => t('certificate_pending'),
            'rejected' => t('certificate_rejected'),
            'not_submitted' => t('certificate_not_submitted'),
        ];
        $location = trim((string) $record['city'] . (!empty($record['location_details']) ? ', ' . $record['location_details'] : ''));
        $bedrooms = $record['bedrooms'] === null ? null : (int) $record['bedrooms'];
        $listings[] = [
            'title' => (string) $record['title'],
            'city' => (string) $record['city'],
            'neighborhood' => $location,
            'transaction' => $isRental ? 'lease' : 'buy',
            'transaction_label' => $isRental ? t('for_rent') : t('for_sale'),
            'property' => (string) $record['property_type'],
            'price' => (int) $record['price_xaf'],
            'size' => $record['property_type'] === 'land'
                ? number_format((float) $record['dimensions_sqm'], 2, '.', ',') . ' m²'
                : ($bedrooms === null ? number_format((float) $record['dimensions_sqm'], 2, '.', ',') . ' m²' : $bedrooms . ' bedrooms'),
            'certificate' => $certificateLabels[$record['certificate_status']] ?? t('certificate_not_submitted'),
            'certificate_verified' => $record['certificate_status'] === 'verified',
            'certificate_pending' => $record['certificate_status'] === 'pending',
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

$heroImages = glob(__DIR__ . '/images/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
if ($heroImages === false) {
    $heroImages = [];
}
natsort($heroImages);
$heroImages = array_values($heroImages);
?>
<!doctype html>
<html lang="<?= $currentLanguage ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#183b31">
    <meta name="description" content="<?= h(t('meta_description')) ?>">
    <title>LumidaChris — <?= h($currentLanguage === 'fr' ? 'Terrains au Cameroun' : 'Land in Cameroon') ?></title>
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="listings.js" defer></script>
    <script src="chatbot.js" defer></script>
    <script src="hero-carousel.js" defer></script>
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="index.php" aria-label="<?= h(t('home_aria')) ?>">
            <img class="brand-logo" src="logo.png" alt="">
            <span class="brand-name"><strong>LUMIDACHRIS</strong><small>Achat et vente de terrains</small></span>
        </a>
        <nav class="primary-nav" aria-label="<?= h(t('main_navigation')) ?>">
            <a href="?lang=<?= $currentLanguage ?>&amp;transaction=buy&amp;property=land#listings"><?= h(t('buy_sell_land')) ?></a>
            <a href="?lang=<?= $currentLanguage ?>&amp;transaction=lease#listings"><?= h(t('leases_tenancy')) ?></a>
            <a href="#due-diligence"><?= h(t('due_diligence')) ?></a>
            <a href="#dispute-resolution"><?= h(t('dispute_resolution')) ?></a>
        </nav>
        <?php require __DIR__ . '/language-switch.php'; ?>
        <a class="header-action" href="#listings"><?= h(t('explore_properties')) ?> <span aria-hidden="true">↗</span></a>
    </div>
</header>

<main>
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-inner">
            <div class="hero-copy">
                <p class="eyebrow"><span class="eyebrow-line"></span> <?= h(t('hero_eyebrow')) ?></p>
                <h1 id="hero-title"><?= $currentLanguage === 'fr' ? 'Un endroit pour prendre <em>racine.</em>' : 'A place to put down <em>roots.</em>' ?></h1>
                <p class="hero-description"><?= h(t('hero_description')) ?></p>
                <a class="text-link" href="#listings"><?= h(t('explore_featured')) ?> <span aria-hidden="true">↓</span></a>
            </div>
            <figure class="hero-photo hero-carousel" data-hero-carousel aria-label="Featured property photos">
                <?php foreach ($heroImages as $index => $imagePath): ?>
                    <img
                        class="hero-carousel-slide<?= $index === 0 ? ' is-active' : '' ?>"
                        src="<?= h('images/' . basename($imagePath)) ?>"
                        alt="Featured property photo <?= (int) ($index + 1) ?>"
                        aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>"
                        <?= $index === 0 ? '' : 'inert' ?>
                    >
                <?php endforeach; ?>
                <?php if (count($heroImages) > 1): ?>
                    <button class="hero-carousel-arrow hero-carousel-previous" type="button" aria-label="Show previous image">
                        <span aria-hidden="true">‹</span>
                    </button>
                    <button class="hero-carousel-arrow hero-carousel-next" type="button" aria-label="Show next image">
                        <span aria-hidden="true">›</span>
                    </button>
                    <div class="hero-carousel-dots" role="group" aria-label="Choose a property photo">
                        <?php foreach ($heroImages as $index => $imagePath): ?>
                            <button
                                class="hero-carousel-dot<?= $index === 0 ? ' is-active' : '' ?>"
                                type="button"
                                aria-label="Show image <?= (int) ($index + 1) ?>"
                                aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
                            ></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <figcaption><span class="photo-pin" aria-hidden="true"></span> Find a place that feels like yours</figcaption>
            </figure>
            <div class="hero-index" aria-hidden="true"><span>01</span><span class="index-rule"></span><span>03</span></div>
        </div>
        <div class="hero-bottom" aria-hidden="true"><span>CAMEROON</span><span>4° 03′ N &nbsp; 9° 42′ E</span></div>
    </section>

    <section class="search-section" aria-label="<?= h(t('search_properties')) ?>">
        <form class="search-form" action="index.php" method="get">
            <input type="hidden" name="lang" value="<?= $currentLanguage ?>">
            <label class="search-field">
                <span class="field-label"><?= h(t('i_want_to')) ?></span>
                <select name="transaction" aria-label="<?= h(t('transaction')) ?>">
                    <option value=""><?= h(t('buy_or_lease')) ?></option>
                    <option value="buy" <?= $selected['transaction'] === 'buy' ? 'selected' : '' ?>><?= h(t('buy')) ?></option>
                    <option value="lease" <?= $selected['transaction'] === 'lease' ? 'selected' : '' ?>><?= h(t('lease')) ?></option>
                </select>
            </label>
            <span class="field-divider" aria-hidden="true"></span>
            <label class="search-field">
                <span class="field-label"><?= h(t('in_around')) ?></span>
                <select name="location" aria-label="<?= h(t('location')) ?>">
                    <option value=""><?= h(t('any_location')) ?></option>
                    <?php foreach ($availableCities as $city): ?>
                        <option value="<?= h($city) ?>" <?= $selected['location'] === $city ? 'selected' : '' ?>><?= h($city) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <span class="field-divider" aria-hidden="true"></span>
            <label class="search-field">
                <span class="field-label"><?= h(t('property_type')) ?></span>
                <select name="property" aria-label="<?= h(t('property_type')) ?>">
                    <option value=""><?= h(t('land_house_apartment')) ?></option>
                    <option value="land" <?= $selected['property'] === 'land' ? 'selected' : '' ?>><?= h(t('land')) ?></option>
                    <option value="house" <?= $selected['property'] === 'house' ? 'selected' : '' ?>><?= h(t('house')) ?></option>
                    <option value="apartment" <?= $selected['property'] === 'apartment' ? 'selected' : '' ?>><?= h(t('apartment')) ?></option>
                </select>
            </label>
            <button class="search-button" type="submit"><span class="search-icon" aria-hidden="true"></span> <?= h(t('find_place')) ?></button>
        </form>
    </section>

    <section class="listings-section" id="listings" aria-labelledby="listings-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow eyebrow-dark"><span class="eyebrow-line"></span> <?= h(t('collection_eyebrow')) ?></p>
                <h2 id="listings-title"><?= $currentLanguage === 'fr' ? 'Des biens qui méritent <em>votre attention.</em>' : 'Places worth <em>a closer look.</em>' ?></h2>
            </div>
            <p class="section-note"><?= h(t('section_note')) ?></p>
        </div>

        <div class="listing-results-layout">
            <form class="listing-sidebar" id="listing-filters" aria-label="<?= h(t('filter_properties')) ?>">
                <div class="sidebar-heading">
                    <h3><?= h(t('refine_search')) ?></h3>
                    <button class="clear-filters" type="button" data-clear-filters><?= h(t('clear')) ?></button>
                </div>
                <p class="filter-result-count" id="filter-result-count" aria-live="polite"></p>
                <label class="sidebar-field">
                    <span><?= h(t('transaction')) ?></span>
                    <select data-filter="type">
                        <option value=""><?= h(t('buy_or_lease')) ?></option>
                        <option value="sale" <?= $selected['transaction'] === 'buy' ? 'selected' : '' ?>><?= h(t('for_sale')) ?></option>
                        <option value="lease" <?= $selected['transaction'] === 'lease' ? 'selected' : '' ?>><?= h(t('for_lease')) ?></option>
                    </select>
                </label>
                <label class="sidebar-field">
                    <span><?= h(t('location')) ?></span>
                    <select data-filter="city">
                        <option value=""><?= h(t('all_locations')) ?></option>
                        <?php foreach ($availableCities as $city): ?>
                            <option value="<?= h($city) ?>" <?= $selected['location'] === $city ? 'selected' : '' ?>><?= h($city) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="sidebar-field">
                    <span><?= h(t('property_type')) ?></span>
                    <select data-filter="category">
                        <option value=""><?= h(t('all_property_types')) ?></option>
                        <option value="land" <?= $selected['property'] === 'land' ? 'selected' : '' ?>><?= h(t('land')) ?></option>
                        <option value="house" <?= $selected['property'] === 'house' ? 'selected' : '' ?>><?= h(t('house')) ?></option>
                        <option value="apartment" <?= $selected['property'] === 'apartment' ? 'selected' : '' ?>><?= h(t('apartment')) ?></option>
                    </select>
                </label>
                <label class="sidebar-field">
                    <span><?= h(t('certificate_status')) ?></span>
                    <select data-filter="certificate">
                        <option value=""><?= h(t('any_status')) ?></option>
                        <option value="true"><?= h(t('verified_documents')) ?></option>
                        <option value="false"><?= h(t('not_verified')) ?></option>
                    </select>
                </label>
            </form>

            <div class="listing-results">
            <div class="listing-grid">
                <?php foreach ($filteredListings as $index => $listing): ?>
                    <article class="listing-card" data-listing data-type="<?= $listing['transaction'] === 'buy' ? 'sale' : 'lease' ?>" data-city="<?= h($listing['city']) ?>" data-category="<?= h($listing['property']) ?>" data-certificate="<?= $listing['certificate_verified'] ? 'true' : 'false' ?>" style="--card-index: <?= (int) $index ?>">
                        <a class="listing-image" href="#due-diligence" aria-label="<?= h(t('learn_more_about')) ?> <?= h($listing['title']) ?>">
                            <img src="<?= $listing['image'] !== '' ? h($listing['image']) : 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&amp;fit=crop&amp;w=900&amp;q=80' ?>" alt="<?= h($listing['alt']) ?>" loading="lazy">
                            <span class="listing-tag"><?= h($listing['transaction_label']) ?></span>
                            <span class="listing-arrow" aria-hidden="true">↗</span>
                        </a>
                        <div class="listing-body">
                            <div class="listing-location"><span class="location-dot" aria-hidden="true"></span><?= h($listing['neighborhood']) ?></div>
                            <h3><?= h($listing['title']) ?></h3>
                            <?php if ($listing['description'] !== ''): ?><p class="listing-description"><?= h($listing['description']) ?></p><?php endif; ?>
                            <div class="listing-specs"><span><?= h(t($listing['property'])) ?></span><span><?= h($listing['size']) ?></span></div>
                            <div class="certificate <?= $listing['certificate_pending'] ? 'certificate-review' : '' ?>">
                                <span class="certificate-icon" aria-hidden="true"><?= $listing['certificate_pending'] ? '!' : '✓' ?></span>
                                <?= h($listing['certificate']) ?>
                            </div>
                            <div class="listing-price"><span><?= h($listing['transaction'] === 'lease' ? t('monthly_rent') : t('asking_price')) ?></span><strong><?= number_format($listing['price'], 0, '.', ',') ?> <small>XAF</small></strong></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
                <p class="listing-empty" id="listing-empty" hidden><?= h(t('listing_empty')) ?></p>
            </div>
        </div>
        <p class="sample-note"><?= h(t('sample_note')) ?></p>
    </section>

    <section class="services-section" aria-label="<?= h(t('property_support')) ?>">
        <article class="service-block" id="due-diligence">
            <span class="service-number"><?= h(t('before_commit')) ?></span>
            <h2><?= h(t('know_heading')) ?></h2>
            <p><?= h(t('know_text')) ?></p>
            <a href="due-diligence.php?lang=<?= $currentLanguage ?>&amp;request=due-diligence" class="service-link"><?= h(t('ask_due_diligence')) ?> <span aria-hidden="true">↗</span></a>
        </article>
        <article class="service-block service-block-light" id="dispute-resolution">
            <span class="service-number"><?= h(t('need_guidance')) ?></span>
            <h2><?= h(t('advice_heading')) ?></h2>
            <p><?= h(t('advice_text')) ?></p>
            <a href="due-diligence.php?lang=<?= $currentLanguage ?>&amp;request=dispute" class="service-link"><?= h(t('discuss_matter')) ?> <span aria-hidden="true">↗</span></a>
        </article>
    </section>
</main>

<footer class="site-footer">
    <div class="footer-main">
        <div class="footer-brand-block">
            <a class="brand brand-footer" href="index.php" aria-label="<?= h(t('home_aria')) ?>">
                <img class="brand-logo" src="logo.png" alt="">
                <span class="brand-name"><strong>LUMIDACHRIS</strong><small>Achat et vente de terrains</small></span>
            </a>
            <p><?= h(t('footer_tagline')) ?></p>
        </div>
        <div class="footer-links">
            <h2><?= h(t('land_resources')) ?></h2>
            <a href="https://mindcaf.gov.cm/" target="_blank" rel="noopener noreferrer"><?= h(t('mindcaf_resources')) ?> <span aria-hidden="true">↗</span></a>
            <a href="https://mindcaf.gov.cm/" target="_blank" rel="noopener noreferrer"><?= h(t('mindcaf_name')) ?> <span aria-hidden="true">↗</span></a>
            <p><?= h(t('mindcaf_note')) ?></p>
        </div>
        <div class="footer-links footer-nav">
            <h2><?= h(t('explore')) ?></h2>
            <a href="?lang=<?= $currentLanguage ?>&amp;transaction=buy&amp;property=land#listings"><?= h(t('buy_sell_land')) ?></a>
            <a href="?lang=<?= $currentLanguage ?>&amp;transaction=lease#listings"><?= h(t('leases_tenancy')) ?></a>
            <a href="#due-diligence"><?= h(t('due_diligence')) ?></a>
            <a href="#dispute-resolution"><?= h(t('dispute_resolution')) ?></a>
            <a href="admin.php?lang=<?= $currentLanguage ?>"><?= h(t('agent_sign_in')) ?></a>
        </div>
    </div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> LumidaChris Cameroon</span><span>Au Cameroun, votre terrain, notre priorité.</span></div>
</footer>

<?php require __DIR__ . '/chatbot-widget.php'; ?>
</body>
</html>