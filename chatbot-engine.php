<?php
declare(strict_types=1);

/**
 * Lumda Chris assistant engine.
 *
 * The assistant is grounded: it answers property questions from the site's own
 * listing data and a fixed, bilingual knowledge base, keeps a short conversation
 * context in the session, and asks a clarifying question instead of inventing an
 * answer it cannot support.
 */

function chatbotKnowledgeBase(): array
{
    static $knowledge = null;
    if ($knowledge === null) {
        $knowledge = require __DIR__ . '/chatbot-knowledge.php';
    }
    return $knowledge;
}

function chatbotListings(): array
{
    static $listings = null;
    if ($listings === null) {
        $listings = require __DIR__ . '/data/listings.php';
    }
    return $listings;
}

function chatbotNormalize(string $value): string
{
    $value = mb_strtolower($value, 'UTF-8');
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
        '’' => "'", '‘' => "'", '–' => '-', '—' => '-',
    ]);

    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

/** Whole-word (or whole-phrase) match on already-normalized text. */
function chatbotTextHas(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return false;
    }
    return preg_match('/(?<![a-z0-9])' . preg_quote($needle, '/') . '(?![a-z0-9])/u', $haystack) === 1;
}

function chatbotDetectLanguage(string $message, string $fallback): string
{
    $fallback = $fallback === 'fr' ? 'fr' : 'en';
    $normalized = chatbotNormalize($message);
    $french = preg_match('/[àâäçéèêëîïôöùûüœ]/u', mb_strtolower($message, 'UTF-8')) === 1 ? 2 : 0;
    $english = 0;

    $frenchWords = ['je', 'tu', 'elle', 'nous', 'vous', 'ils', 'mon', 'ma', 'mes', 'notre', 'votre', 'le', 'la', 'les', 'une', 'des', 'du', 'au', 'aux', 'et', 'ou', 'pour', 'dans', 'sur', 'avec', 'sans', 'est', 'sont', 'etre', 'avoir', 'veux', 'voudrais', 'souhaite', 'cherche', 'acheter', 'vendre', 'terrain', 'terrains', 'titre', 'foncier', 'bail', 'loyer', 'prix', 'combien', 'comment', 'pourquoi', 'quand', 'quel', 'quelle', 'bonjour', 'bonsoir', 'salut', 'merci', 'oui', 'non', 'verifier', 'notaire', 'acte', 'vente', 'location', 'caution', 'litige', 'succession', 'bornage', 'etranger', 'pieces', 'dossier'];
    $englishWords = ['hello', 'hi', 'hey', 'thanks', 'thank', 'please', 'i', 'you', 'we', 'my', 'our', 'what', 'which', 'how', 'why', 'when', 'where', 'is', 'are', 'can', 'could', 'would', 'should', 'want', 'need', 'buy', 'sell', 'land', 'title', 'lease', 'rent', 'price', 'cost', 'property', 'apartment', 'house', 'plot', 'with', 'for', 'in', 'on', 'the', 'do', 'does', 'have', 'about', 'tell', 'show', 'know', 'help'];

    foreach ($frenchWords as $word) {
        if (chatbotTextHas($normalized, $word)) {
            $french++;
        }
    }
    foreach ($englishWords as $word) {
        if (chatbotTextHas($normalized, $word)) {
            $english++;
        }
    }

    if ($french > $english) {
        return 'fr';
    }
    if ($english > $french) {
        return 'en';
    }
    return $fallback;
}

function chatbotExtractBudget(string $normalized): ?array
{
    $direction = 'around';
    if (preg_match('/\b(under|below|less than|max|maximum|up to|at most|no more than|moins de|jusqu a|au plus|pas plus de)\b/', $normalized) === 1) {
        $direction = 'max';
    } elseif (preg_match('/\b(above|over|more than|at least|plus de|a partir de|au moins)\b/', $normalized) === 1) {
        $direction = 'min';
    }

    $value = null;
    if (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*(million|millions|milliard|milliards|m|k|thousand)\b/', $normalized, $match) === 1) {
        $number = (float) str_replace(',', '.', $match[1]);
        $multiplier = match ($match[2]) {
            'million', 'millions', 'm' => 1000000,
            'milliard', 'milliards' => 1000000000,
            default => 1000,
        };
        $value = (int) round($number * $multiplier);
    } elseif (preg_match('/\b([0-9][0-9\s.,]{4,})\b/', $normalized, $match) === 1) {
        $hasMoneyCue = preg_match('/\b(xaf|fcfa|cfa|francs?|prix|price|budget|cout|cost|combien|how much|afford|cheap|expensive)\b/', $normalized) === 1;
        if ($hasMoneyCue || $direction !== 'around') {
            $candidate = (int) preg_replace('/\D+/', '', $match[1]);
            if ($candidate >= 100000) {
                $value = $candidate;
            }
        }
    }

    if ($value === null || $value <= 0) {
        return null;
    }

    return ['value' => $value, 'direction' => $direction];
}

