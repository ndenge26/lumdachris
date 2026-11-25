<?php
$switchQuery = $_GET;
unset($switchQuery['lang']);
$switchBase = strtok($_SERVER['REQUEST_URI'] ?? 'index.php', '?') ?: 'index.php';
$switchFragment = parse_url($switchBase, PHP_URL_FRAGMENT);
?>
<nav class="language-switcher" aria-label="<?= h(t('language_switcher_label')) ?>">
    <?php foreach (['en' => 'EN', 'fr' => 'FR'] as $code => $label): ?>
        <?php $query = $switchQuery; $query['lang'] = $code; ?>
        <a
            href="<?= h($switchBase . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)) ?>"
            lang="<?= $code ?>"
            hreflang="<?= $code ?>"
            class="<?= $currentLanguage === $code ? 'is-active' : '' ?>"
            <?= $currentLanguage === $code ? 'aria-current="true"' : '' ?>
            aria-label="<?= h($code === 'fr' ? t('switch_to_french') : t('switch_to_english')) ?>"
        ><?= $label ?></a>
    <?php endforeach; ?>
</nav>
