<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

<<<<<<< HEAD
$games_url = 'https://worldcup26.ir/get/games';
$stadiums_url = 'https://worldcup26.ir/get/stadiums';

// City-to-timezone mapping for all 2026 WC host cities
$city_timezone_map = [
    'Mexico City'      => 'America/Mexico_City',
    'Guadalajara'      => 'America/Mexico_City',
    'Monterrey'        => 'America/Monterrey',
    'Dallas'           => 'America/Chicago',
    'Houston'          => 'America/Chicago',
    'Kansas City'      => 'America/Chicago',
    'Atlanta'          => 'America/New_York',
    'Miami'            => 'America/New_York',
    'Boston'           => 'America/New_York',
    'Philadelphia'     => 'America/New_York',
    'New York'         => 'America/New_York',
    'Toronto'          => 'America/Toronto',
    'Vancouver'        => 'America/Vancouver',
    'Seattle'          => 'America/Los_Angeles',
    'San Francisco'    => 'America/Los_Angeles',
    'Los Angeles'      => 'America/Los_Angeles',
];

function fetch_json($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($http_code === 200 && $result) ? json_decode($result, true) : null;
}

// --- Build stadium timezone map from API ---
$stadium_timezones = [];
$stadium_data = fetch_json($stadiums_url);

if ($stadium_data && isset($stadium_data['stadiums'])) {
    foreach ($stadium_data['stadiums'] as $s) {
        $sid    = $s['id'];
        $city   = $s['city_en'];
        $region = $s['region'] ?? '';

        $tz = null;
        if (isset($city_timezone_map[$city])) {
            $tz = $city_timezone_map[$city];
        } else {
            foreach ($city_timezone_map as $ck => $tv) {
                if (stripos($city, $ck) !== false) { $tz = $tv; break; }
            }
        }
        if (!$tz) {
            $tz = match ($region) {
                'Eastern' => 'America/New_York',
                'Central' => 'America/Chicago',
                'Western' => 'America/Los_Angeles',
                default   => 'America/New_York',
            };
        }
        $stadium_timezones[$sid] = $tz;
=======
$cache_file = 'matches_cache.json';
$stadium_cache_file = 'stadiums_cache.json';
$cache_time = 300; // Cache duration in seconds (5 minutes)
$stadium_cache_time = 3600; // Stadium data cache 1 hour
$remote_url = 'https://worldcup26.ir/get/games';
$stadium_url = 'https://worldcup26.ir/get/stadiums';

// 1. Serve games cache if fresh
if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
    echo file_get_contents($cache_file);
    exit;
}

// City-to-timezone mapping for all 2026 WC host cities
$city_timezone_map = [
    // Mexico
    'Mexico City'      => 'America/Mexico_City',
    'Guadalajara'      => 'America/Mexico_City',
    'Monterrey'        => 'America/Monterrey',
    // USA - Central
    'Dallas'           => 'America/Chicago',
    'Houston'          => 'America/Chicago',
    'Kansas City'      => 'America/Chicago',
    // USA - Eastern
    'Atlanta'          => 'America/New_York',
    'Miami'            => 'America/New_York',
    'Boston'           => 'America/New_York',
    'Philadelphia'     => 'America/New_York',
    'New York'         => 'America/New_York',
    // Canada
    'Toronto'          => 'America/Toronto',
    'Vancouver'        => 'America/Vancouver',
    // USA - Western
    'Seattle'          => 'America/Los_Angeles',
    'San Francisco'    => 'America/Los_Angeles',
    'Los Angeles'      => 'America/Los_Angeles',
];

// Build stadium timezone map from API
$stadium_timezones = [];
$stadiums_json = null;

if (file_exists($stadium_cache_file) && (time() - filemtime($stadium_cache_file) < $stadium_cache_time)) {
    $stadiums_json = file_get_contents($stadium_cache_file);
} else {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $stadium_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    $stadiums_json = curl_exec($ch);
    curl_close($ch);

    if ($stadiums_json) {
        file_put_contents($stadium_cache_file, $stadiums_json);
    } elseif (file_exists($stadium_cache_file)) {
        $stadiums_json = file_get_contents($stadium_cache_file);
    }
}

if ($stadiums_json) {
    $stadium_data = json_decode($stadiums_json, true);
    $stadium_list = isset($stadium_data['stadiums']) ? $stadium_data['stadiums'] : [];

    foreach ($stadium_list as $s) {
        $sid = $s['id'];
        $city = $s['city_en'];
        $region = $s['region'] ?? '';

        // Resolve timezone
        $tz = null;
        if (isset($city_timezone_map[$city])) {
            $tz = $city_timezone_map[$city];
        } else {
            foreach ($city_timezone_map as $city_key => $tz_val) {
                if (stripos($city, $city_key) !== false) {
                    $tz = $tz_val;
                    break;
                }
            }
        }

        if (!$tz) {
            $tz = match ($region) {
                'Eastern'  => 'America/New_York',
                'Central'  => 'America/Chicago',
                'Western'  => 'America/Los_Angeles',
                default    => 'America/New_York',
            };
        }

        $stadium_timezones[$sid] = $tz;
    }
}

// 2. Fetch fresh game data from the remote source
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $remote_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
$json_data = curl_exec($ch);
curl_close($ch);

if (!$json_data) {
    if (file_exists($cache_file)) {
        echo file_get_contents($cache_file);
    } else {
        echo json_encode(['error' => 'Unable to fetch match data', 'games' => []]);
>>>>>>> 6ea361b9a41707233084cb0608f768c1a0432082
    }
}

// --- Fetch games ---
$upcoming_matches = [];

<<<<<<< HEAD
$data = fetch_json($games_url);
$games_list = $data['games'] ?? (is_array($data) ? $data : []);
=======
$games_list = isset($data['games']) ? $data['games'] : (is_array($data) ? $data : []);
>>>>>>> 6ea361b9a41707233084cb0608f768c1a0432082

if (!empty($games_list)) {
    $malaysia_tz = new DateTimeZone('Asia/Kuala_Lumpur');

    foreach ($games_list as $game) {
<<<<<<< HEAD
        if (empty($game['home_team_name_en'])) continue;

        $date_str   = $game['local_date'];
        $stadium_id = isset($game['stadium_id']) ? (string)$game['stadium_id'] : '';
        $tz_name    = $stadium_timezones[$stadium_id] ?? 'America/New_York';
        $date       = DateTime::createFromFormat('m/d/Y H:i', $date_str, new DateTimeZone($tz_name));

        if ($date) {
            $date->setTimezone($malaysia_tz);
            $formatted = $date->format('D, j M') . ' ' . $date->format('g:ia T');
            $ts = $date->getTimestamp();
        } else {
            $formatted = $date_str;
            $ts = time();
=======
        if (isset($game['home_team_name_en']) && $game['home_team_name_en'] !== null) {

            $date_str = $game['local_date']; // Format: "06/11/2026 13:00"

            $stadium_id = isset($game['stadium_id']) ? (string)$game['stadium_id'] : '';
            $tz_name = isset($stadium_timezones[$stadium_id])
                ? $stadium_timezones[$stadium_id]
                : 'America/New_York';
            $local_tz = new DateTimeZone($tz_name);

            $date = DateTime::createFromFormat('m/d/Y H:i', $date_str, $local_tz);

            if ($date) {
                $date->setTimezone($malaysia_tz);
                $formatted_date = $date->format('D, j M') . ' ' . $date->format('g:ia T');
                $timestamp = $date->getTimestamp();
            } else {
                $formatted_date = $game['local_date'];
                $timestamp = time();
            }

            $group_label = isset($game['group']) ? 'Group ' . $game['group'] : 'Stage';

            $upcoming_matches[] = [
                'stage' => $group_label,
                'home_team' => $game['home_team_name_en'],
                'away_team' => $game['away_team_name_en'],
                'schedule' => $formatted_date,
                'timestamp' => $timestamp,
            ];
>>>>>>> 6ea361b9a41707233084cb0608f768c1a0432082
        }

        $upcoming_matches[] = [
            'stage'     => ($game['group'] ?? false) ? 'Group ' . $game['group'] : 'Stage',
            'home_team' => $game['home_team_name_en'],
            'away_team' => $game['away_team_name_en'],
            'schedule'  => $formatted,
            'timestamp' => $ts,
        ];
    }

<<<<<<< HEAD
    usort($upcoming_matches, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
    $now = time();
    $upcoming_matches = array_values(array_filter($upcoming_matches, fn($m) => $m['timestamp'] >= ($now - 7200)));
    $upcoming_matches = array_slice($upcoming_matches, 0, 4);
}

echo json_encode($upcoming_matches);
=======
    // Sort chronologically
    usort($upcoming_matches, function($a, $b) {
        return $a['timestamp'] <=> $b['timestamp'];
    });

    // Filter out matches completed more than 2 hours ago
    $current_time = time();
    $upcoming_matches = array_filter($upcoming_matches, function($match) use ($current_time) {
        return $match['timestamp'] >= ($current_time - 7200);
    });

    // Extract the top 4 matches
    $upcoming_matches = array_slice(array_values($upcoming_matches), 0, 4);
}

// 3. Save processed lightweight layout response to cache file
$output_json = json_encode($upcoming_matches);
file_put_contents($cache_file, $output_json);

echo $output_json;
>>>>>>> 6ea361b9a41707233084cb0608f768c1a0432082
