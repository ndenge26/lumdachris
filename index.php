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

$listings = [
    [
        'title' => 'A quiet parcel in Bastos',
        'city' => 'Yaounde',
        'city_label' => 'Yaounde',
        'neighborhood' => 'Bastos, Centre Region',
        'transaction' => 'buy',
        'transaction_label' => 'For sale',
        'property' => 'land',
        'price' => 48500000,
        'size' => '1,200 m2',
        'certificate' => 'Certificate verified',
        'image' => 'photo-1500382017468-9049fed747ef',
        'alt' => 'Open green land under a bright sky',
    ],
    [
        'title' => 'Light-filled apartment near the coast',
        'city' => 'Douala',
        'city_label' => 'Douala',
        'neighborhood' => 'Bonapriso, Littoral Region',
        'transaction' => 'lease',
        'transaction_label' => 'For lease',
        'property' => 'apartment',
        'price' => 650000,
        'size' => '3 bedrooms',
        'certificate' => 'Title documents verified',
        'image' => 'photo-1600607687939-ce8a6c25118c',
        'alt' => 'Modern apartment with a bright open living area',
    ],
    [
        'title' => 'Room to build in a growing town',
        'city' => 'Kribi',
        'city_label' => 'Kribi',
        'neighborhood' => 'Near the town centre, South Region',
        'transaction' => 'buy',
        'transaction_label' => 'For sale',
        'property' => 'land',
        'price' => 22000000,
        'size' => '800 m2',
        'certificate' => 'Certificate verified',
        'image' => 'photo-1510798831971-661eb04b3739',
        'alt' => 'A house set among trees on a spacious plot',
    ],
    [
        'title' => 'A family home with garden space',
        'city' => 'Yaounde',
        'city_label' => 'Yaounde',
        'neighborhood' => 'Odza, Centre Region',
        'transaction' => 'lease',
        'transaction_label' => 'For lease',
        'property' => 'apartment',
        'price' => 425000,
        'size' => '2 bedrooms',
        'certificate' => 'Title documents verified',
        'image' => 'photo-1600566753086-00f18fb6b3ea',
        'alt' => 'A modern home with a garden and wide windows',
    ],
    [
        'title' => 'Elevated land with a city outlook',
        'city' => 'Douala',
        'city_label' => 'Douala',
        'neighborhood' => 'Yassa, Littoral Region',
        'transaction' => 'buy',
        'transaction_label' => 'For sale',
        'property' => 'land',
        'price' => 31500000,
        'size' => '1,000 m2',
        'certificate' => 'Certificate under review',
        'image' => 'photo-1500382017468-9049fed747ef',
        'alt' => 'A broad green plot with a clear view',
    ],
    [
        'title' => 'A calm apartment close to the beach',
        'city' => 'Kribi',
        'city_label' => 'Kribi',
        'neighborhood' => 'Ngoye, South Region',
        'transaction' => 'lease',
        'transaction_label' => 'For lease',
        'property' => 'apartment',
        'price' => 300000,
        'size' => '2 bedrooms',
        'certificate' => 'Title documents verified',
        'image' => 'photo-1600607687920-4e2a09cf159d',
        'alt' => 'A welcoming contemporary living space',
    ],
];

