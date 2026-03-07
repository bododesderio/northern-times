<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Resolve GPS coordinates to the nearest known city.
 *
 * Uses a local database of major cities (focus on Uganda/East Africa)
 * as primary resolver. Falls back to Nominatim for areas not in the list.
 *
 * This solves the problem where Nominatim doesn't know many African cities
 * (e.g., Lira shows as "Oyam" in Nominatim).
 */
final class CityResolver
{
    /** Max distance (km) to snap to a known city */
    private const MAX_DISTANCE_KM = 30;

    /**
     * Resolve coordinates to a city name, country, and region.
     */
    public static function resolve(float $lat, float $lon): ?array
    {
        // Try local city database first (accurate for known cities)
        $local = self::findNearestCity($lat, $lon);
        if ($local !== null) {
            return $local;
        }

        // Fall back to Nominatim
        return GeoIP::reverseGeocode($lat, $lon);
    }

    /**
     * Find nearest city from local database within MAX_DISTANCE_KM.
     */
    private static function findNearestCity(float $lat, float $lon): ?array
    {
        $cities = self::cities();
        $best = null;
        $bestDist = self::MAX_DISTANCE_KM;

        foreach ($cities as $city) {
            $dist = self::haversine($lat, $lon, $city[2], $city[3]);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $city;
            }
        }

        if ($best === null) return null;

