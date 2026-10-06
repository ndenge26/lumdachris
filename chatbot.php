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

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function chatbotRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function chatbotNormalize(string $value): string
{
    $value = strtolower($value);
    $value = strtr($value, [
        'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ä' => 'a', 'Ã' => 'a',
        'Ç' => 'c', 'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
        'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i', 'Ñ' => 'n',
        'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Ö' => 'o', 'Õ' => 'o',
        'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u', 'Œ' => 'oe',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'œ' => 'oe',
    ]);

    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

function chatbotContains(string $message, string $pattern): bool
{
    return preg_match($pattern, $message) === 1;
}

function chatbotDomainReply(string $message, string $language): string
{
    $answers = [
        [
            'pattern' => '/\b(due diligence|encumbrance|encumbrances|mortgage|hypothec|registry|land registry|conservation fonciere|verify|verification|check the title|charges|hypotheque)\b/u',
            'en' => 'For due diligence, ask the Conservation Fonciere (Land Registry) for a current certified title search and check registered charges, mortgages, ownership details, and any pending formalities. Have a notary compare the registry record with the original documents and the survey/boundaries before money changes hands. A copy or seller assurance alone is not verification.',
            'fr' => 'Pour une vérification, demandez à la Conservation Foncière un état certifié et récent du titre, puis contrôlez les charges, hypothèques, titulaires et formalités en cours. Un notaire devrait rapprocher le registre des originaux et du plan/bornage avant tout paiement. Une photocopie ou une assurance du vendeur ne suffit pas.',
        ],
        [
            'pattern' => '/\b(titre foncier|land title|land certificate|certificate of ownership|ownership certificate|notary|notaire|private sale|attestation de propriete)\b/u',
            'en' => 'A Titre Foncier is the registered land title recorded at the Conservation Fonciere. A document called a “certificate of ownership” or an attestation is not automatically equivalent to a registered title; its legal effect must be checked. For a formal transfer, involve a Cameroonian notary and complete the required deed and registration formalities before relying on the sale.',
            'fr' => 'Le Titre Foncier est le titre inscrit à la Conservation Foncière. Une « attestation/certificat de propriété » n’est pas automatiquement équivalente à un titre immatriculé; sa portée doit être vérifiée. Pour une mutation, faites intervenir un notaire camerounais et accomplissez les formalités d’acte et d’enregistrement avant de vous fier à la vente.',
        ],
        [
            'pattern' => '/\b(mindcaf|bornage|boundary placement|land registration|register land|registration|land gazette|gazette|immatriculation|publication|delimitation)\b/u',
            'en' => 'Land registration generally involves filing the required application and supporting documents through the competent MINDCAF services, a cadastral inspection and boundary placement (bornage), public notice/publication with an opportunity for objections, and review before a title can issue. The Land Tenure Ordinance No. 74-1 of 6 July 1974 and Decree No. 76-165 of 27 April 1976 are key texts; confirm the current local checklist and publication steps with MINDCAF or a notary.',
            'fr' => 'L’immatriculation implique généralement le dépôt du dossier et des pièces requises auprès des services compétents du MINDCAF, une descente cadastrale avec bornage, une publicité permettant les oppositions, puis l’examen du dossier avant délivrance du titre. Les textes de référence incluent l’ordonnance n° 74-1 du 6 juillet 1974 et le décret n° 76-165 du 27 avril 1976. Confirmez la liste et les publications applicables localement auprès du MINDCAF ou d’un notaire.',
        ],
        [
            'pattern' => '/\b(lease|tenancy|tenant|rent|rental|commercial|ohada|caution|deposit|eviction|notice|bail|loyer|locataire|expulsion|preavis|commercial lease)\b/u',
            'en' => 'Residential tenancy and a business/commercial lease are not the same. OHADA’s Uniform Act on General Commercial Law governs commercial leases when its conditions apply; residential letting is mainly governed by Cameroonian national rules. Put rent, duration, permitted use, repairs, the caution/security deposit and its refund conditions in writing. Do not treat a deposit as automatically forfeited, and use the required written notice and lawful process for termination or eviction; never use self-help. Have a local lawyer review the lease.',
            'fr' => 'La location d’habitation et le bail professionnel/commercial sont distincts. L’Acte uniforme OHADA relatif au droit commercial général régit le bail commercial lorsque ses conditions sont réunies; l’habitation relève principalement des règles nationales camerounaises. Précisez par écrit le loyer, la durée, l’usage, les réparations, la caution et ses conditions de restitution. La caution n’est pas automatiquement perdue; toute résiliation ou expulsion doit suivre les notifications et procédures légales. Faites vérifier le bail par un juriste local.',
        ],
        [
            'pattern' => '/\b(double sale|sold twice|two buyers|inheritance|inherit|heir|family land|familial|chefferie|traditional ruler|chief|property dispute|land dispute|boundary dispute|conflit|litige|succession|heritage|chef traditionnel|double vente)\b/u',
            'en' => 'For a suspected double sale, inheritance conflict, or boundary dispute, preserve the title/search, sale documents, receipts, survey plans, messages, and any court papers. Do not resell, alter boundary markers, or rely on force. A Chefferie may help explain customary or family history, but it does not replace registry checks or the competent courts for formal title disputes. Ask a property lawyer/notary to review the records promptly.',
            'fr' => 'En cas de double vente, conflit successoral ou litige de limites, conservez le titre/état du registre, actes de vente, reçus, plans, messages et documents judiciaires. Ne revendez pas, ne déplacez pas les bornes et n’employez pas la force. La chefferie peut éclairer le contexte coutumier ou familial, mais ne remplace ni les contrôles au registre ni les juridictions compétentes pour un litige de titre. Consultez rapidement un avocat ou un notaire.',
        ],
    ];

    foreach ($answers as $answer) {
        if (chatbotContains($message, $answer['pattern'])) {
            return $answer[$language];
        }
    }

    if (chatbotContains($message, '/\b(hello|hi|hey|good morning|good afternoon|bonjour|bonsoir|salut|coucou)\b/u')) {
        return $language === 'fr'
            ? 'Bonjour. Je peux vous renseigner de manière générale sur les titres fonciers, les baux, les vérifications et les litiges immobiliers au Cameroun. Que souhaitez-vous savoir?'
            : 'Hello. I can share general information about land titles, leases, due diligence, and property disputes in Cameroon. What would you like to know?';
    }

    return $language === 'fr'
        ? 'Je peux vous aider sur l’achat/vente d’un terrain, l’immatriculation MINDCAF, les baux, la vérification d’un titre ou un litige foncier. Pour une décision juridique, consultez un notaire ou un avocat camerounais. Quel sujet vous intéresse?'
        : 'I can help with buying or selling land, MINDCAF registration, leases, title verification, or property disputes. For a legal decision, consult a Cameroonian notary or lawyer. Which topic should we explore?';
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

$language = ($request['language'] ?? '') === 'fr' ? 'fr' : 'en';
$normalizedMessage = chatbotNormalize($message);
if (chatbotContains($normalizedMessage, '/\b(bonjour|bonsoir|salut|merci|je|vous|nous|mon|ma|mes|quel|quelle|quels|quelles|comment|combien|pouvez|pourriez|est-ce|qu\x27est-ce)\b/u')) {
    $language = 'fr';
}

$lead = $_SESSION['chatbot_lead'] ?? null;
if (is_array($lead) && isset($lead['stage'], $lead['language'])) {
    $language = $lead['language'] === 'fr' ? 'fr' : 'en';

    if ($lead['stage'] === 'name') {
        $nameLength = preg_match_all('/./us', $message);
        if ($nameLength === false || $nameLength < 2 || $nameLength > 100) {
            chatbotRespond([
                'reply' => $language === 'fr' ? 'Veuillez indiquer votre nom (2 à 100 caractères).' : 'Please enter your name (2 to 100 characters).',
                'language' => $language,
            ]);
        }
        $_SESSION['chatbot_lead']['name'] = $message;
        $_SESSION['chatbot_lead']['stage'] = 'phone';
        chatbotRespond([
            'reply' => $language === 'fr' ? 'Merci. Quel numéro de téléphone puis-je transmettre à notre agent?' : 'Thank you. What phone number may I pass to our agent?',
            'language' => $language,
        ]);
    }

    if ($lead['stage'] === 'phone') {
        $digits = preg_replace('/\D+/', '', $message);
        if (!preg_match('/^\+?[0-9().\s-]{7,30}$/D', $message) || $digits === null || strlen($digits) < 7 || strlen($digits) > 15) {
            chatbotRespond([
                'reply' => $language === 'fr' ? 'Ce numéro semble incomplet. Envoyez un numéro valide avec son indicatif si possible.' : 'That number looks incomplete. Please send a valid phone number, including its country code if possible.',
                'language' => $language,
            ]);
        }
        $_SESSION['chatbot_lead']['phone'] = $message;
        $_SESSION['chatbot_lead']['stage'] = 'details';
        chatbotRespond([
            'reply' => $language === 'fr' ? 'Quelle est votre fourchette de budget et dans quelle ville ou quel quartier cherchez-vous (ou vendez-vous)?' : 'What is your budget range, and which city or neighbourhood are you looking in (or selling in)?',
            'language' => $language,
        ]);
    }

    if ($lead['stage'] === 'details') {
        $detailsLength = preg_match_all('/./us', $message);
        if ($detailsLength === false || $detailsLength < 3 || $detailsLength > 300) {
            chatbotRespond([
                'reply' => $language === 'fr' ? 'Indiquez votre budget et la ville ou le quartier concerné (3 à 300 caractères).' : 'Please include your budget and the relevant city or neighbourhood (3 to 300 characters).',
                'language' => $language,
            ]);
        }
        $_SESSION['chatbot_lead']['budget_location'] = $message;
        unset($_SESSION['chatbot_lead']);
        chatbotRespond([
            'reply' => $language === 'fr'
                ? 'Merci. Vos coordonnées et critères sont prêts pour une transmission simulée à un agent. Aucun agent n’a été contacté automatiquement; utilisez le formulaire de contact du site pour une prise en charge réelle.'
                : 'Thank you. Your contact details and criteria are ready for a simulated agent hand-off. No agent has been contacted automatically; use the site contact form for a real follow-up.',
            'language' => $language,
            'handoff' => true,
        ]);
    }
}

$sellIntent = chatbotContains($normalizedMessage, '/\b(i want to sell|i would like to sell|looking to sell|want to sell|sell my land|sell my property|list my land|list my property|i am selling|i\x27m selling|je veux vendre|je cherche a vendre|je souhaite vendre|je vends|mon terrain est a vendre)\b/u');
$buyIntent = $sellIntent || chatbotContains($normalizedMessage, '/\b(i want to buy|i would like to buy|i am looking to buy|looking to buy|want to buy|interested in buying|buying land|buying property|je veux acheter|je cherche a acheter|je souhaite acheter|j\x27aimerais acheter)\b/u');
if ($buyIntent) {
    $_SESSION['chatbot_lead'] = [
        'stage' => 'name',
        'intent' => $sellIntent ? 'sell' : 'buy',
        'language' => $language,
    ];
    chatbotRespond([
        'reply' => $language === 'fr'
            ? 'Je peux préparer une mise en relation simulée pour votre projet. Quel est votre nom complet?'
            : 'I can prepare a simulated agent hand-off for your property plans. What is your full name?',
        'language' => $language,
    ]);
}

$reply = chatbotDomainReply($normalizedMessage, $language);
chatbotRespond(['reply' => $reply, 'language' => $language]);