function chatbotExtractSlots(string $normalized): array
{
    $slots = [];

    $cities = ['yaounde' => 'Yaounde', 'douala' => 'Douala', 'kribi' => 'Kribi', 'buea' => 'Buea', 'limbe' => 'Limbe', 'bafoussam' => 'Bafoussam', 'bamenda' => 'Bamenda', 'garoua' => 'Garoua', 'ngaoundere' => 'Ngaoundere', 'ebolowa' => 'Ebolowa', 'bertoua' => 'Bertoua', 'dschang' => 'Dschang', 'edea' => 'Edea', 'kumba' => 'Kumba', 'maroua' => 'Maroua', 'mbalmayo' => 'Mbalmayo', 'sangmelima' => 'Sangmelima', 'foumban' => 'Foumban', 'tiko' => 'Tiko'];
    $neighbourhoods = ['bastos' => 'Yaounde', 'odza' => 'Yaounde', 'bonapriso' => 'Douala', 'yassa' => 'Douala', 'ngoye' => 'Kribi'];
    foreach ($neighbourhoods as $place => $city) {
        if (chatbotTextHas($normalized, $place)) {
            $slots['city'] = $city;
            $slots['neighbourhood'] = ucfirst($place);
            break;
        }
    }
    if (!isset($slots['city'])) {
        foreach ($cities as $alias => $city) {
            if (chatbotTextHas($normalized, $alias)) {
                $slots['city'] = $city;
                break;
            }
        }
    }

    if (preg_match('/\b(a vendre|for sale)\b/', $normalized) === 1) {
        $slots['transaction'] = 'buy';
    } elseif (preg_match('/\b(sell|selling|vendre|vends|vente)\b/', $normalized) === 1) {
        $slots['transaction'] = 'sell';
    }
    if (preg_match('/\b(rent|renting|rental|lease|leasing|tenant|louer|location|bail|loyer)\b/', $normalized) === 1) {
        $slots['transaction'] = 'lease';
    }
    if (!isset($slots['transaction']) && preg_match('/\b(buy|buying|purchase|purchasing|acheter|achat|sale)\b/', $normalized) === 1) {
        $slots['transaction'] = 'buy';
    }

    if (preg_match('/\b(land|plot|plots|parcel|parcelle|terrain|terrains|hectare|hectares)\b/', $normalized) === 1) {
        $slots['property'] = 'land';
    } elseif (preg_match('/\b(apartment|apartments|flat|house|home|studio|villa|appartement|appartements|maison|logement)\b/', $normalized) === 1) {
        $slots['property'] = 'apartment';
    }

    if (preg_match('/\b(titre foncier|land title|certificate|certificat|verified|verifie|with title|avec titre)\b/', $normalized) === 1) {
        $slots['certificate'] = true;
    }

    $budget = chatbotExtractBudget($normalized);
    if ($budget !== null) {
        $slots['budget'] = $budget;
    }

    return $slots;
}

function chatbotMergeSlots(array $previous, array $current): array
{
    $merged = $previous;
    foreach (['city', 'neighbourhood', 'transaction', 'property', 'certificate', 'budget'] as $key) {
        if (array_key_exists($key, $current)) {
            $merged[$key] = $current[$key];
        }
    }
    return $merged;
}

function chatbotTopicScores(string $normalized): array
{
    $scores = [];
    foreach (chatbotKnowledgeBase() as $id => $topic) {
        $score = 0;
        foreach ($topic['keywords'] ?? [] as $term => $weight) {
            if (chatbotTextHas($normalized, (string) $term)) {
                $score += (int) $weight;
            }
        }
        if ($score > 0) {
            $scores[$id] = $score;
        }
    }
    arsort($scores);
    return $scores;
}

