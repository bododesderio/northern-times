<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Category;

/**
 * Smart category matcher using weighted keyword analysis.
 *
 * Scores each article against all system categories using:
 * - Title keywords (3x weight — most indicative)
 * - Content keywords (1x weight — supporting signal)
 * - RSS category tags (2x weight — explicit signal from source)
 * - URL path segments (1.5x weight)
 *
 * Only assigns a category if confidence >= 90%.
 * Falls back to "Uncategorized" / default if no confident match.
 */
final class CategoryMatcher
{
    private const CONFIDENCE_THRESHOLD = 90;

    // Comprehensive keyword dictionaries per category slug.
    // Each keyword has a weight: higher = more indicative.
    // This covers news categories typical for Northern Uganda / East Africa.
    private const KEYWORD_MAP = [

        'sports' => [
            // High confidence (10)
            'football' => 10, 'soccer' => 10, 'basketball' => 10, 'cricket' => 10,
            'rugby' => 10, 'tennis' => 10, 'athletics' => 10, 'marathon' => 10,
            'volleyball' => 10, 'netball' => 10, 'boxing' => 10, 'wrestling' => 10,
            'swimming' => 10, 'goalkeep' => 10, 'midfielder' => 10, 'striker' => 10,
            'goalkeeper' => 10, 'handball' => 10, 'badminton' => 10,
            // Medium confidence (7)
            'league' => 7, 'tournament' => 7, 'championship' => 7, 'fixture' => 7,
            'semifinal' => 7, 'semi-final' => 7, 'quarterfinal' => 7, 'final' => 4,
            'premier league' => 8, 'fufa' => 9, 'fifa' => 9, 'caf' => 8,
            'kcca' => 7, 'vipers' => 7, 'sc villa' => 8, 'express fc' => 8,
            'cranes' => 7, 'she cranes' => 8, 'olympics' => 8, 'olympic' => 8,
            'world cup' => 8, 'afcon' => 9, 'usssa' => 9, 'copa' => 7,
            'epl' => 8, 'la liga' => 8, 'serie a' => 8, 'bundesliga' => 8,
            'champions league' => 8, 'europa league' => 8,
            // Lower confidence (5)
            'coach' => 5, 'player' => 5, 'team' => 4, 'match' => 5,
            'goal' => 5, 'score' => 5, 'defeat' => 4, 'victory' => 4,
            'draw' => 4, 'win' => 3, 'lost' => 3, 'trophy' => 6,
            'stadium' => 6, 'pitch' => 5, 'referee' => 7, 'transfer' => 6,
            'signing' => 5, 'injury' => 4, 'season' => 4, 'cap' => 3,
            'athlete' => 7, 'medal' => 6, 'sprint' => 7, 'relay' => 7,
        ],

        'politics' => [
            // High confidence (10)
            'parliament' => 10, 'election' => 10, 'presidential' => 10,
            'senator' => 10, 'congressman' => 10, 'legislat' => 9,
            'opposition' => 9, 'ruling party' => 10, 'nrm' => 9, 'fdc' => 9,
            'nup' => 9, 'dp' => 7, 'upc' => 8, 'anp' => 8,
            'electoral commission' => 10, 'ballot' => 9, 'poll' => 7,
            'constituency' => 10, 'by-election' => 10, 'primaries' => 9,
            'campaign' => 7, 'manifesto' => 9, 'political party' => 10,
            'speaker of parliament' => 10, 'prime minister' => 10,
            // Medium confidence (7)
            'government' => 7, 'minister' => 7, 'cabinet' => 8, 'policy' => 6,
            'lawmaker' => 9, 'bill' => 6, 'legislation' => 8, 'decree' => 8,
            'diplomat' => 8, 'ambassador' => 8, 'summit' => 6, 'treaty' => 7,
            'sanctions' => 7, 'regime' => 7, 'coup' => 9, 'impeach' => 10,
            'democracy' => 7, 'constitutional' => 9, 'referendum' => 10,
            'governor' => 8, 'mayor' => 7, 'council' => 5, 'lc5' => 9,
            'lc3' => 9, 'rdc' => 8, 'district chairman' => 9,
            'museveni' => 8, 'bobi wine' => 9, 'besigye' => 9,
            'political' => 7, 'ideology' => 6, 'activist' => 6,
            'mp ' => 8, 'mps ' => 8, 'member of parliament' => 10,
            'rdc' => 9, 'resident district' => 10, 'district official' => 9,
            'lc1' => 9, 'lc2' => 9, 'lc3' => 9, 'lc5' => 9,
            'local council' => 9, 'chairperson' => 7, 'sub-county' => 8,
            'subcounty' => 8, 'town clerk' => 8, 'cao' => 8,
            'chief administrative' => 10, 'nepotism' => 8,
            'corruption' => 8, 'accountability' => 7, 'inspector general' => 10,
            'anti-corruption' => 9, 'public service' => 7, 'state house' => 9,
            'entebbe state' => 9, 'national resistance' => 9,
            // Lower confidence (4)
            'vote' => 5, 'voter' => 6, 'debate' => 5, 'protest' => 5,
            'rally' => 6, 'demonstration' => 5, 'riot' => 5,
            'arrested' => 4, 'remand' => 5, 'charged' => 4,
            'misconduct' => 7, 'election misconduct' => 10,
            'transfer' => 3, 'reshuffle' => 8, 'sacked' => 7, 'fired' => 5,
            'controversial' => 4, 'scandal' => 6, 'probe' => 6, 'inquiry' => 6,
        ],

        'business' => [
            // High confidence (10)
            'stock market' => 10, 'nasdaq' => 10, 'dow jones' => 10,
            'ipo' => 10, 'gdp' => 10, 'inflation' => 9, 'interest rate' => 9,
            'central bank' => 10, 'bank of uganda' => 10, 'bou' => 8,
            'revenue' => 7, 'profit' => 7, 'earnings' => 8, 'fiscal' => 8,
            'budget' => 7, 'tax' => 6, 'taxation' => 8, 'ura' => 8,
            'trade' => 6, 'export' => 7, 'import' => 7, 'tariff' => 8,
            'investment' => 7, 'investor' => 7, 'startup' => 8,
            'entrepreneur' => 8, 'ceo' => 7, 'company' => 5, 'corporate' => 8,
            'merger' => 9, 'acquisition' => 9, 'ipo' => 10,
            // Medium confidence (6)
            'economy' => 7, 'economic' => 7, 'market' => 5, 'industry' => 5,
            'commerce' => 7, 'banking' => 8, 'loan' => 6, 'credit' => 5,
            'debt' => 6, 'bond' => 7, 'share' => 5, 'dividend' => 9,
            'commodity' => 7, 'oil price' => 8, 'agriculture' => 6,
            'manufacturing' => 7, 'real estate' => 8, 'property' => 5,
            'retail' => 6, 'supply chain' => 8, 'forex' => 9,
            'cryptocurrency' => 8, 'bitcoin' => 7, 'fintech' => 8,
            'mobile money' => 8, 'mtn' => 5, 'airtel' => 5,
            'employment' => 6, 'unemployment' => 7, 'job market' => 7,
            'sme' => 8, 'small business' => 7, 'microfinance' => 8,
        ],

        'health' => [
            // High confidence (10)
            'hospital' => 9, 'clinic' => 8, 'doctor' => 7, 'surgeon' => 9,
            'patient' => 7, 'diagnosis' => 9, 'treatment' => 7, 'therapy' => 8,
            'vaccine' => 10, 'vaccination' => 10, 'immunization' => 10,
            'epidemic' => 10, 'pandemic' => 10, 'outbreak' => 9,
            'disease' => 8, 'virus' => 8, 'infection' => 8, 'bacteria' => 9,
            'malaria' => 10, 'hiv' => 10, 'aids' => 9, 'tuberculosis' => 10,
            'covid' => 10, 'ebola' => 10, 'cholera' => 10, 'measles' => 10,
            'cancer' => 9, 'diabetes' => 9, 'hypertension' => 9,
            'surgery' => 9, 'transplant' => 9, 'medical' => 7,
            'who' => 6, 'world health' => 10, 'ministry of health' => 10,
            'pharmaceutical' => 9, 'drug' => 5, 'medicine' => 7,
            // Medium confidence (6)
            'healthcare' => 8, 'health care' => 8, 'mental health' => 9,
            'nutrition' => 8, 'maternal' => 8, 'child health' => 9,
            'pregnancy' => 8, 'maternity' => 8, 'neonatal' => 9,
            'sanitation' => 7, 'hygiene' => 7, 'water safety' => 7,
            'ambulance' => 8, 'emergency room' => 8, 'icu' => 8,
            'nurse' => 7, 'midwife' => 8, 'pharmacist' => 8,
            'disability' => 6, 'rehabilitation' => 7, 'wellness' => 6,
            'fitness' => 5, 'diet' => 5, 'obesity' => 7,
        ],

        'technology' => [
            // High confidence (10)
            'artificial intelligence' => 10, 'ai' => 7, 'machine learning' => 10,
            'blockchain' => 9, 'cybersecurity' => 10, 'software' => 8,
            'hardware' => 7, 'programming' => 9, 'coding' => 8,
            'app' => 5, 'application' => 4, 'smartphone' => 7,
            'internet' => 6, 'broadband' => 8, 'wifi' => 7, '5g' => 8,
            'cloud computing' => 10, 'data center' => 9, 'server' => 6,
            'google' => 6, 'apple' => 5, 'microsoft' => 6, 'meta' => 5,
            'amazon' => 5, 'tesla' => 6, 'spacex' => 8, 'openai' => 9,
            'robotics' => 9, 'delivery drone' => 7, 'drone technology' => 8, 'automation' => 8,
            'silicon valley' => 9, 'tech startup' => 10, 'innovation' => 6,
            // Medium confidence (6)
            'digital' => 5, 'cyber' => 7, 'hacker' => 8, 'hack' => 6,
            'encryption' => 9, 'algorithm' => 8, 'database' => 8,
            'ecommerce' => 7, 'e-commerce' => 7, 'online platform' => 6,
            'social media' => 5, 'twitter' => 4, 'facebook' => 4,
            'virtual reality' => 9, 'augmented reality' => 9,
            'semiconductor' => 9, 'chip' => 5, 'processor' => 8,
            'satellite' => 7, 'space' => 5, 'launch' => 4,
            'nita' => 7, 'ict' => 8, 'telecom' => 7,
        ],

        'opinion' => [
            // High confidence (10)
            'editorial' => 10, 'op-ed' => 10, 'opinion' => 10,
            'commentary' => 10, 'perspective' => 8, 'viewpoint' => 9,
            'column' => 7, 'columnist' => 9, 'letter to the editor' => 10,
            'analysis' => 7, 'critique' => 7, 'review' => 5,
            'my view' => 9, 'i think' => 5, 'i believe' => 5,
            'guest writer' => 9, 'contributing writer' => 9,
        ],

        'world' => [
            // High confidence (10)
            'united nations' => 10, 'un' => 5, 'nato' => 9,
            'european union' => 10, 'eu' => 6, 'african union' => 10,
            'foreign affairs' => 10, 'international' => 7, 'global' => 6,
            'geopolitics' => 10, 'war' => 7, 'conflict' => 6,
            'ceasefire' => 9, 'peace talks' => 9, 'peace deal' => 9,
            'refugee' => 7, 'asylum' => 8, 'migration' => 7,
            'terrorism' => 8, 'militant' => 7, 'insurgent' => 8,
            'humanitarian' => 7, 'aid' => 4, 'relief' => 5,
            'drone strike' => 9, 'airstrike' => 9, 'air strike' => 9,
            'missile' => 9, 'missile strike' => 10, 'bombard' => 8,
            'military' => 7, 'troops' => 7, 'soldiers' => 7,
            'naval' => 8, 'naval base' => 9, 'warship' => 9,
            'service members' => 8, 'pentagon' => 9, 'armed forces' => 8,
            'invasion' => 9, 'occupation' => 7, 'annexation' => 9,
            // Country/region names (medium confidence)
            'middle east' => 8, 'gaza' => 9, 'israel' => 8, 'palestine' => 8,
            'ukraine' => 8, 'russia' => 7, 'china' => 6, 'iran' => 7,
            'syria' => 8, 'yemen' => 8, 'afghanistan' => 8, 'libya' => 8,
            'somalia' => 7, 'congo' => 7, 'drc' => 7, 'south sudan' => 8,
            'sudan' => 7, 'ethiopia' => 7, 'eritrea' => 7,
            'united states' => 7, 'us' => 3, 'america' => 5,
            'white house' => 9, 'pentagon' => 9, 'kremlin' => 9,
            'trump' => 6, 'biden' => 6, 'putin' => 7, 'zelensky' => 8,
            'nato' => 9, 'g7' => 8, 'g20' => 8, 'brics' => 8,
            'earthquake' => 7, 'tsunami' => 9, 'hurricane' => 8,
        ],

        'east-africa' => [
            // Country names (high confidence)
            'kenya' => 9, 'tanzania' => 9, 'rwanda' => 9, 'burundi' => 9,
            'south sudan' => 9, 'ethiopia' => 8, 'somalia' => 8, 'eritrea' => 8,
            'djibouti' => 9, 'east africa' => 10, 'east african' => 10,
            // Cities
            'nairobi' => 9, 'dar es salaam' => 9, 'kigali' => 9, 'addis ababa' => 9,
            'mogadishu' => 9, 'juba' => 8, 'mombasa' => 8, 'arusha' => 8,
            'dodoma' => 8, 'bujumbura' => 9, 'kisumu' => 8, 'eldoret' => 8,
            // Regional orgs
            'eac' => 7, 'igad' => 8, 'east african community' => 10,
            'comesa' => 7, 'eala' => 8, 'east african court' => 10,
            // Regional issues
            'lake victoria' => 9, 'great rift' => 8, 'mt kenya' => 9,
            'kilimanjaro' => 8, 'serengeti' => 8, 'masai mara' => 8,
            'maasai' => 8, 'kikuyu' => 7, 'swahili' => 6,
        ],

        'africa' => [
            // Continental
            'african union' => 10, 'au summit' => 10, 'pan-african' => 10,
            'afdb' => 9, 'african development' => 10, 'nepad' => 9,
            'sadc' => 8, 'ecowas' => 8, 'sahel' => 8, 'maghreb' => 8,
            // West Africa
            'nigeria' => 7, 'ghana' => 7, 'senegal' => 7, 'ivory coast' => 7,
            'lagos' => 7, 'accra' => 7, 'abuja' => 7, 'dakar' => 7,
            // Southern Africa
            'south africa' => 7, 'zimbabwe' => 7, 'mozambique' => 7,
            'johannesburg' => 7, 'cape town' => 7, 'pretoria' => 7,
            // North Africa
            'egypt' => 7, 'morocco' => 7, 'tunisia' => 7, 'algeria' => 7,
            'cairo' => 7, 'casablanca' => 7,
            // Central Africa
            'congo' => 7, 'drc' => 7, 'cameroon' => 7, 'gabon' => 7,
            'kinshasa' => 7,
            // Continental issues
            'afcfta' => 9, 'african continental' => 10,
            'africa day' => 9, 'decoloniz' => 7,
        ],

        'northern-uganda' => [
            // High confidence (10) — regional identifiers
            'gulu' => 10, 'lira' => 10, 'soroti' => 10, 'arua' => 10,
            'kitgum' => 10, 'pader' => 10, 'amuru' => 10, 'nwoya' => 10,
            'adjumani' => 10, 'moyo' => 10, 'yumbe' => 10, 'koboko' => 10,
            'nebbi' => 10, 'zombo' => 10, 'maracha' => 10, 'pakwach' => 10,
            'apac' => 10, 'alebtong' => 10, 'dokolo' => 10, 'amolatar' => 10,
            'otuke' => 10, 'kole' => 10, 'oyam' => 10, 'kwania' => 10,
            'kaberamaido' => 10, 'katakwi' => 10, 'napak' => 10,
            'moroto' => 10, 'kotido' => 10, 'abim' => 10, 'kaabong' => 10,
            'amudat' => 10, 'nakapiripirit' => 10, 'nabilatuk' => 10,
            'agago' => 10, 'lamwo' => 10, 'omoro' => 10,
            // Ethnic/cultural markers (8)
            'acholi' => 9, 'langi' => 9, 'lango' => 9, 'iteso' => 9, 'madi' => 8,
            'karamojong' => 9, 'lugbara' => 9, 'alur' => 9,
            // Institutional (8)
            'gulu university' => 10, 'lira university' => 10,
            'lacor hospital' => 10, 'st mary' => 5,
            'northern uganda' => 10, 'the north' => 6,
            'lra' => 8, 'lord\'s resistance' => 10, 'kony' => 9,
            'cattle rustling' => 9, 'karamoja' => 10,
            // Infrastructure & local terms
            'karuma' => 8, 'isimba' => 7, 'pakwach bridge' => 10,
            'boda boda' => 7, 'sub-county' => 6, 'parish' => 5,
            'clan' => 5, 'chieftainship' => 8, 'cultural leader' => 8,
            'rwot' => 9, 'ker kwaro' => 10, 'lango cultural' => 10,
            'acholi cultural' => 10, 'iteso cultural' => 10,
            'won nyaci' => 10, 'development priorities' => 5,
            'referral hospital' => 7, 'health centre' => 6,
            'domestic violence' => 5, 'land dispute' => 6, 'land grab' => 7,
            'displacement' => 6, 'returnee' => 7, 'camp' => 4,
            // Town-level identifiers
            'lacor' => 9, 'bobi' => 7, 'minakulu' => 10,
            'barlonyo' => 10, 'lukodi' => 10, 'atiak' => 10,
            'palabek' => 10, 'palaro' => 10, 'patongo' => 10,
            'kalongo' => 10, 'pajule' => 10, 'opit' => 9,
            'anaka' => 9, 'koch goma' => 10, 'pece' => 9,
            'bar dege' => 10, 'layibi' => 9, 'bungatira' => 10,
            'cwero' => 10, 'lalogi' => 10, 'purongo' => 10,
            'odek' => 10, 'atiak' => 10, 'bibia' => 10,
            'elegu' => 10, 'nimule' => 9, 'pangisa' => 10,
        ],
    ];

