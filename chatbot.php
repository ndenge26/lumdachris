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

require __DIR__ . '/chatbot-engine.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function chatbotRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function chatbotLeadPrompt(string $stage, string $language): string
{
    $prompts = [
        'name' => [
            'en' => 'What is your full name?',
            'fr' => 'Quel est votre nom complet ?',
        ],
        'phone' => [
            'en' => 'Thank you. What phone number may I pass to our agent?',
            'fr' => 'Merci. Quel numéro de téléphone puis-je transmettre à notre agent ?',
        ],
        'details' => [
            'en' => 'What is your budget range, and which city or neighbourhood are you looking in (or selling in)?',
            'fr' => 'Quelle est votre fourchette de budget et dans quelle ville ou quel quartier cherchez-vous (ou vendez-vous) ?',
        ],
    ];

    return $prompts[$stage][$language] ?? $prompts['name'][$language];
}

function chatbotCancelSuggestion(string $language): string
{
    return $language === 'fr' ? 'Annuler' : 'Cancel';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    chatbotRespond(['error' => 'POST requests only.'], 405);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== 0) {
    chatbotRespond(['error' => 'Send a JSON request.'], 415);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || strlen($rawBody) > 8192) {
    chatbotRespond(['error' => 'Message is too large.'], 413);
}

$request = json_decode($rawBody, true);
if (!is_array($request) || json_last_error() !== JSON_ERROR_NONE) {
    chatbotRespond(['error' => 'Invalid JSON request.'], 400);
}

$providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken = $_SESSION['chatbot_csrf_token'] ?? '';
if (!is_string($providedToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $providedToken)) {
    chatbotRespond(['error' => 'Your chat session expired. Reload the page and try again.'], 403);
}

$message = $request['message'] ?? null;
if (!is_string($message)) {
    chatbotRespond(['error' => 'Enter a text message.'], 422);
}
$message = trim($message);
$messageLength = preg_match_all('/./us', $message);
if ($message === '' || strlen($message) > 4800 || $messageLength === false || $messageLength > 1200 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $message)) {
    chatbotRespond(['error' => 'Enter a message of up to 1,200 characters.'], 422);
}

$language = chatbotDetectLanguage($message, ($request['language'] ?? '') === 'fr' ? 'fr' : 'en');

