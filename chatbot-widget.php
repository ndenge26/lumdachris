<?php
declare(strict_types=1);

if (!isset($_SESSION['chatbot_csrf_token'])) {
    $_SESSION['chatbot_csrf_token'] = bin2hex(random_bytes(32));
}

$chatbotSuggestionsEn = json_encode(
    ['Show me available land', 'How do I verify a land title?', 'Explain the buying process'],
    JSON_UNESCAPED_UNICODE
);
$chatbotSuggestionsFr = json_encode(
    ['Montrez-moi les terrains disponibles', 'Comment vérifier un titre foncier ?', 'Expliquez le processus d’achat'],
    JSON_UNESCAPED_UNICODE
);
?>
<div class="chatbot" id="property-chatbot" data-endpoint="chatbot.php" data-language="<?= $currentLanguage ?>" data-csrf="<?= htmlspecialchars($_SESSION['chatbot_csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-suggestions-en="<?= htmlspecialchars($chatbotSuggestionsEn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-suggestions-fr="<?= htmlspecialchars($chatbotSuggestionsFr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <section class="chatbot-panel" id="chatbot-panel" role="dialog" aria-label="<?= h(t('chat_property_consultant')) ?>" aria-modal="false" hidden>
        <header class="chatbot-header">
            <div class="chatbot-identity">
                <img class="chatbot-avatar" src="logo.png" alt="">
                <span><strong>LumidaChris Property Guide</strong><small>Cameroon · English / Français</small></span>
            </div>
            <button class="chat-icon-button chatbot-close" type="button" aria-label="<?= h(t('chat_close')) ?>" data-chat-close>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="chatbot-tools">
            <label class="chat-language"><span><?= h(t('language_switcher_label')) ?></span>
                <select data-chat-language aria-label="Chat and voice language">
                    <option value="en">English</option>
                    <option value="fr">Français</option>
                </select>
            </label>
            <label class="chat-speaker-toggle"><span><?= h(t('chat_voice_replies')) ?></span><input type="checkbox" data-chat-speaker aria-label="<?= h(t('chat_enable_voice')) ?>"><span class="switch-track" aria-hidden="true"></span></label>
        </div>

        <div class="chatbot-messages" data-chat-messages role="log" aria-live="polite" aria-relevant="additions">
            <article class="chat-message chat-message-bot"><p><?= $currentLanguage === 'fr'
                ? 'Bonjour, je suis LumidaChris. Posez-moi vos questions sur les terrains et appartements disponibles, les prix, la vérification foncière, les titres fonciers, les baux ou les litiges — en français ou en anglais. Je ne remplace pas un notaire ou un avocat.'
                : 'Hello, I’m LumidaChris. Ask me about available land and apartments, prices, due diligence, land titles, leases or disputes — in English or French. I’m not a substitute for a notary or lawyer.' ?></p></article>
        </div>

        <div class="chatbot-suggestions" data-chat-suggestions aria-label="<?= h(t('chat_suggested')) ?>"></div>

        <p class="chatbot-status" data-chat-status role="status" aria-live="polite"></p>
        <form class="chatbot-compose" data-chat-form>
            <button class="chat-icon-button chat-microphone" type="button" aria-label="<?= h(t('chat_speak')) ?>" title="<?= h(t('chat_speak')) ?>" data-chat-microphone>
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="3" width="6" height="12" rx="3"/><path d="M5.5 11.5a6.5 6.5 0 0 0 13 0M12 18v3m-4 0h8"/></svg>
            </button>
            <input class="chatbot-input" type="text" name="message" maxlength="1200" autocomplete="off" placeholder="<?= h(t('chat_placeholder')) ?>" aria-label="<?= h(t('chat_your_message')) ?>" data-chat-input required>
            <button class="chat-icon-button chat-send" type="submit" aria-label="<?= h(t('chat_send')) ?>" title="<?= h(t('chat_send')) ?>" data-chat-send>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 3-7.5 18-3.8-7.7L2 9.5 21 3Z"/><path d="M9.7 13.3 15 8"/></svg>
            </button>
        </form>
    </section>

    <button class="chatbot-launcher" type="button" aria-expanded="false" aria-controls="chatbot-panel" data-chat-open>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5 8 8 0 0 1-3.2-.7L4 20l1.4-4.1a7.5 7.5 0 1 1 14.6-4.4Z"/><path d="M8 11h.01M12 11h.01M16 11h.01"/></svg>
        <span><?= h(t('chat_open')) ?></span>
    </button>
</div>