function chatbotDefaultSuggestions(string $language): array
{
    return $language === 'fr'
        ? ['Montrez-moi les terrains disponibles', 'Comment vérifier un titre foncier ?', 'Parler à un agent']
        : ['Show me available land', 'How do I verify a land title?', 'Talk to an agent'];
}

function chatbotFilterListings(array $listings, array $slots): array
{
    return array_filter($listings, static function (array $listing) use ($slots): bool {
        if (isset($slots['city']) && $listing['city'] !== $slots['city']) {
            return false;
        }
        if (isset($slots['transaction']) && $listing['transaction'] !== $slots['transaction']) {
            return false;
        }
        if (isset($slots['property']) && $listing['property'] !== $slots['property']) {
            return false;
        }
        if (!empty($slots['certificate']) && !in_array($listing['certificate'], ['Certificate verified', 'Title documents verified'], true)) {
            return false;
        }
        if (isset($slots['budget']['value'])) {
            $value = (int) $slots['budget']['value'];
            $direction = $slots['budget']['direction'] ?? 'around';
            $price = (int) $listing['price'];
            if ($direction === 'max' && $price > $value) {
                return false;
            }
            if ($direction === 'min' && $price < $value) {
                return false;
            }
            if ($direction === 'around' && ($price < $value * 0.75 || $price > $value * 1.25)) {
                return false;
            }
        }
        return true;
    });
}

function chatbotFormatPrice(int $price): string
{
    return number_format($price, 0, ',', ',') . ' XAF';
}

function chatbotFormatListing(array $listing, string $language): string
{
    $type = $listing['property'] === 'land'
        ? ($language === 'fr' ? 'terrain' : 'land')
        : ($language === 'fr' ? 'appartement' : 'apartment');
    $deal = $listing['transaction'] === 'lease'
        ? ($language === 'fr' ? 'en location' : 'for lease')
        : ($language === 'fr' ? 'à vendre' : 'for sale');
    $price = chatbotFormatPrice((int) $listing['price']) . ($listing['transaction'] === 'lease' ? ($language === 'fr' ? ' /mois' : ' /month') : '');
    $certificate = [
        'Certificate verified' => $language === 'fr' ? 'certificat vérifié' : 'certificate verified',
        'Title documents verified' => $language === 'fr' ? 'documents du titre vérifiés' : 'title documents verified',
        'Certificate under review' => $language === 'fr' ? 'certificat en cours de vérification' : 'certificate under review',
    ][$listing['certificate']] ?? $listing['certificate'];

    return '• ' . $listing['title'] . ' — ' . $listing['neighborhood'] . ' · ' . $type . ' ' . $deal . ', ' . $listing['size'] . ', ' . $price . ' (' . $certificate . ')';
}

function chatbotListingIntro(array $slots, string $language): string
{
    $city = $slots['city'] ?? '';
    $property = $slots['property'] ?? '';
    $transaction = $slots['transaction'] ?? '';

    if ($language === 'fr') {
        $what = $property === 'land' ? 'terrains' : ($property === 'apartment' ? 'appartements' : 'biens');
        $deal = $transaction === 'buy' ? 'à vendre' : ($transaction === 'lease' ? 'en location' : '');
        return 'Voici les ' . $what . ($deal !== '' ? ' ' . $deal : '') . ($city !== '' ? ' à ' . $city : '') . ' :';
    }

    $what = $property === 'land' ? 'land plots' : ($property === 'apartment' ? 'apartments' : 'properties');
    $deal = $transaction === 'buy' ? 'for sale' : ($transaction === 'lease' ? 'for lease' : '');
    return 'Here are the ' . $what . ($deal !== '' ? ' ' . $deal : '') . ($city !== '' ? ' in ' . $city : '') . ':';
}

function chatbotListingNote(string $language): string
{
    return $language === 'fr'
        ? 'Ce sont des annonces illustratives : confirmez le prix, les documents et les limites avant de vous engager.'
        : 'These are illustrative listings, so confirm the price, documents and boundaries before you commit.';
}

function chatbotListingSuggestions(string $language, bool $hasMore): array
{
    if ($hasMore) {
        return $language === 'fr'
            ? ['Montrez plus d’options', 'Parler à un agent']
            : ['Show more options', 'Talk to an agent'];
    }
    return $language === 'fr'
        ? ['Parler à un agent', 'Comment vérifier un titre foncier ?']
        : ['Talk to an agent', 'How do I verify a land title?'];
}

