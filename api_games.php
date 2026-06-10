<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    }
}

// --- Fetch games ---
$upcoming_matches = [];

$data = fetch_json($games_url);
$games_list = $data['games'] ?? (is_array($data) ? $data : []);

if (!empty($games_list)) {
    $malaysia_tz = new DateTimeZone('Asia/Kuala_Lumpur');

    foreach ($games_list as $game) {
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
        }

        $upcoming_matches[] = [
            'stage'     => ($game['group'] ?? false) ? 'Group ' . $game['group'] : 'Stage',
            'home_team' => $game['home_team_name_en'],
            'away_team' => $game['away_team_name_en'],
            'schedule'  => $formatted,
            'timestamp' => $ts,
        ];
    }

    usort($upcoming_matches, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
    $now = time();
    $upcoming_matches = array_values(array_filter($upcoming_matches, fn($m) => $m['timestamp'] >= ($now - 7200)));
    $upcoming_matches = array_slice($upcoming_matches, 0, 4);
}

echo json_encode($upcoming_matches);
