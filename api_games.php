<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$games_url    = 'https://worldcup26.ir/get/games';
$stadiums_url = 'https://worldcup26.ir/get/stadiums';
$teams_url    = 'https://worldcup26.ir/get/teams';

$city_timezone_map = [
    'Mexico City'      => 'America/Mexico_City', 'Guadalajara' => 'America/Mexico_City',
    'Monterrey'        => 'America/Monterrey',
    'Dallas'           => 'America/Chicago',     'Houston'     => 'America/Chicago',   'Kansas City' => 'America/Chicago',
    'Atlanta'          => 'America/New_York',    'Miami'       => 'America/New_York',
    'Boston'           => 'America/New_York',    'Philadelphia'=> 'America/New_York',
    'New York'         => 'America/New_York',
    'Toronto'          => 'America/Toronto',     'Vancouver'   => 'America/Vancouver',
    'Seattle'          => 'America/Los_Angeles', 'San Francisco'=> 'America/Los_Angeles', 'Los Angeles' => 'America/Los_Angeles',
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

// --- Teams ---
$team_map = [];
$tdata = fetch_json($teams_url);
if ($tdata && isset($tdata['teams'])) {
    foreach ($tdata['teams'] as $t) {
        $team_map[$t['name_en']] = [
            'fifa' => $t['fifa_code'] ?? '',
            'flag' => $t['flag'] ?? '',
        ];
    }
}

// --- Stadium timezones ---
$stadium_timezones = [];
$sdata = fetch_json($stadiums_url);
if ($sdata && isset($sdata['stadiums'])) {
    foreach ($sdata['stadiums'] as $s) {
        $sid    = $s['id'];
        $city   = $s['city_en'];
        $region = $s['region'] ?? '';
        $tz = null;
        if (isset($city_timezone_map[$city])) { $tz = $city_timezone_map[$city]; }
        else { foreach ($city_timezone_map as $ck => $tv) { if (stripos($city, $ck) !== false) { $tz = $tv; break; } } }
        if (!$tz) { $tz = match ($region) { 'Eastern'=>'America/New_York', 'Central'=>'America/Chicago', 'Western'=>'America/Los_Angeles', default=>'America/New_York' }; }
        $stadium_timezones[$sid] = $tz;
    }
}

// --- Games ---
$upcoming_matches = [];
$live_scores = [];
$data = fetch_json($games_url);
$games_list = $data['games'] ?? (is_array($data) ? $data : []);

if (!empty($games_list)) {
    $malaysia_tz = new DateTimeZone('Asia/Kuala_Lumpur');
    $current_ts = time();

    foreach ($games_list as $game) {
        if (empty($game['home_team_name_en'])) continue;

        $date_str   = $game['local_date'];
        $stadium_id = isset($game['stadium_id']) ? (string)$game['stadium_id'] : '';
        $tz_name    = $stadium_timezones[$stadium_id] ?? 'America/New_York';
        $date       = DateTime::createFromFormat('m/d/Y H:i', $date_str, new DateTimeZone($tz_name));

        $ts = $current_ts;
        $formatted = $date_str;
        if ($date) {
            $date->setTimezone($malaysia_tz);
            $formatted = $date->format('D, j M') . ' ' . $date->format('g:ia T');
            $ts = $date->getTimestamp();
        }

        $finished   = $game['finished'] ?? 'FALSE';
        $elapsed    = $game['time_elapsed'] ?? 'notstarted';
        $h_score    = $game['home_score'] ?? '0';
        $a_score    = $game['away_score'] ?? '0';

        $h_name = $game['home_team_name_en'];
        $a_name = $game['away_team_name_en'];
        $h_info = $team_map[$h_name] ?? ['fifa'=>'', 'flag'=>''];
        $a_info = $team_map[$a_name] ?? ['fifa'=>'', 'flag'=>''];

        $entry = [
            'stage'       => ($game['group'] ?? false) ? 'Group ' . $game['group'] : 'Stage',
            'home_team'   => $h_name,
            'away_team'   => $a_name,
            'home_fifa'   => $h_info['fifa'],
            'away_fifa'   => $a_info['fifa'],
            'home_flag'   => $h_info['flag'],
            'away_flag'   => $a_info['flag'],
            'schedule'    => $formatted,
            'timestamp'   => $ts,
            'home_score'  => $h_score,
            'away_score'  => $a_score,
            'finished'    => $finished,
            'time_elapsed'=> $elapsed,
        ];

        if ($elapsed !== 'notstarted' || $finished === 'TRUE') {
            $live_scores[] = $entry;
        }
        if ($elapsed === 'notstarted' && $finished === 'FALSE') {
            $upcoming_matches[] = $entry;
        }
    }

    usort($upcoming_matches, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
    $upcoming_matches = array_values(array_filter($upcoming_matches, fn($m) => $m['timestamp'] >= ($current_ts - 7200)));
    $upcoming_matches = array_slice($upcoming_matches, 0, 4);

    usort($live_scores, function($a, $b) {
        $aL = $a['finished'] === 'TRUE' ? 1 : 0;
        $bL = $b['finished'] === 'TRUE' ? 1 : 0;
        if ($aL !== $bL) return $aL - $bL;
        return $b['timestamp'] - $a['timestamp'];
    });
    $live_scores = array_values($live_scores);
}

echo json_encode([
    'matches' => $upcoming_matches,
    'scores'  => $live_scores,
]);