function chatbotListingSearchReply(array $slots, string $language, int $offset = 0): array
{
    $listings = chatbotListings();
    if (!isset($slots['transaction']) && isset($slots['budget']['value']) && (int) $slots['budget']['value'] <= 2000000) {
        $slots['transaction'] = 'lease';
    }

    $matches = chatbotFilterListings($listings, $slots);
    if ($matches === []) {
        return chatbotListingEmptyReply($slots, $language, $offset);
    }

    $keys = array_keys($matches);
    if ($offset >= count($keys)) {
        return chatbotListingEmptyReply($slots, $language, $offset);
    }
    $pageKeys = array_slice($keys, $offset, 3);
    $lines = [];
    foreach ($pageKeys as $key) {
        $lines[] = chatbotFormatListing($listings[$key], $language);
    }

    $text = chatbotListingIntro($slots, $language) . "\n" . implode("\n", $lines);
    $remaining = count($keys) - ($offset + count($pageKeys));
    if ($remaining > 0) {
        $text .= "\n" . ($language === 'fr'
            ? "Il reste {$remaining} autre(s) option(s) — dites « plus » pour les voir."
            : "I have {$remaining} more — say “more” to see them.");
    }
    $text .= "\n\n" . chatbotListingNote($language);

    return [
        'reply' => $text,
        'language' => $language,
        'topic' => 'listings',
        'suggestions' => chatbotListingSuggestions($language, $remaining > 0),
        'context' => [
            'topic' => 'listings',
            'slots' => $slots,
            'listing_keys' => $keys,
            'offset' => $offset + count($pageKeys),
        ],
    ];
}

function chatbotListingEmptyReply(array $slots, string $language, int $offset): array
{
    $listings = chatbotListings();
    $city = $slots['city'] ?? null;
    $keys = [];

    if ($offset > 0) {
        $reply = $language === 'fr'
            ? 'C’est tout ce que j’ai pour ces critères.'
            : 'That is everything I have for those filters.';
        return [
            'reply' => $reply . "\n\n" . chatbotListingNote($language),
            'language' => $language,
            'topic' => 'listings',
            'suggestions' => chatbotListingSuggestions($language, false),
            'context' => ['topic' => 'listings', 'slots' => $slots, 'listing_keys' => [], 'offset' => 0],
        ];
    }

    $cityMatches = $city !== null ? chatbotFilterListings($listings, ['city' => $city]) : [];
    if ($cityMatches !== []) {
        $keys = array_keys($cityMatches);
        $reply = $language === 'fr'
            ? "Je n’ai pas d’annonce qui corresponde à ces critères. Voici ce que j’ai à {$city} :"
            : "I don't have a listing that matches those filters. Here is what I have in {$city}:";
    } else {
        $keys = array_keys(array_slice($listings, 0, 2, true));
        $reply = $language === 'fr'
            ? ($city !== null
                ? "Je n’ai pas d’annonce active à {$city}. Le site propose actuellement des biens à Yaoundé, Douala et Kribi. En voici deux :"
                : 'Je n’ai pas d’annonce qui corresponde à ces critères. Voici ce que je peux vous montrer à la place :')
            : ($city !== null
                ? "I don't have an active listing in {$city}. The site currently lists properties in Yaounde, Douala and Kribi. Here are two:"
                : "I don't have a listing that matches those filters. Here is what I can show you instead:");
    }

    $lines = [];
    foreach (array_slice($keys, 0, 2) as $key) {
        $lines[] = chatbotFormatListing($listings[$key], $language);
    }
    $reply .= "\n" . implode("\n", $lines);
    $reply .= "\n" . ($language === 'fr'
        ? 'Donnez-moi une ville (Yaoundé, Douala ou Kribi) et un budget, et je précise la recherche.'
        : 'Tell me a city (Yaounde, Douala or Kribi) and a budget and I will narrow it down.');
    $reply .= "\n\n" . chatbotListingNote($language);

    return [
        'reply' => $reply,
        'language' => $language,
        'topic' => 'listings',
        'suggestions' => chatbotListingSuggestions($language, false),
        'context' => ['topic' => 'listings', 'slots' => $slots, 'listing_keys' => $keys, 'offset' => count($keys)],
    ];
}