    /**
     * Match an article to a category with confidence scoring.
     *
     * @param array $item       Feed item with title, description, content, categories, link
     * @param array $categories System categories from DB [{id, name, slug}, ...]
     * @param string $defaultId Fallback category ID
     *
     * @return array{category_id: string, confidence: int, matched_slug: string}
     */
    public static function match(array $item, array $categories, string $defaultId): array
    {
        $title       = strtolower($item['title'] ?? '');
        $description = strtolower($item['description'] ?? '');
        $content     = strtolower(strip_tags($item['content'] ?? ''));
        $rssCategories = array_map('strtolower', $item['categories'] ?? []);
        $url         = strtolower($item['link'] ?? '');

        // Build category lookup: slug → id
        $catLookup = [];
        $catNames  = [];
        foreach ($categories as $cat) {
            $slug = strtolower($cat['slug']);
            $catLookup[$slug] = $cat['id'];
            $catNames[$slug]  = strtolower($cat['name']);
        }

        $scores = [];

        foreach (self::KEYWORD_MAP as $catSlug => $keywords) {
            // Only score categories that exist in our system
            if (!isset($catLookup[$catSlug])) continue;

            $score = 0;
            $maxPossible = 0;
            $matchedKeywords = [];

            foreach ($keywords as $keyword => $weight) {
                $kwLower = strtolower($keyword);
                $maxPossible += $weight * 3; // Title is max weight source

                // Title match (3x weight — titles are most indicative)
                if (str_contains($title, $kwLower)) {
                    $score += $weight * 3;
                    $matchedKeywords[] = $keyword . '(title)';
                }

                // RSS category tag match (2x weight — explicit from source)
                foreach ($rssCategories as $rssCat) {
                    if (str_contains($rssCat, $kwLower) || str_contains($kwLower, $rssCat)) {
                        $score += $weight * 2;
                        $matchedKeywords[] = $keyword . '(rss)';
                        break;
                    }
                }

                // URL path match (1.5x weight)
                if (str_contains($url, $kwLower)) {
                    $score += (int)($weight * 1.5);
                    $matchedKeywords[] = $keyword . '(url)';
                }

                // Content match (1x weight)
                if ($content && str_contains($content, $kwLower)) {
                    $score += $weight;
                    $matchedKeywords[] = $keyword . '(content)';
                } elseif ($description && str_contains($description, $kwLower)) {
                    $score += $weight;
                    $matchedKeywords[] = $keyword . '(desc)';
                }
            }

            // Also check direct RSS category → our category name/slug match
            foreach ($rssCategories as $rssCat) {
                $rssCat = trim($rssCat);
                $catName = $catNames[$catSlug] ?? '';

                // Direct match: RSS category IS our category name
                if ($rssCat === $catSlug || $rssCat === $catName) {
                    $score += 50; // Strong bonus for direct match
                    $matchedKeywords[] = 'DIRECT:' . $rssCat;
                }
            }

            // Normalize score to 0-100 confidence
            $matchCount = count(array_unique($matchedKeywords));
            if ($matchCount === 0) {
                $confidence = 0;
            } else {
                // Check for strong signals (high-weight keywords in title)
                $hasStrongTitleMatch = false;
                foreach ($keywords as $keyword => $weight) {
                    if ($weight >= 9 && str_contains($title, strtolower($keyword))) {
                        $hasStrongTitleMatch = true;
                        break;
                    }
                }

                $confidence = min(100, (int)(
                    // Base from raw score (diminishing returns)
                    min(60, $score * 2) +
                    // Bonus for multiple keyword matches
                    min(25, $matchCount * 5) +
                    // Bonus for title matches (very strong signal)
                    (str_contains(implode(',', $matchedKeywords), '(title)') ? 12 : 0) +
                    // Bonus for high-weight keyword in title (e.g. district name, sport name)
                    ($hasStrongTitleMatch ? 13 : 0)
                ));
            }

            $scores[$catSlug] = [
                'category_id'     => $catLookup[$catSlug],
                'confidence'      => $confidence,
                'matched_slug'    => $catSlug,
                'score'           => $score,
                'matched_keywords' => array_unique($matchedKeywords),
            ];
        }

        // Sort by confidence descending
        uasort($scores, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        // Get best match
        $best = reset($scores);

        // Check threshold
        if ($best && $best['confidence'] >= self::CONFIDENCE_THRESHOLD) {
            return [
                'category_id'  => $best['category_id'],
                'confidence'   => $best['confidence'],
                'matched_slug' => $best['matched_slug'],
            ];
        }

        // Below threshold — try direct RSS category tag to system category name/slug match
        foreach ($rssCategories as $rssCat) {
            $rssCat = trim($rssCat);
            if ($rssCat === '') continue;
            foreach ($categories as $cat) {
                $catName = strtolower($cat['name']);
                $catSlug = strtolower($cat['slug']);
                // Direct match
                if ($rssCat === $catName || $rssCat === $catSlug) {
                    return [
                        'category_id'  => $cat['id'],
                        'confidence'   => 85,
                        'matched_slug' => $catSlug . ' (rss-direct)',
                    ];
                }
                // Partial match (RSS tag contains category name or vice versa)
                if (strlen($rssCat) > 3 && (str_contains($rssCat, $catName) || str_contains($catName, $rssCat))) {
                    return [
                        'category_id'  => $cat['id'],
                        'confidence'   => 80,
                        'matched_slug' => $catSlug . ' (rss-partial)',
                    ];
                }
            }
        }

        // Final fallback — source default category (never auto-create categories)
        return [
            'category_id'  => $defaultId,
            'confidence'   => $best['confidence'] ?? 0,
            'matched_slug' => 'default',
        ];
    }
}