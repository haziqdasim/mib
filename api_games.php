<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cache_file = 'matches_cache.json';
$cache_time = 300; // Cache duration in seconds (5 minutes)
$remote_url = 'https://worldcup26.ir/get/games';

// 1. Serve cache if it is fresh to save bandwidth and prevent rate-limiting
if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
    echo file_get_contents($cache_file);
    exit;
}

// 2. Fetch fresh data from the remote source
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $remote_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
$json_data = curl_exec($ch);
curl_close($ch);

if (!$json_data) {
    // Fallback to stale cache if remote request fails
    if (file_exists($cache_file)) {
        echo file_get_contents($cache_file);
    } else {
        echo json_encode(['error' => 'Unable to fetch match data', 'games' => []]);
    }
    exit;
}

$data = json_decode($json_data, true);
$upcoming_matches = [];

// Use the data structure containing games
$games_list = isset($data['games']) ? $data['games'] : (is_array($data) ? $data : []);

if (!empty($games_list)) {
    $malaysia_tz   = new DateTimeZone('Asia/Kuala_Lumpur');

    // Map stadium ID to its respective host city/country timezone
    $stadium_timezones = [
        '1'  => 'America/Mexico_City',
        '2'  => 'America/Mexico_City',
        '3'  => 'America/Monterrey',
        '4'  => 'America/Chicago',
        '5'  => 'America/Chicago',
        '6'  => 'America/Chicago',
        '7'  => 'America/New_York',
        '8'  => 'America/New_York',
        '9'  => 'America/New_York',
        '10' => 'America/New_York',
        '11' => 'America/New_York',
        '12' => 'America/Toronto',
        '13' => 'America/Vancouver',
        '14' => 'America/Los_Angeles',
        '15' => 'America/Los_Angeles',
        '16' => 'America/Los_Angeles'
    ];

    foreach ($games_list as $game) {
        // Only target games that have structural match names available
        if (isset($game['home_team_name_en']) && $game['home_team_name_en'] !== null) {
            
            $date_str = $game['local_date']; // Format: "06/11/2026 13:00"
            
            $stadium_id = isset($game['stadium_id']) ? (string)$game['stadium_id'] : '';
            $tz_name = isset($stadium_timezones[$stadium_id]) ? $stadium_timezones[$stadium_id] : 'America/New_York';
            $local_tz = new DateTimeZone($tz_name);

            $date = DateTime::createFromFormat('m/d/Y H:i', $date_str, $local_tz);
            
            if ($date) {
                $date->setTimezone($malaysia_tz);
                $formatted_date = $date->format('D, j M') . '<br>' . $date->format('g:ia');
                $timestamp = $date->getTimestamp();
            } else {
                $formatted_date = $game['local_date'];
                $timestamp = time();
            }

            // Adjust fallback safely for group notation if fields vary
            $group_label = isset($game['group']) ? 'Group ' . $game['group'] : 'Stage';

            $upcoming_matches[] = [
                'stage' => $group_label,
                'home_team' => $game['home_team_name_en'],
                'away_team' => $game['away_team_name_en'],
                'schedule' => $formatted_date,
                'timestamp' => $timestamp
            ];
        }
    }

    // Sort chronologically
    usort($upcoming_matches, function($a, $b) {
        return $a['timestamp'] <=> $b['timestamp'];
    });

    // Filter logic: Exclude finished matches if timestamp is older than 2 hours ago
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