function chatbotListingDetailReply(array $listing, string $language): array
{
    $type = $listing['property'] === 'land'
        ? ($language === 'fr' ? 'Terrain' : 'Land')
        : ($language === 'fr' ? 'Appartement' : 'Apartment');
    $deal = $listing['transaction'] === 'lease'
        ? ($language === 'fr' ? 'en location' : 'for lease')
        : ($language === 'fr' ? 'à vendre' : 'for sale');
    $price = chatbotFormatPrice((int) $listing['price']) . ($listing['transaction'] === 'lease' ? ($language === 'fr' ? ' par mois' : ' per month') : '');
    $certificate = [
        'Certificate verified' => $language === 'fr' ? 'certificat vérifié' : 'certificate verified',
        'Title documents verified' => $language === 'fr' ? 'documents du titre vérifiés' : 'title documents verified',
        'Certificate under review' => $language === 'fr' ? 'certificat en cours de vérification' : 'certificate under review',
    ][$listing['certificate']] ?? $listing['certificate'];

    $reply = $language === 'fr'
        ? "Voici « {$listing['title']} » :\n• Quartier : {$listing['neighborhood']}\n• Type : {$type}, {$deal}\n• Taille : {$listing['size']}\n• Prix : {$price}\n• Documents : {$certificate}\n\nConfirmez le prix, le titre et les limites directement avant de vous engager. Voulez-vous qu’un agent vous recontacte ?"
        : "Here are the details for “{$listing['title']}”:\n• Location: {$listing['neighborhood']}\n• Type: {$type}, {$deal}\n• Size: {$listing['size']}\n• Price: {$price}\n• Documents: {$certificate}\n\nConfirm the price, title and boundaries directly before you commit. Would you like an agent to follow up?";

    return [
        'reply' => $reply,
        'language' => $language,
        'topic' => 'listing_detail',
        'suggestions' => $language === 'fr'
            ? ['Parler à un agent', 'Comment vérifier un titre foncier ?']
            : ['Talk to an agent', 'How do I verify a land title?'],
        'context' => [
            'topic' => 'listing_detail',
            'slots' => [],
            'listing_keys' => [],
            'offset' => 0,
        ],
    ];
}

function chatbotPriceReply(array $slots, string $language): array
{
    $listings = chatbotListings();
    $city = $slots['city'] ?? null;
    $sale = [];
    $rent = [];
    foreach ($listings as $listing) {
        if ($city !== null && $listing['city'] !== $city) {
            continue;
        }
        if ($listing['transaction'] === 'buy') {
            $sale[] = (int) $listing['price'];
        } else {
            $rent[] = (int) $listing['price'];
        }
    }

    if ($city !== null && $sale === [] && $rent === []) {
        $reply = $language === 'fr'
            ? "Je n’ai pas d’annonce active à {$city} pour donner une fourchette de prix. Le site propose actuellement des biens à Yaoundé, Douala et Kribi. Dites-moi une de ces villes et je vous donne les prix."
            : "I don't have an active listing in {$city} to quote a price range from. The site currently lists properties in Yaounde, Douala and Kribi. Name one of those and I will give you the prices.";
    } elseif ($language === 'fr') {
        $parts = [];
        if ($sale !== []) {
            $parts[] = min($sale) === max($sale)
                ? 'le terrain à vendre est à ' . chatbotFormatPrice(min($sale))
                : 'le terrain à vendre va de ' . chatbotFormatPrice(min($sale)) . ' à ' . chatbotFormatPrice(max($sale));
        }
        if ($rent !== []) {
            $parts[] = min($rent) === max($rent)
                ? 'l’appartement en location est à ' . chatbotFormatPrice(min($rent)) . ' par mois'
                : 'les appartements en location de ' . chatbotFormatPrice(min($rent)) . ' à ' . chatbotFormatPrice(max($rent)) . ' par mois';
        }
        $where = $city !== null ? ' à ' . $city : ' dans les annonces actuelles';
        $reply = 'D’après les annonces du site' . $where . ', ' . implode(' et ', $parts) . '. Les prix varient beaucoup selon le quartier : comparez au moins trois ventes récentes avant de fixer un prix.';
    } else {
        $parts = [];
        if ($sale !== []) {
            $parts[] = min($sale) === max($sale)
                ? 'land for sale is at ' . chatbotFormatPrice(min($sale))
                : 'land for sale runs from ' . chatbotFormatPrice(min($sale)) . ' to ' . chatbotFormatPrice(max($sale));
        }
        if ($rent !== []) {
            $parts[] = min($rent) === max($rent)
                ? 'apartments for lease are at ' . chatbotFormatPrice(min($rent)) . ' per month'
                : 'apartments for lease from ' . chatbotFormatPrice(min($rent)) . ' to ' . chatbotFormatPrice(max($rent)) . ' per month';
        }
        $where = $city !== null ? ' in ' . $city : ' across the current listings';
        $reply = 'Based on the listings on the site' . $where . ', ' . implode(' and ', $parts) . '. Prices vary a lot by neighbourhood, so compare at least three recent sales nearby before agreeing a price.';
    }

    return [
        'reply' => $reply,
        'language' => $language,
        'topic' => 'market_prices',
        'suggestions' => $language === 'fr'
            ? ['Montrez-moi des terrains à vendre', 'Parler à un agent']
            : ['Show me land for sale', 'Talk to an agent'],
        'context' => [
            'topic' => 'market_prices',
            'slots' => $slots,
            'listing_keys' => [],
            'offset' => 0,
        ],
    ];
}