$filterOptions = [
    'transaction' => ['buy', 'lease'],
    'location' => ['Yaounde', 'Douala', 'Kribi'],
    'property' => ['land', 'apartment'],
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
                    <option value="Yaounde" <?= $selected['location'] === 'Yaounde' ? 'selected' : '' ?>>Yaounde</option>
                    <option value="Douala" <?= $selected['location'] === 'Douala' ? 'selected' : '' ?>>Douala</option>
                    <option value="Kribi" <?= $selected['location'] === 'Kribi' ? 'selected' : '' ?>>Kribi</option>
                </select>
            </label>
            <span class="field-divider" aria-hidden="true"></span>
            <label class="search-field">
                <span class="field-label">Property type</span>
                <select name="property" aria-label="Property type">
                    <option value="">Land or apartment</option>
                    <option value="land" <?= $selected['property'] === 'land' ? 'selected' : '' ?>>Land</option>
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
                        <option value="Yaounde" <?= $selected['location'] === 'Yaounde' ? 'selected' : '' ?>>Yaounde</option>
                        <option value="Douala" <?= $selected['location'] === 'Douala' ? 'selected' : '' ?>>Douala</option>
                        <option value="Kribi" <?= $selected['location'] === 'Kribi' ? 'selected' : '' ?>>Kribi</option>
                    </select>
                </label>
                <label class="sidebar-field">
                    <span>Property type</span>
                    <select data-filter="category">
                        <option value="">All property types</option>
                        <option value="land" <?= $selected['property'] === 'land' ? 'selected' : '' ?>>Land</option>
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
                            <img src="https://images.unsplash.com/<?= h($listing['image']) ?>?auto=format&amp;fit=crop&amp;w=900&amp;q=80" alt="<?= h($listing['alt']) ?>" loading="lazy">
                            <span class="listing-tag"><?= h($listing['transaction_label']) ?></span>
                            <span class="listing-arrow" aria-hidden="true">↗</span>
                        </a>
                        <div class="listing-body">
                            <div class="listing-location"><span class="location-dot" aria-hidden="true"></span><?= h($listing['neighborhood']) ?></div>
                            <h3><?= h($listing['title']) ?></h3>
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
        <p class="sample-note">Illustrative listings shown for demonstration. Confirm all prices, details, and documents independently.</p>
    </section>

    <section class="services-section" aria-label="Property support">
        <article class="service-block" id="due-diligence">
            <span class="service-number">01 / BEFORE YOU COMMIT</span>
            <h2>Know what you're stepping into.</h2>
            <p>Request a due diligence review to help verify a property's documents, ownership history, and survey details.</p>
            <a href="due-diligence.php" class="service-link">Ask about due diligence <span aria-hidden="true">↗</span></a>
        </article>
        <article class="service-block service-block-light" id="dispute-resolution">
            <span class="service-number">02 / WHEN YOU NEED GUIDANCE</span>
            <h2>Get the right advice, early.</h2>
            <p>Speak with a property legal expert about a land matter or a dispute that needs a considered next step.</p>
            <a href="due-diligence.php" class="service-link">Discuss a property matter <span aria-hidden="true">↗</span></a>
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
        </div>
    </div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> Lumda Chris Cameroon</span><span>Au Cameroun, votre terrain, notre priorité.</span></div>
</footer>

<div class="chatbot" id="property-chatbot" data-endpoint="chatbot.php" data-csrf="<?= h($_SESSION['chatbot_csrf_token']) ?>">
    <section class="chatbot-panel" id="chatbot-panel" role="dialog" aria-label="Cameroon property consultant" aria-modal="false" hidden>
        <header class="chatbot-header">
            <div class="chatbot-identity">
                <img class="chatbot-avatar" src="logo.png" alt="">
                <span><strong>Lumda Chris Property Guide</strong><small>Cameroon · English / Français</small></span>
            </div>
            <button class="chat-icon-button chatbot-close" type="button" aria-label="Close chat" data-chat-close>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="chatbot-tools">
            <label class="chat-language"><span>Language</span>
                <select data-chat-language aria-label="Chat and voice language">
                    <option value="en">English</option>
                    <option value="fr">Français</option>
                </select>
            </label>
            <label class="chat-speaker-toggle"><span>Voice replies</span><input type="checkbox" data-chat-speaker aria-label="Enable spoken replies"><span class="switch-track" aria-hidden="true"></span></label>
        </div>

        <div class="chatbot-messages" data-chat-messages role="log" aria-live="polite" aria-relevant="additions">
            <article class="chat-message chat-message-bot"><p>Hello, I can share general information about land titles, leases, due diligence, and property disputes in Cameroon. I’m not a substitute for a notary or lawyer. How can I help?</p></article>
        </div>

        <p class="chatbot-status" data-chat-status role="status" aria-live="polite"></p>
        <form class="chatbot-compose" data-chat-form>
            <button class="chat-icon-button chat-microphone" type="button" aria-label="Speak your message" title="Speak your message" data-chat-microphone>
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="3" width="6" height="12" rx="3"/><path d="M5.5 11.5a6.5 6.5 0 0 0 13 0M12 18v3m-4 0h8"/></svg>
            </button>
            <input class="chatbot-input" type="text" name="message" maxlength="1200" autocomplete="off" placeholder="Ask about property in Cameroon" aria-label="Your message" data-chat-input required>
            <button class="chat-icon-button chat-send" type="submit" aria-label="Send message" title="Send message" data-chat-send>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 3-7.5 18-3.8-7.7L2 9.5 21 3Z"/><path d="M9.7 13.3 15 8"/></svg>
            </button>
        </form>
    </section>

    <button class="chatbot-launcher" type="button" aria-expanded="false" aria-controls="chatbot-panel" data-chat-open>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5 8 8 0 0 1-3.2-.7L4 20l1.4-4.1a7.5 7.5 0 1 1 14.6-4.4Z"/><path d="M8 11h.01M12 11h.01M16 11h.01"/></svg>
        <span>Ask Lumda Chris</span>
    </button>
</div>
</body>
</html>