// Agent hand-off: collect the details step by step, but let the visitor ask a
// question or cancel at any point without losing the conversation.
$lead = $_SESSION['chatbot_lead'] ?? null;
if (is_array($lead) && isset($lead['stage'], $lead['language'])) {
    $language = chatbotDetectLanguage($message, $lead['language'] === 'fr' ? 'fr' : 'en');
    $_SESSION['chatbot_lead']['language'] = $language;
    $normalizedMessage = chatbotNormalize($message);

    if (preg_match('/\b(cancel|stop|annuler|abandonner|arreter|no thanks|non merci)\b/', $normalizedMessage) === 1) {
        unset($_SESSION['chatbot_lead']);
        chatbotRespond([
            'reply' => $language === 'fr'
                ? 'D’accord, j’ai arrêté la prise de coordonnées. Je reste disponible pour vos questions sur les biens, les titres ou les litiges.'
                : 'Okay, I have stopped collecting your details. I am still here for questions about properties, titles or disputes.',
            'language' => $language,
            'suggestions' => chatbotDefaultSuggestions($language),
        ]);
    }

    $isQuestion = str_contains($message, '?')
        || preg_match('/^(what|how|why|where|when|who|can|do|does|is|are|quel|quelle|comment|pourquoi|quand|ou|qui|est ce)\b/', $normalizedMessage) === 1;
    if ($isQuestion) {
        $result = chatbotEngineReply($message, $language, $_SESSION['chatbot_context'] ?? []);
        $_SESSION['chatbot_context'] = $result['context'];
        chatbotRespond([
            'reply' => $result['reply'] . "\n\n" . chatbotLeadPrompt($lead['stage'], $language),
            'language' => $result['language'],
            'suggestions' => [chatbotCancelSuggestion($language)],
        ]);
    }

    if ($lead['stage'] === 'name') {
        $nameLength = preg_match_all('/./us', $message);
        if ($nameLength === false || $nameLength < 2 || $nameLength > 100) {
            chatbotRespond([
                'reply' => $language === 'fr' ? 'Veuillez indiquer votre nom (2 à 100 caractères).' : 'Please enter your name (2 to 100 characters).',
                'language' => $language,
                'suggestions' => [chatbotCancelSuggestion($language)],
            ]);
        }
        $_SESSION['chatbot_lead']['name'] = $message;
        $_SESSION['chatbot_lead']['stage'] = 'phone';
        chatbotRespond([
            'reply' => chatbotLeadPrompt('phone', $language),
            'language' => $language,
            'suggestions' => [chatbotCancelSuggestion($language)],
        ]);
    }

    if ($lead['stage'] === 'phone') {
        $digits = preg_replace('/\D+/', '', $message);
        if (!preg_match('/^\+?[0-9().\s-]{7,30}$/D', $message) || $digits === null || strlen($digits) < 7 || strlen($digits) > 15) {
            chatbotRespond([
                'reply' => $language === 'fr' ? 'Ce numéro semble incomplet. Envoyez un numéro valide avec son indicatif si possible.' : 'That number looks incomplete. Please send a valid phone number, including its country code if possible.',
                'language' => $language,
                'suggestions' => [chatbotCancelSuggestion($language)],
            ]);
        }
        $_SESSION['chatbot_lead']['phone'] = $message;
        $_SESSION['chatbot_lead']['stage'] = 'details';
        chatbotRespond([
            'reply' => chatbotLeadPrompt('details', $language),
            'language' => $language,
            'suggestions' => [chatbotCancelSuggestion($language)],
        ]);
    }

    if ($lead['stage'] === 'details') {
        $detailsLength = preg_match_all('/./us', $message);
        if ($detailsLength === false || $detailsLength < 3 || $detailsLength > 300) {
            chatbotRespond([
                'reply' => $language === 'fr' ? 'Indiquez votre budget et la ville ou le quartier concerné (3 à 300 caractères).' : 'Please include your budget and the relevant city or neighbourhood (3 to 300 characters).',
                'language' => $language,
                'suggestions' => [chatbotCancelSuggestion($language)],
            ]);
        }
        $_SESSION['chatbot_lead']['budget_location'] = $message;
        unset($_SESSION['chatbot_lead']);
        chatbotRespond([
            'reply' => $language === 'fr'
                ? 'Merci. Vos coordonnées et critères sont prêts pour une transmission simulée à un agent. Aucun agent n’a été contacté automatiquement ; pour une prise en charge réelle, envoyez vos coordonnées et un document via la page de vérification du site.'
                : 'Thank you. Your contact details and criteria are ready for a simulated agent hand-off. No agent has been contacted automatically; for a real follow-up, send your details and a document through the due-diligence page on the site.',
            'language' => $language,
            'suggestions' => chatbotDefaultSuggestions($language),
        ]);
    }
}

$result = chatbotEngineReply($message, $language, $_SESSION['chatbot_context'] ?? []);
$_SESSION['chatbot_context'] = $result['context'];

if (($result['action'] ?? null) === 'lead') {
    $intent = preg_match('/\b(sell|vendre|vends|vente)\b/', chatbotNormalize($message)) === 1 ? 'sell' : 'buy';
    $_SESSION['chatbot_lead'] = ['stage' => 'name', 'intent' => $intent, 'language' => $result['language']];
    chatbotRespond([
        'reply' => ($result['language'] === 'fr' ? 'Très bien. ' : 'Great. ') . chatbotLeadPrompt('name', $result['language']),
        'language' => $result['language'],
        'suggestions' => [chatbotCancelSuggestion($result['language'])],
    ]);
}

chatbotRespond([
    'reply' => $result['reply'],
    'language' => $result['language'],
    'suggestions' => $result['suggestions'] ?? chatbotDefaultSuggestions($result['language']),
]);