function chatbotStaticReply(string $topicId, string $language, array $slots): array
{
    $knowledge = chatbotKnowledgeBase();
    $topic = $knowledge[$topicId] ?? null;
    if ($topic === null) {
        return chatbotFallbackReply($language, $slots);
    }

    return [
        'reply' => $topic['answer'][$language] ?? $topic['answer']['en'],
        'language' => $language,
        'topic' => $topicId,
        'suggestions' => $topic['suggestions'][$language] ?? chatbotDefaultSuggestions($language),
        'action' => $topicId === 'agent_handoff' ? 'lead' : null,
        'context' => [
            'topic' => $topicId,
            'slots' => $slots,
            'listing_keys' => [],
            'offset' => 0,
        ],
    ];
}

function chatbotFallbackReply(string $language, array $slots = []): array
{
    return [
        'reply' => $language === 'fr'
            ? "Je suis spécialisé dans l’immobilier au Cameroun, donc je ne peux pas répondre à cela de façon fiable. Je peux vous aider sur les annonces disponibles, l’achat ou la vente, la vérification, les titres fonciers, les baux et les litiges. Lequel vous intéresse ?"
            : "I'm focused on property in Cameroon, so I can't answer that reliably. I can help with available listings, buying or selling, due diligence, titles, leases and land disputes. Which one would you like?",
        'language' => $language,
        'topic' => 'out_of_scope',
        'suggestions' => chatbotDefaultSuggestions($language),
        'context' => [
            'topic' => 'out_of_scope',
            'slots' => $slots,
            'listing_keys' => [],
            'offset' => 0,
        ],
    ];
}

function chatbotFindListingByText(string $normalized): ?array
{
    foreach (chatbotListings() as $key => $listing) {
        $place = trim(explode(',', $listing['neighborhood'])[0]);
        $placeNormalized = chatbotNormalize($place);
        if (strlen($placeNormalized) >= 4 && $placeNormalized !== 'near' && chatbotTextHas($normalized, $placeNormalized)) {
            return ['key' => $key, 'listing' => $listing];
        }
        if (chatbotTextHas($normalized, chatbotNormalize($listing['title']))) {
            return ['key' => $key, 'listing' => $listing];
        }
    }
    return null;
}

function chatbotListingFromContext(string $normalized, array $context): ?array
{
    $keys = $context['listing_keys'] ?? [];
    if (!is_array($keys) || $keys === []) {
        return null;
    }

    $index = null;
    if (preg_match('/\b(first|1st|premier|premiere)\b/', $normalized) === 1) {
        $index = 0;
    } elseif (preg_match('/\b(second|2nd|deuxieme)\b/', $normalized) === 1) {
        $index = 1;
    } elseif (preg_match('/\b(third|3rd|troisieme)\b/', $normalized) === 1) {
        $index = 2;
    }
    if ($index === null || !isset($keys[$index])) {
        return null;
    }

    $listings = chatbotListings();
    $key = (int) $keys[$index];
    if (!isset($listings[$key])) {
        return null;
    }
    return ['key' => $key, 'listing' => $listings[$key]];
}