        return [
            'city'         => $best[0],
            'region'       => $best[1],
            'lat'          => $lat,    // Keep exact GPS coords
            'lon'          => $lon,
            'country'      => $best[4],
            'country_name' => $best[5],
            'country_code' => $best[4],
            'source'       => 'gps',
        ];
    }

    /**
     * Haversine distance in km.
     */
    private static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Local city database: [name, region, lat, lon, country_code, country_name]
     *
     * Uganda cities + major East African cities.
     */
    private static function cities(): array
    {
        return [
            // ── Uganda ───────────────────────────────────────────────
            // Central
            ['Kampala',       'Central Region',   0.3163,  32.5822, 'UG', 'Uganda'],
            ['Entebbe',       'Central Region',   0.0512,  32.4637, 'UG', 'Uganda'],
            ['Mukono',        'Central Region',   0.3533,  32.7554, 'UG', 'Uganda'],
            ['Wakiso',        'Central Region',   0.4044,  32.4594, 'UG', 'Uganda'],
            ['Mpigi',         'Central Region',   0.2253,  32.3138, 'UG', 'Uganda'],
            ['Mityana',       'Central Region',   0.4175,  32.0228, 'UG', 'Uganda'],
            ['Luweero',       'Central Region',   0.8494,  32.4733, 'UG', 'Uganda'],
            ['Kayunga',       'Central Region',   0.7025,  32.8886, 'UG', 'Uganda'],
            ['Masaka',        'Central Region',  -0.3136,  31.7353, 'UG', 'Uganda'],
            ['Bombo',         'Central Region',   0.5833,  32.5333, 'UG', 'Uganda'],

            // Northern
            ['Lira',          'Northern Region',   2.2499,  32.5338, 'UG', 'Uganda'],
            ['Gulu',          'Northern Region',   2.7746,  32.2990, 'UG', 'Uganda'],
            ['Kitgum',        'Northern Region',   3.2784,  32.8872, 'UG', 'Uganda'],
            ['Pader',         'Northern Region',   2.7833,  33.2500, 'UG', 'Uganda'],
            ['Apac',          'Northern Region',   1.9853,  32.5350, 'UG', 'Uganda'],
            ['Oyam',          'Northern Region',   2.2333,  32.3833, 'UG', 'Uganda'],
            ['Amuru',         'Northern Region',   2.8167,  31.9500, 'UG', 'Uganda'],
            ['Nwoya',         'Northern Region',   2.6333,  32.0000, 'UG', 'Uganda'],
            ['Adjumani',      'Northern Region',   3.3781,  31.7908, 'UG', 'Uganda'],
            ['Moyo',          'Northern Region',   3.6500,  31.7167, 'UG', 'Uganda'],
            ['Arua',          'Northern Region',   3.0204,  30.9110, 'UG', 'Uganda'],
            ['Nebbi',         'Northern Region',   2.4778,  31.1000, 'UG', 'Uganda'],
            ['Zombo',         'Northern Region',   2.5167,  30.9167, 'UG', 'Uganda'],
            ['Yumbe',         'Northern Region',   3.4667,  31.2500, 'UG', 'Uganda'],
            ['Koboko',        'Northern Region',   3.4100,  30.9600, 'UG', 'Uganda'],
            ['Dokolo',        'Northern Region',   1.9100,  33.1700, 'UG', 'Uganda'],
            ['Alebtong',      'Northern Region',   2.2500,  33.2167, 'UG', 'Uganda'],
            ['Otuke',         'Northern Region',   2.4500,  33.4833, 'UG', 'Uganda'],
            ['Agago',         'Northern Region',   2.8333,  33.3500, 'UG', 'Uganda'],
            ['Lamwo',         'Northern Region',   3.5333,  32.8000, 'UG', 'Uganda'],
            ['Kaabong',       'Northern Region',   3.5167,  34.1333, 'UG', 'Uganda'],
            ['Kotido',        'Northern Region',   2.9808,  34.1331, 'UG', 'Uganda'],
            ['Moroto',        'Northern Region',   2.5344,  34.6656, 'UG', 'Uganda'],
            ['Napak',         'Northern Region',   2.3833,  34.2333, 'UG', 'Uganda'],
            ['Amudat',        'Northern Region',   1.9500,  34.9500, 'UG', 'Uganda'],
            ['Nakapiripirit', 'Northern Region',   1.9167,  34.7167, 'UG', 'Uganda'],
            ['Abim',          'Northern Region',   2.7167,  33.6500, 'UG', 'Uganda'],
            ['Kole',          'Northern Region',   2.4000,  32.7667, 'UG', 'Uganda'],
            ['Omoro',         'Northern Region',   2.7167,  32.4833, 'UG', 'Uganda'],
            ['Kwania',        'Northern Region',   1.9333,  32.9333, 'UG', 'Uganda'],
            ['Madi-Okollo',   'Northern Region',   2.7833,  31.1000, 'UG', 'Uganda'],
            ['Obongi',        'Northern Region',   3.5167,  31.6667, 'UG', 'Uganda'],
            ['Terego',        'Northern Region',   3.2167,  31.1000, 'UG', 'Uganda'],
            ['Pakwach',       'Northern Region',   2.4614,  31.4944, 'UG', 'Uganda'],

            // Eastern
            ['Jinja',         'Eastern Region',    0.4244,  33.2041, 'UG', 'Uganda'],
            ['Mbale',         'Eastern Region',    1.0750,  34.1753, 'UG', 'Uganda'],
            ['Soroti',        'Eastern Region',    1.7147,  33.6111, 'UG', 'Uganda'],
            ['Tororo',        'Eastern Region',    0.6928,  34.1814, 'UG', 'Uganda'],
            ['Iganga',        'Eastern Region',    0.6092,  33.4686, 'UG', 'Uganda'],
            ['Busia',         'Eastern Region',    0.4544,  34.0922, 'UG', 'Uganda'],
            ['Kamuli',        'Eastern Region',    0.9472,  33.1197, 'UG', 'Uganda'],
            ['Pallisa',       'Eastern Region',    1.1450,  33.7094, 'UG', 'Uganda'],
            ['Bugiri',        'Eastern Region',    0.5714,  33.7419, 'UG', 'Uganda'],
            ['Kumi',          'Eastern Region',    1.4608,  33.9361, 'UG', 'Uganda'],
            ['Kapchorwa',     'Eastern Region',    1.3961,  34.4508, 'UG', 'Uganda'],
            ['Sironko',       'Eastern Region',    1.2294,  34.2486, 'UG', 'Uganda'],
            ['Bududa',        'Eastern Region',    1.0028,  34.3342, 'UG', 'Uganda'],
            ['Manafwa',       'Eastern Region',    0.9333,  34.3500, 'UG', 'Uganda'],
            ['Butaleja',      'Eastern Region',    0.9250,  33.9500, 'UG', 'Uganda'],
            ['Namutumba',     'Eastern Region',    0.8333,  33.6833, 'UG', 'Uganda'],
            ['Kaliro',        'Eastern Region',    0.8983,  33.5036, 'UG', 'Uganda'],
            ['Buyende',       'Eastern Region',    1.1167,  33.1500, 'UG', 'Uganda'],
            ['Luuka',         'Eastern Region',    0.7833,  33.3000, 'UG', 'Uganda'],
            ['Serere',        'Eastern Region',    1.5000,  33.5500, 'UG', 'Uganda'],
            ['Ngora',         'Eastern Region',    1.4500,  33.7833, 'UG', 'Uganda'],
            ['Katakwi',       'Eastern Region',    1.8972,  33.9653, 'UG', 'Uganda'],
            ['Amuria',        'Eastern Region',    2.0333,  33.6333, 'UG', 'Uganda'],
            ['Kaberamaido',   'Eastern Region',    1.7333,  33.1500, 'UG', 'Uganda'],
            ['Kapelebyong',   'Eastern Region',    2.1167,  33.8000, 'UG', 'Uganda'],
            ['Bulambuli',     'Eastern Region',    1.2333,  34.3833, 'UG', 'Uganda'],
            ['Budaka',        'Eastern Region',    1.0000,  33.9333, 'UG', 'Uganda'],
            ['Kibuku',        'Eastern Region',    1.0500,  33.7833, 'UG', 'Uganda'],

            // Western
            ['Fort Portal',   'Western Region',    0.6710,  30.2750, 'UG', 'Uganda'],
            ['Mbarara',       'Western Region',   -0.6072,  30.6545, 'UG', 'Uganda'],
            ['Kabale',        'Western Region',   -1.2489,  29.9900, 'UG', 'Uganda'],
            ['Kasese',        'Western Region',    0.1833,  30.0833, 'UG', 'Uganda'],
            ['Hoima',         'Western Region',    1.4314,  31.3522, 'UG', 'Uganda'],
            ['Masindi',       'Western Region',    1.6836,  31.7150, 'UG', 'Uganda'],
            ['Bushenyi',      'Western Region',   -0.5417,  30.1861, 'UG', 'Uganda'],
            ['Ntungamo',      'Western Region',   -0.8806,  30.2642, 'UG', 'Uganda'],
            ['Rukungiri',     'Western Region',   -0.8417,  29.9417, 'UG', 'Uganda'],
            ['Kisoro',        'Western Region',   -1.2833,  29.6833, 'UG', 'Uganda'],
            ['Ibanda',        'Western Region',   -0.1333,  30.4833, 'UG', 'Uganda'],
            ['Kamwenge',      'Western Region',    0.1867,  30.4553, 'UG', 'Uganda'],
            ['Kyenjojo',      'Western Region',    0.6300,  30.6200, 'UG', 'Uganda'],
            ['Bundibugyo',    'Western Region',    0.7114,  30.0667, 'UG', 'Uganda'],
            ['Kabarole',      'Western Region',    0.5833,  30.2500, 'UG', 'Uganda'],
            ['Kibaale',       'Western Region',    0.8000,  31.0667, 'UG', 'Uganda'],
            ['Isingiro',      'Western Region',   -0.7833,  30.8167, 'UG', 'Uganda'],
            ['Kiruhura',      'Western Region',   -0.2500,  30.8333, 'UG', 'Uganda'],
            ['Sheema',        'Western Region',   -0.5500,  30.4000, 'UG', 'Uganda'],
            ['Mitooma',       'Western Region',   -0.6167,  30.0667, 'UG', 'Uganda'],
            ['Rubirizi',      'Western Region',   -0.2667,  30.1167, 'UG', 'Uganda'],
            ['Buhweju',       'Western Region',   -0.4000,  30.3000, 'UG', 'Uganda'],
            ['Kitagwenda',    'Western Region',   -0.0500,  30.3167, 'UG', 'Uganda'],
            ['Kakumiro',      'Western Region',    0.7833,  31.3833, 'UG', 'Uganda'],
            ['Kagadi',        'Western Region',    0.9333,  30.8000, 'UG', 'Uganda'],
            ['Rubanda',       'Western Region',   -1.1833,  29.8500, 'UG', 'Uganda'],
            ['Bunyangabu',    'Western Region',    0.4833,  30.2000, 'UG', 'Uganda'],

            // ── Kenya ────────────────────────────────────────────────
            ['Nairobi',       'Nairobi County',   -1.2864,  36.8172, 'KE', 'Kenya'],
            ['Mombasa',       'Coast',            -4.0435,  39.6682, 'KE', 'Kenya'],
            ['Kisumu',        'Nyanza',           -0.0917,  34.7680, 'KE', 'Kenya'],
            ['Nakuru',        'Rift Valley',      -0.3031,  36.0800, 'KE', 'Kenya'],
            ['Eldoret',       'Rift Valley',       0.5143,  35.2698, 'KE', 'Kenya'],
            ['Thika',         'Central',          -1.0396,  37.0900, 'KE', 'Kenya'],
            ['Malindi',       'Coast',            -3.2138,  40.1169, 'KE', 'Kenya'],
            ['Kitale',        'Rift Valley',       1.0187,  35.0020, 'KE', 'Kenya'],
            ['Garissa',       'North Eastern',    -0.4532,  39.6461, 'KE', 'Kenya'],
            ['Nyeri',         'Central',          -0.4167,  36.9500, 'KE', 'Kenya'],

            // ── Tanzania ─────────────────────────────────────────────
            ['Dar es Salaam',  'Dar es Salaam',   -6.7924,  39.2083, 'TZ', 'Tanzania'],
            ['Dodoma',         'Dodoma',           -6.1630,  35.7516, 'TZ', 'Tanzania'],
            ['Mwanza',         'Mwanza',           -2.5167,  32.9000, 'TZ', 'Tanzania'],
            ['Arusha',         'Arusha',           -3.3869,  36.6830, 'TZ', 'Tanzania'],
            ['Mbeya',          'Mbeya',            -8.9000,  33.4500, 'TZ', 'Tanzania'],
            ['Zanzibar City',  'Zanzibar',         -6.1659,  39.2026, 'TZ', 'Tanzania'],
            ['Tanga',          'Tanga',            -5.0689,  39.0989, 'TZ', 'Tanzania'],
            ['Morogoro',       'Morogoro',         -6.8235,  37.6614, 'TZ', 'Tanzania'],

            // ── Rwanda ───────────────────────────────────────────────
            ['Kigali',        'Kigali',           -1.9403,  29.8739, 'RW', 'Rwanda'],
            ['Butare',        'Southern',         -2.5967,  29.7386, 'RW', 'Rwanda'],
            ['Gisenyi',       'Western',          -1.7028,  29.2564, 'RW', 'Rwanda'],
            ['Ruhengeri',     'Northern',         -1.4997,  29.6347, 'RW', 'Rwanda'],

            // ── South Sudan ──────────────────────────────────────────
            ['Juba',          'Central Equatoria', 4.8594,  31.5713, 'SS', 'South Sudan'],
            ['Malakal',       'Upper Nile',        9.5334,  31.6605, 'SS', 'South Sudan'],
            ['Wau',           'Western Bahr el Ghazal', 7.7000, 28.0000, 'SS', 'South Sudan'],

            // ── Burundi ──────────────────────────────────────────────
            ['Bujumbura',     'Bujumbura Mairie', -3.3822,  29.3644, 'BI', 'Burundi'],
            ['Gitega',        'Gitega',           -3.4264,  29.9246, 'BI', 'Burundi'],

            // ── DR Congo (Eastern) ───────────────────────────────────
            ['Goma',          'North Kivu',       -1.6792,  29.2228, 'CD', 'DR Congo'],
            ['Bukavu',        'South Kivu',       -2.5083,  28.8608, 'CD', 'DR Congo'],
            ['Kisangani',     'Tshopo',            0.5153,  25.1910, 'CD', 'DR Congo'],

            // ── Ethiopia ─────────────────────────────────────────────
            ['Addis Ababa',   'Addis Ababa',       9.0192,  38.7525, 'ET', 'Ethiopia'],
            ['Dire Dawa',     'Dire Dawa',         9.5931,  41.8661, 'ET', 'Ethiopia'],
            ['Mekelle',       'Tigray',           13.4967,  39.4753, 'ET', 'Ethiopia'],
            ['Hawassa',       'SNNPR',             7.0621,  38.4763, 'ET', 'Ethiopia'],

            // ── Major world cities ───────────────────────────────────
            ['London',        'England',          51.5074,  -0.1278, 'GB', 'United Kingdom'],
            ['New York',      'New York',         40.7128, -74.0060, 'US', 'United States'],
            ['Dubai',         'Dubai',            25.2048,  55.2708, 'AE', 'UAE'],
            ['Lagos',         'Lagos',             6.5244,   3.3792, 'NG', 'Nigeria'],
            ['Johannesburg',  'Gauteng',         -26.2041,  28.0473, 'ZA', 'South Africa'],
            ['Cairo',         'Cairo',            30.0444,  31.2357, 'EG', 'Egypt'],
            ['Mumbai',        'Maharashtra',      19.0760,  72.8777, 'IN', 'India'],
            ['Beijing',       'Beijing',          39.9042, 116.4074, 'CN', 'China'],
            ['Tokyo',         'Tokyo',            35.6762, 139.6503, 'JP', 'Japan'],
            ['Sydney',        'NSW',             -33.8688, 151.2093, 'AU', 'Australia'],
            ['Toronto',       'Ontario',          43.6532, -79.3832, 'CA', 'Canada'],
            ['Paris',         'Ile-de-France',    48.8566,   2.3522, 'FR', 'France'],
            ['Berlin',        'Berlin',           52.5200,  13.4050, 'DE', 'Germany'],
            ['Accra',         'Greater Accra',     5.6037,  -0.1870, 'GH', 'Ghana'],
            ['Lusaka',        'Lusaka',          -15.3875,  28.3228, 'ZM', 'Zambia'],
            ['Maputo',        'Maputo',          -25.9692,  32.5732, 'MZ', 'Mozambique'],
            ['Harare',        'Harare',          -17.8252,  31.0335, 'ZW', 'Zimbabwe'],
            ['Lilongwe',      'Central Region',  -13.9626,  33.7741, 'MW', 'Malawi'],
        ];
    }
}
