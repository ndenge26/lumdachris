<?php
declare(strict_types=1);

/**
 * Knowledge base for the Lumda Chris property assistant.
 *
 * Every entry has weighted keywords (matched against the accent-stripped,
 * lower-cased message) and a bilingual answer. Answers stay within general,
 * verifiable guidance for Cameroon and point to a notary, lawyer or MINDCAF
 * whenever a legal decision is involved. The `listings` and `market_prices`
 * answers are composed at runtime from active database listings so the
 * assistant never quotes a property the site does not show.
 */
return [
    'greeting' => [
        'keywords' => ['hello' => 5, 'hi' => 4, 'hey' => 4, 'good morning' => 5, 'good afternoon' => 5, 'good evening' => 5, 'bonjour' => 5, 'bonsoir' => 5, 'salut' => 4, 'coucou' => 4],
        'answer' => [
            'en' => "Hello — I'm LumidaChris, your property guide for Cameroon. I can help with available land, houses and apartments, due diligence, land titles, leases, prices and land disputes, in English or French. What would you like to know?",
            'fr' => "Bonjour — je suis LumidaChris, votre guide immobilier au Cameroun. Je peux vous aider sur les terrains, maisons et appartements disponibles, la vérification, les titres fonciers, les baux, les prix et les litiges, en français ou en anglais. Que souhaitez-vous savoir ?",
        ],
        'suggestions' => [
            'en' => ['Show me available land', 'How do I verify a land title?', 'Explain the buying process'],
            'fr' => ['Montrez-moi les terrains disponibles', 'Comment vérifier un titre foncier ?', 'Expliquez le processus d’achat'],
        ],
    ],
    'capabilities' => [
        'keywords' => ['what can you do' => 5, 'who are you' => 5, 'what do you do' => 4, 'how can you help' => 5, 'que peux tu faire' => 5, 'qui es tu' => 4, 'comment peux tu aider' => 5, 'what can i ask' => 4],
        'answer' => [
            'en' => "I can search the properties on this site, explain how to buy or sell safely, walk you through due diligence and land titles, explain leases and land disputes, and take your details for an agent. Ask in English or French — what would you like to start with?",
            'fr' => "Je peux chercher les biens de ce site, expliquer comment acheter ou vendre en sécurité, détailler la vérification et le Titre Foncier, expliquer les baux et les litiges, et prendre vos coordonnées pour un agent. Posez votre question en français ou en anglais — par quoi commencer ?",
        ],
        'suggestions' => [
            'en' => ['Show me available land', 'How do I verify a land title?', 'Talk to an agent'],
            'fr' => ['Montrez-moi les terrains disponibles', 'Comment vérifier un titre foncier ?', 'Parler à un agent'],
        ],
    ],
    'listings' => [
        'keywords' => ['available' => 2, 'properties' => 2, 'listing' => 3, 'listings' => 3, 'annonces' => 3, 'biens disponibles' => 4, 'disponible' => 2, 'show me' => 3, 'montrez' => 3, 'do you have' => 4, 'avez vous' => 4, 'what do you have' => 4, 'options' => 2, 'for sale' => 2, 'a vendre' => 2, 'for lease' => 2, 'en location' => 2],
        'answer' => [
            'en' => 'Let me look that up in the current listings.',
            'fr' => 'Je vérifie dans les annonces actuelles.',
        ],
        'suggestions' => [
            'en' => ['Show more options', 'Talk to an agent', 'How do I verify a land title?'],
            'fr' => ['Montrez plus d’options', 'Parler à un agent', 'Comment vérifier un titre foncier ?'],
        ],
    ],
    'market_prices' => [
        'keywords' => ['how much' => 4, 'price' => 3, 'prices' => 3, 'combien' => 4, 'prix' => 3, 'market' => 3, 'per square metre' => 4, 'per m2' => 4, 'value of land' => 4, 'valeur' => 2, 'expensive' => 3, 'cheap' => 3, 'affordable' => 3, 'cout' => 2],
        'answer' => [
            'en' => 'Prices vary a lot by city and neighbourhood, so compare at least three recent sales nearby before agreeing a price.',
            'fr' => 'Les prix varient beaucoup selon la ville et le quartier : comparez au moins trois ventes récentes avant de fixer un prix.',
        ],
        'suggestions' => [
            'en' => ['Show me land in Douala', 'Show me apartments in Yaounde', 'Talk to an agent'],
            'fr' => ['Montrez-moi des terrains à Douala', 'Montrez-moi des appartements à Yaoundé', 'Parler à un agent'],
        ],
    ],
    'due_diligence' => [
        'keywords' => ['due diligence' => 5, 'encumbrance' => 4, 'encumbrances' => 4, 'mortgage' => 3, 'hypothec' => 3, 'hypotheque' => 4, 'land registry' => 4, 'conservation fonciere' => 5, 'check the title' => 4, 'etat du titre' => 4, 'registry' => 3, 'verify' => 2, 'verification' => 3, 'verifier' => 3, 'charges' => 2],
        'answer' => [
            'en' => "For due diligence, get a current certified search of the land register (état du titre) at the Conservation Foncière, then check the registered owner, any mortgage or charge, and pending formalities. Have a notary compare the title, the seller's ID and the survey plan with the boundaries on the ground before any money changes hands. A photocopy or the seller's assurance is not verification. I can help you start a due-diligence request if you tell me the location.",
            'fr' => "Pour une vérification, demandez à la Conservation Foncière un état certifié et récent du titre, puis contrôlez le titulaire, les charges ou hypothèques et les formalités en cours. Un notaire doit comparer le titre, la pièce d'identité du vendeur et le plan de bornage avec les limites sur le terrain avant tout paiement. Une photocopie ou l'assurance du vendeur ne suffit pas. Je peux vous aider à lancer une demande de vérification si vous m'indiquez la localisation.",
        ],
        'suggestions' => [
            'en' => ['What documents do I need?', 'Start a due-diligence request', 'Talk to an agent'],
            'fr' => ['Quels documents faut-il ?', 'Lancer une vérification', 'Parler à un agent'],
        ],
    ],
    'land_title' => [
        'keywords' => ['titre foncier' => 5, 'land title' => 5, 'land certificate' => 4, 'certificate of ownership' => 4, 'ownership certificate' => 4, 'title deed' => 4, 'proof of ownership' => 3, 'attestation de propriete' => 4, 'attestation' => 3, 'private sale' => 3, 'notary' => 2, 'notaire' => 2],
        'answer' => [
            'en' => "A Titre Foncier is the land title registered at the Conservation Foncière and is the strongest proof of ownership. A 'certificate of ownership', an attestation or a village paper is not automatically the same thing — its legal value must be checked. Ask for the title number and a recent certified search, and let a notary confirm it before you pay.",
            'fr' => "Le Titre Foncier est le titre inscrit à la Conservation Foncière : c'est la preuve de propriété la plus solide. Une « attestation de propriété », un certificat ou un papier de village n'est pas automatiquement équivalent — sa valeur juridique doit être vérifiée. Demandez le numéro du titre et un état récent, et faites confirmer par un notaire avant de payer.",
        ],
        'suggestions' => [
            'en' => ['How do I verify a title?', 'What is MINDCAF registration?', 'Talk to an agent'],
            'fr' => ['Comment vérifier un titre ?', 'Qu’est-ce que l’immatriculation MINDCAF ?', 'Parler à un agent'],
        ],
    ],
    'registration' => [
        'keywords' => ['mindcaf' => 5, 'bornage' => 4, 'land registration' => 4, 'register land' => 4, 'registration' => 3, 'land gazette' => 4, 'gazette' => 3, 'immatriculation' => 5, 'delimitation' => 3, 'cadastral' => 3, 'publication' => 2],
        'answer' => [
            'en' => "Registering land in Cameroon usually means filing the application and supporting documents with the competent MINDCAF service, a cadastral survey with boundary placement (bornage), public notice so neighbours can object, and then review before a title is issued. Ordinance No. 74-1 of 6 July 1974 and Decree No. 76-165 of 27 April 1976 are the key texts. Requirements and fees change, so confirm the current checklist with MINDCAF or a notary.",
            'fr' => "L'immatriculation au Cameroun passe généralement par le dépôt du dossier auprès du service compétent du MINDCAF, une descente cadastrale avec bornage, une publicité permettant les oppositions, puis l'examen avant délivrance du titre. Les textes de référence sont l'ordonnance n° 74-1 du 6 juillet 1974 et le décret n° 76-165 du 27 avril 1976. Les pièces et frais changent : confirmez la liste actuelle auprès du MINDCAF ou d'un notaire.",
        ],
        'suggestions' => [
            'en' => ['What documents do I need?', 'What does bornage mean?', 'Talk to an agent'],
            'fr' => ['Quels documents faut-il ?', 'Que signifie le bornage ?', 'Parler à un agent'],
        ],
    ],
    'documents' => [
        'keywords' => ['documents' => 3, 'document' => 2, 'paperwork' => 4, 'what do i need' => 4, 'pieces' => 3, 'dossier' => 3, 'requirements' => 3, 'justificatifs' => 3],
        'answer' => [
            'en' => "A buyer will usually ask for the Titre Foncier (or the document you hold), a recent certified registry search, the seller's national ID, the survey and boundary plan, proof of tax payments, and a written sale agreement. The exact list depends on the situation, so have a notary confirm it before signing or paying.",
            'fr' => "Un acheteur demandera en général le Titre Foncier (ou le document que vous détenez), un état récent du registre, la pièce d'identité du vendeur, le plan de bornage, la preuve de paiement des taxes et un acte de vente écrit. La liste exacte dépend du dossier : faites-la confirmer par un notaire avant de signer ou de payer.",
        ],
        'suggestions' => [
            'en' => ['How do I verify a title?', 'Explain the buying process', 'Talk to an agent'],
            'fr' => ['Comment vérifier un titre ?', 'Expliquez le processus d’achat', 'Parler à un agent'],
        ],
    ],
    'buy_process' => [
        'keywords' => ['how do i buy' => 5, 'how to buy' => 5, 'buying process' => 5, 'purchase process' => 5, 'steps to buy' => 5, 'processus d achat' => 5, 'comment acheter' => 5, 'acheter un terrain' => 4, 'safe way to buy' => 4, 'before i buy' => 4, 'i want to buy' => 4, 'i would like to buy' => 4, 'looking to buy' => 3],
        'answer' => [
            'en' => "A safer purchase goes like this: agree the terms in writing, verify the seller and the title at the registry, have the boundaries surveyed and checked, sign the deed before a notary, pay through traceable channels with receipts, then complete registration and the transfer. Never pay in full on a photocopy or a promise. Tell me the city and budget and I can show what is available.",
            'fr' => "Un achat plus sûr suit cet ordre : fixer les conditions par écrit, vérifier le vendeur et le titre au registre, faire borner et contrôler les limites, signer l'acte chez un notaire, payer par un canal traçable avec reçus, puis achever l'enregistrement et la mutation. Ne payez jamais tout sur une photocopie ou une promesse. Donnez-moi la ville et le budget et je vous montre ce qui est disponible.",
        ],
        'suggestions' => [
            'en' => ['Show me land for sale', 'How do I verify a title?', 'Talk to an agent'],
            'fr' => ['Montrez-moi des terrains à vendre', 'Comment vérifier un titre ?', 'Parler à un agent'],
        ],
    ],
    'sell_process' => [
        'keywords' => ['how do i sell' => 5, 'how to sell' => 5, 'selling process' => 5, 'sell my land' => 5, 'sell my property' => 5, 'vendre mon terrain' => 5, 'comment vendre' => 5, 'mettre en vente' => 4, 'i want to sell' => 5, 'i would like to sell' => 5, 'i am selling' => 4],
        'answer' => [
            'en' => "To sell, prepare the title or the document you hold, your ID, the survey plan and any receipts or tax papers, then agree the price and terms in writing. The transfer should be completed before a notary so the buyer can register it, and buyers will ask for a current registry search — order one early. I can take your details for an agent hand-off, or you can use the due-diligence form for a real review.",
            'fr' => "Pour vendre, préparez le titre ou le document que vous détenez, votre pièce d'identité, le plan de bornage et les reçus ou justificatifs fiscaux, puis fixez le prix et les conditions par écrit. La mutation doit se faire chez un notaire pour que l'acheteur puisse l'enregistrer, et les acheteurs demanderont un état récent du registre : commandez-le tôt. Je peux prendre vos coordonnées pour une mise en relation, ou utilisez le formulaire de vérification.",
        ],
        'suggestions' => [
            'en' => ['What documents do I need?', 'Talk to an agent', 'Show me available properties'],
            'fr' => ['Quels documents faut-il ?', 'Parler à un agent', 'Montrez-moi les biens disponibles'],
        ],
    ],
    'lease' => [
        'keywords' => ['lease' => 4, 'tenancy' => 4, 'tenant' => 4, 'rental' => 3, 'rent' => 3, 'commercial lease' => 5, 'caution' => 3, 'deposit' => 3, 'eviction' => 4, 'bail' => 4, 'loyer' => 3, 'locataire' => 4, 'expulsion' => 4, 'preavis' => 3, 'bail commercial' => 5],
        'answer' => [
            'en' => "Residential tenancy and a commercial lease are different: commercial leases can fall under the OHADA Uniform Act on General Commercial Law, while housing follows national rules. Put the rent, duration, permitted use, repairs, the caution/deposit and its refund conditions in writing. A deposit is not automatically forfeited, and eviction must follow written notice and the lawful procedure — never self-help. Have a local lawyer read the lease.",
            'fr' => "La location d'habitation et le bail commercial sont différents : le bail commercial peut relever de l'Acte uniforme OHADA relatif au droit commercial général, tandis que l'habitation suit les règles nationales. Précisez par écrit le loyer, la durée, l'usage, les réparations, la caution et ses conditions de restitution. La caution n'est pas automatiquement perdue, et l'expulsion doit suivre une notification écrite et la procédure légale — jamais par la force. Faites relire le bail par un juriste local.",
        ],
        'suggestions' => [
            'en' => ['What should a lease include?', 'Can a deposit be kept?', 'Talk to an agent'],
            'fr' => ['Que doit contenir un bail ?', 'La caution peut-elle être retenue ?', 'Parler à un agent'],
        ],
    ],
    'dispute' => [
        'keywords' => ['double sale' => 5, 'sold twice' => 5, 'two buyers' => 4, 'inheritance' => 4, 'inherit' => 3, 'heir' => 3, 'family land' => 4, 'chefferie' => 4, 'traditional ruler' => 3, 'property dispute' => 5, 'land dispute' => 5, 'boundary dispute' => 5, 'conflit' => 3, 'litige' => 4, 'succession' => 4, 'heritage' => 3, 'double vente' => 5, 'chef traditionnel' => 4],
        'answer' => [
            'en' => "For a suspected double sale, an inheritance conflict or a boundary dispute, preserve the title or registry search, the sale agreements, receipts, survey plans, messages and any court papers. Don't resell, move boundary markers or use force. A chefferie can help explain family or customary history, but it does not replace registry checks or the courts for a title dispute — a property lawyer should review the file quickly, and our dispute intake can start that.",
            'fr' => "En cas de double vente, de conflit successoral ou de litige de limites, conservez le titre ou l'état du registre, les actes de vente, les reçus, les plans, les messages et les documents judiciaires. Ne revendez pas, ne déplacez pas les bornes et n'employez pas la force. La chefferie peut éclairer l'histoire familiale ou coutumière, mais ne remplace ni le registre ni les juridictions pour un litige de titre : faites examiner le dossier rapidement par un avocat, et notre formulaire de litige peut lancer cette démarche.",
        ],
        'suggestions' => [
            'en' => ['Start a dispute request', 'Can a chief settle a land dispute?', 'Talk to an agent'],
            'fr' => ['Lancer une demande de litige', 'Un chef peut-il régler un litige foncier ?', 'Parler à un agent'],
        ],
    ],
    'survey' => [
        'keywords' => ['bornage' => 4, 'what is bornage' => 5, 'que signifie le bornage' => 5, 'boundary' => 3, 'boundaries' => 3, 'survey' => 3, 'survey plan' => 4, 'plot number' => 3, 'limites' => 3, 'bornes' => 4],
        'answer' => [
            'en' => "Bornage is the cadastral operation that fixes a plot's limits on the ground and on the plan. Ask for the approved survey plan, check that the plot number and dimensions match the title, and walk the boundaries with the seller and the neighbours. A plan that isn't tied to the title proves very little.",
            'fr' => "Le bornage est l'opération cadastrale qui fixe les limites d'une parcelle sur le terrain et sur le plan. Demandez le plan approuvé, vérifiez que le numéro de parcelle et les dimensions correspondent au titre, et parcourez les limites avec le vendeur et les voisins. Un plan non rattaché au titre ne prouve presque rien.",
        ],
        'suggestions' => [
            'en' => ['How do I verify a title?', 'What documents do I need?', 'Talk to an agent'],
            'fr' => ['Comment vérifier un titre ?', 'Quels documents faut-il ?', 'Parler à un agent'],
        ],
    ],
    'fees' => [
        'keywords' => ['fees' => 4, 'fee' => 3, 'costs' => 3, 'taxes' => 3, 'tax' => 3, 'stamp duty' => 4, 'notary fees' => 4, 'registration duty' => 4, 'frais' => 3, 'taxe' => 3, 'combien coute' => 3, 'prix du titre' => 3],
        'answer' => [
            'en' => "Expect registration duties, notary fees, survey and boundary fees, and certified-copy fees. They depend on the declared value and the current finance law, so there is no single rate I can quote safely. Ask a notary for a written estimate before you commit, and keep every receipt.",
            'fr' => "Prévoyez les droits d'enregistrement, les honoraires du notaire, les frais de bornage et de copie certifiée. Ils dépendent de la valeur déclarée et de la loi de finances en vigueur : je ne peux pas citer un taux unique de façon fiable. Demandez un devis écrit au notaire avant de vous engager et gardez tous les reçus.",
        ],
        'suggestions' => [
            'en' => ['What documents do I need?', 'How do I verify a title?', 'Talk to an agent'],
            'fr' => ['Quels documents faut-il ?', 'Comment vérifier un titre ?', 'Parler à un agent'],
        ],
    ],
    'financing' => [
        'keywords' => ['loan' => 4, 'bank' => 3, 'mortgage' => 3, 'credit' => 3, 'instalment' => 4, 'installment' => 4, 'payment plan' => 5, 'finance' => 3, 'pret' => 4, 'banque' => 3, 'credit immobilier' => 4, 'echelonne' => 3, 'payer en plusieurs fois' => 4],
        'answer' => [
            'en' => "Some Cameroonian banks offer property credit, but approval depends on your income, the title and the bank's valuation. A seller's instalment plan is possible, but put it in writing with receipts and a clear rule for late payments. Avoid cash without proof and have a lawyer review the arrangement.",
            'fr' => "Certaines banques camerounaises proposent un crédit immobilier, mais l'accord dépend de vos revenus, du titre et de l'évaluation de la banque. Un paiement échelonné avec le vendeur est possible, mais doit être écrit, avec reçus et une règle claire en cas de retard. Évitez les espèces sans preuve et faites relire l'accord par un avocat.",
        ],
        'suggestions' => [
            'en' => ['Show me land for sale', 'What documents do I need?', 'Talk to an agent'],
            'fr' => ['Montrez-moi des terrains à vendre', 'Quels documents faut-il ?', 'Parler à un agent'],
        ],
    ],
    'foreigners' => [
        'keywords' => ['foreigner' => 5, 'foreigners' => 5, 'non citizen' => 5, 'foreign ownership' => 5, 'expatriate' => 4, 'etranger' => 5, 'etrangers' => 5, 'non national' => 4],
        'answer' => [
            'en' => "Land ownership by non-citizens is restricted in Cameroon. Foreigners usually hold land through a long-term lease granted by the State rather than freehold title, and conditions apply. It is decided case by case, so confirm the current rule with MINDCAF and a Cameroonian notary before paying anything.",
            'fr' => "L'accès à la propriété foncière des non-nationaux est limité au Cameroun. Les étrangers détiennent généralement la terre par un bail de longue durée accordé par l'État plutôt qu'en pleine propriété, et des conditions s'appliquent. Chaque cas est examiné séparément : confirmez la règle actuelle auprès du MINDCAF et d'un notaire camerounais avant tout paiement.",
        ],
        'suggestions' => [
            'en' => ['How do I verify a title?', 'Talk to an agent', 'What documents do I need?'],
            'fr' => ['Comment vérifier un titre ?', 'Parler à un agent', 'Quels documents faut-il ?'],
        ],
    ],
    'services' => [
        'keywords' => ['services' => 3, 'consultation' => 4, 'legal expert' => 4, 'lawyer' => 3, 'avocat' => 3, 'specialist' => 3, 'due diligence request' => 3, 'formulaire' => 2, 'expert' => 2],
        'answer' => [
            'en' => "Lumda Chris offers a due-diligence review of a property's documents, ownership history and survey details, and a consultation on a land or tenancy dispute. You can send your details and a document on the due-diligence page, and a specialist will follow up. I can tell you what to prepare first.",
            'fr' => "Lumda Chris propose une vérification des documents, de l'historique de propriété et du plan d'un bien, ainsi qu'une consultation sur un litige foncier ou de location. Vous pouvez envoyer vos coordonnées et un document depuis la page de vérification, et un spécialiste vous répondra. Je peux vous dire quoi préparer d'abord.",
        ],
        'suggestions' => [
            'en' => ['Start a due-diligence request', 'Discuss a land dispute', 'Talk to an agent'],
            'fr' => ['Lancer une vérification', 'Discuter d’un litige foncier', 'Parler à un agent'],
        ],
    ],
    'agent_handoff' => [
        'keywords' => ['agent' => 4, 'talk to someone' => 5, 'speak to someone' => 5, 'contact you' => 4, 'call me' => 4, 'whatsapp' => 3, 'parler a un agent' => 5, 'mettre en relation' => 4, 'rendez vous' => 3, 'appointment' => 3, 'contacter' => 3],
        'answer' => [
            'en' => "I can take your name, phone number and what you're looking for so an agent can follow up. Would you like to do that now?",
            'fr' => "Je peux prendre votre nom, votre numéro de téléphone et votre recherche pour qu'un agent vous recontacte. Voulez-vous le faire maintenant ?",
        ],
        'suggestions' => [
            'en' => ['Cancel'],
            'fr' => ['Annuler'],
        ],
    ],
    'thanks' => [
        'keywords' => ['thanks' => 4, 'thank you' => 5, 'merci' => 5, 'thank' => 3],
        'answer' => [
            'en' => "You're welcome. If you tell me a city and a budget, I can show what is available, or I can connect you with an agent.",
            'fr' => "Avec plaisir. Donnez-moi une ville et un budget et je vous montre ce qui est disponible, ou je peux vous mettre en relation avec un agent.",
        ],
        'suggestions' => [
            'en' => ['Show me available land', 'Talk to an agent'],
            'fr' => ['Montrez-moi les terrains disponibles', 'Parler à un agent'],
        ],
    ],
    'goodbye' => [
        'keywords' => ['goodbye' => 5, 'bye' => 4, 'au revoir' => 5, 'a bientot' => 4, 'see you' => 3],
        'answer' => [
            'en' => 'Goodbye, and good luck with your property plans. You can reopen this chat any time.',
            'fr' => 'Au revoir, et bonne chance pour votre projet immobilier. Vous pouvez rouvrir cette discussion à tout moment.',
        ],
        'suggestions' => [
            'en' => ['Show me available land', 'Talk to an agent'],
            'fr' => ['Montrez-moi les terrains disponibles', 'Parler à un agent'],
        ],
    ],
];