function chatbotEngineReply(string $message, string $language, array $context): array
{
    $normalized = chatbotNormalize($message);
    $wordCount = str_word_count($normalized);
    $slots = chatbotExtractSlots($normalized);
    $previousSlots = is_array($context['slots'] ?? null) ? $context['slots'] : [];
    $mergedSlots = chatbotMergeSlots($previousSlots, $slots);
    $scores = chatbotTopicScores($normalized);
    $hasListingCue = preg_match('/\b(land|plots?|properties|property|apartments?|houses?|rent|rental|buy|sell|available|listings?|terrains?|maisons?|appartements?|louer|acheter|vendre|biens?|what about|how about|and in|et a|et pour|sinon)\b/', $normalized) === 1;

    // A new, explicit search drops a budget left over from the previous one.
    if ($hasListingCue && !isset($slots['budget']) && isset($mergedSlots['budget'])) {
        unset($mergedSlots['budget']);
    }

    // 1. A detail request for a listing already shown, or named by its area.
    if (($context['topic'] ?? '') === 'listings' || ($context['topic'] ?? '') === 'listing_detail') {
        $fromContext = chatbotListingFromContext($normalized, $context);
        if ($fromContext !== null) {
            return chatbotListingDetailReply($fromContext['listing'], $language);
        }
    }
    $namedListing = chatbotFindListingByText($normalized);
    if ($namedListing !== null && preg_match('/\b(tell me|details|about|info|price|how much|combien|parlez|details|infos)\b/', $normalized) === 1) {
        return chatbotListingDetailReply($namedListing['listing'], $language);
    }

    // 2. "more" continues the previous listing search.
    if (preg_match('/\b(more|others|other options|next|plus|autres|encore|suivant)\b/', $normalized) === 1
        && ($context['topic'] ?? '') === 'listings'
        && !empty($context['listing_keys'])) {
        return chatbotListingSearchReply($mergedSlots, $language, (int) ($context['offset'] ?? 0));
    }

    // 3. Split social topics from property topics.
    $socialTopics = ['greeting' => true, 'thanks' => true, 'goodbye' => true, 'capabilities' => true];
    $bestTopic = null;
    $bestScore = 0;
    $bestSocial = null;
    $bestSocialScore = 0;
    foreach ($scores as $id => $score) {
        if (isset($socialTopics[$id])) {
            if ($score > $bestSocialScore) {
                $bestSocial = $id;
                $bestSocialScore = $score;
            }
            continue;
        }
        if ($score > $bestScore) {
            $bestTopic = $id;
            $bestScore = $score;
        }
    }

    if ($bestSocial !== null && $bestTopic === null && $slots === [] && $wordCount <= 10) {
        return chatbotStaticReply($bestSocial, $language, $mergedSlots);
    }

    // 4. A property topic with a supported answer.
    if ($bestTopic !== null && $bestScore >= 2) {
        $hasConcreteCriteria = isset($slots['city']) || isset($slots['property']) || isset($slots['budget']);
        if ($bestTopic === 'listings') {
            return chatbotListingSearchReply($mergedSlots, $language);
        }
        if ($bestTopic === 'market_prices') {
            return chatbotPriceReply($mergedSlots, $language);
        }
        if ($bestTopic === 'buy_process' && $hasConcreteCriteria) {
            return chatbotListingSearchReply($mergedSlots, $language);
        }
        return chatbotStaticReply($bestTopic, $language, $mergedSlots);
    }

    // 5. A selling request without a knowledge topic still gets the seller guidance.
    if (($mergedSlots['transaction'] ?? '') === 'sell') {
        return chatbotStaticReply('sell_process', $language, $mergedSlots);
    }

    // 6. A property search with slots, or a short follow-up on the previous search.
    $hasQuestionWord = preg_match('/\b(what|which|how|why|when|where|who|is|are|can|do|does|combien|comment|pourquoi|quand|ou|qui|quel|quelle)\b/', $normalized) === 1;
    $isShortFollowUp = ($context['topic'] ?? '') === 'listings' && !$hasQuestionWord && $wordCount <= 6;
    if ($slots !== [] && ($hasListingCue || $isShortFollowUp)) {
        return chatbotListingSearchReply($mergedSlots, $language);
    }

    return chatbotFallbackReply($language);
}
