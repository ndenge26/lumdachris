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
<div class="chatbot" id="property-chatbot" data-endpoint="chatbot.php" data-csrf="<?= htmlspecialchars($_SESSION['chatbot_csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-suggestions-en="<?= htmlspecialchars($chatbotSuggestionsEn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-suggestions-fr="<?= htmlspecialchars($chatbotSuggestionsFr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
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
            <article class="chat-message chat-message-bot"><p>Hello, I’m Lumda Chris. Ask me about available land and apartments, prices, due diligence, land titles, leases or disputes — in English or French. I’m not a substitute for a notary or lawyer.</p></article>
        </div>

        <div class="chatbot-suggestions" data-chat-suggestions aria-label="Suggested questions"></div>

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
