<?php
date_default_timezone_set('Asia/Kuala_Lumpur');

$config_file = 'active_slide.txt';
$live_image = file_exists($config_file) ? trim(file_get_contents($config_file)) : '10.png';

$games_url = 'https://worldcup26.ir/get/games';
$stadiums_url = 'https://worldcup26.ir/get/stadiums';

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

// --- Stadiums ---
$stadium_timezones = [];
$stadium_info = [];

$stadium_data = fetch_json($stadiums_url);
if ($stadium_data && isset($stadium_data['stadiums'])) {
    foreach ($stadium_data['stadiums'] as $s) {
        $sid = $s['id'];
        $city = $s['city_en'];
        $region = $s['region'] ?? '';
        $stadium_info[$sid] = ['name' => $s['name_en'], 'city' => $city, 'country' => $s['country_en']];
        $tz = null;
        if (isset($city_timezone_map[$city])) { $tz = $city_timezone_map[$city]; }
        else { foreach ($city_timezone_map as $ck => $tv) { if (stripos($city, $ck) !== false) { $tz = $tv; break; } } }
        if (!$tz) { $tz = match ($region) { 'Eastern'=>'America/New_York', 'Central'=>'America/Chicago', 'Western'=>'America/Los_Angeles', default=>'America/New_York' }; }
        $stadium_timezones[$sid] = $tz;
    }
}

// --- Games ---
$data = fetch_json($games_url);
$upcoming_matches = [];
$live_scores = [];
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

        $formatted = $date_str;
        $ts = $current_ts;
        if ($date) {
            $date->setTimezone($malaysia_tz);
            $formatted = $date->format('D, j M') . '<br>' . $date->format('g:ia') . ' <span class="tz-badge">MYT</span>';
            $ts = $date->getTimestamp();
        }

        $finished   = $game['finished'] ?? 'FALSE';
        $elapsed    = $game['time_elapsed'] ?? 'notstarted';
        $h_score    = $game['home_score'] ?? '0';
        $a_score    = $game['away_score'] ?? '0';

        $loc = '';
        if (isset($stadium_info[$stadium_id])) {
            $s = $stadium_info[$stadium_id];
            $loc = htmlspecialchars($s['city']) . ', ' . htmlspecialchars($s['country']);
        }

        $entry = [
            'stage'       => (!empty($game['group']) ? 'Group ' . $game['group'] : 'Match Stage'),
            'home_team'   => $game['home_team_name_en'],
            'away_team'   => $game['away_team_name_en'],
            'schedule'    => $formatted,
            'timestamp'   => $ts,
            'stadium'     => $loc,
            'home_score'  => $h_score,
            'away_score'  => $a_score,
            'finished'    => $finished,
            'time_elapsed'=> $elapsed,
        ];

        // Live scores: in-progress or finished games
        if ($elapsed !== 'notstarted' || $finished === 'TRUE') {
            $live_scores[] = $entry;
        }

        // Upcoming sidebar: future games
        if ($elapsed === 'notstarted' && $finished === 'FALSE') {
            $upcoming_matches[] = $entry;
        }
    }

    usort($upcoming_matches, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
    $upcoming_matches = array_values(array_filter($upcoming_matches, fn($m) => $m['timestamp'] >= ($current_ts - 9000)));
    $upcoming_matches = array_slice($upcoming_matches, 0, 4);

    // Sort live: in-progress first, then finished by timestamp desc
    usort($live_scores, function($a, $b) {
        $aLive = $a['finished'] === 'TRUE' ? 1 : 0;
        $bLive = $b['finished'] === 'TRUE' ? 1 : 0;
        if ($aLive !== $bLive) return $aLive - $bLive;
        return $b['timestamp'] - $a['timestamp'];
    });
}

$card_styles = ['dark-red', 'red', 'green', 'dark-green'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TV Multipurpose Information Board</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        @font-face { font-family: 'FWC2026-NormalRegular'; src: url('/fonts/FWC2026-NormalRegular.ttf') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Inter-Custom'; src: url('/assets/fonts/Inter_18pt-Regular.ttf') format('truetype'); font-weight: normal; font-style: normal; }
        *, *::before, *::after { box-sizing: border-box; }
        body, html { margin: 0; padding: 0; height: 100%; background-color: #000; color: #fff; overflow: hidden; font-family: 'FWC2026-NormalRegular', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        h1, h2, h3, h4, h5, h6, span, div, p { font-family: 'FWC2026-NormalRegular', sans-serif; }
        .inter { font-family: 'Inter-Custom', sans-serif !important; }

        .tv-container { height: 100vh; display: table; width: 100%; table-layout: fixed; }
        .main-content-row { display: table-row; height: 88vh; }
        .sidebar-cell { display: table-cell; width: 16%; vertical-align: top; padding: 20px 15px; background-image: url(assets/bg-sidebar.png); background-size: cover; background-position: center; background-repeat: no-repeat; }
        .sidebar-header-wrapper { position: relative; text-align: center; margin-bottom: 25px; }
        .carousel-cell { display: table-cell; width: 84%; color: #1a1a1a; vertical-align: middle; text-align: center; position: relative; background-position: center; background-size: cover; background-repeat: no-repeat; box-shadow: inset 4px 4px 30px rgba(0,0,0,0.1), inset -4px -4px 30px rgba(0,0,0,0.1); }
        .ticker-row { display: table-row; height: 12vh; background-image: url(assets/bg-sidebar2.png); background-repeat: no-repeat; background-size: contain; background-position: left; }
        .ticker-container-cell { display: table-cell; vertical-align: middle; padding: 0 15px; }
        .ticker-flex-layout { display: flex; align-items: center; justify-content: flex-start; height: 100%; gap: 20px; }
        .ticker-label { font-weight: 700; font-size: 1.15rem; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; color: #fff; flex-shrink: 0; }
        .bottom-right-logo-cell { display: table-cell; width: 130px; vertical-align: middle; text-align: center; background-color: #000; }

        .red{ background-color: #D40101; border-radius: 10px; }
        .dark-red{ background-color: #731311; border-radius: 10px; }
        .green{ background-color: #00C953; border-radius: 10px; }
        .dark-green{ background-color: #004E3C; border-radius: 10px; }
        .card{ border: none; }
        .card-header:first-child{ border-radius: 9px 9px 0 0; }

        .tz-badge { display: inline-block; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); border-radius: 3px; padding: 0 4px; font-size: 0.65rem; font-weight: 600; letter-spacing: 0.3px; vertical-align: middle; line-height: 1.3; }
        .stadium-location { font-size: 0.65rem; color: #6c757d; display: block; margin-top: 2px; line-height: 1.2; }

        /* Ticker score items */
        .score-item { display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; font-size: 1rem; font-weight: 700; color: #fff; padding: 4px 14px; border-right: 1px solid rgba(255,255,255,0.15); }
        .score-item:last-child { border-right: none; }
        .score-vs { color: rgba(255,255,255,0.5); font-size: 0.8rem; margin: 0 2px; }
        .score-num { background: rgba(255,255,255,0.12); border-radius: 4px; padding: 1px 7px; font-family: 'Inter-Custom', monospace; font-size: 1.1rem; min-width: 24px; text-align: center; }
        .score-big { font-size: 1.6rem; font-weight: 900; padding: 2px 10px; background: transparent; }
        .score-badge-live { background: #D40101; color: #fff; font-size: 0.6rem; padding: 1px 5px; border-radius: 3px; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; }
        .score-badge-ft { background: #333; color: #aaa; font-size: 0.6rem; padding: 1px 5px; border-radius: 3px; text-transform: uppercase; font-weight: 800; }
        /* Banner card (bottom-right) */
        .score-banner-card {
            background: #1a1a1a; border: 1px solid #333; border-radius: 8px;
            padding: 6px 8px; display: inline-block;
        }
        .banner-team { color: #ccc; font-size: 0.7rem; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 110px; }
        .banner-score { margin: 2px 0; }
        .ticker-scores-inner { display: flex; align-items: center; gap: 0; }
        .no-scores { color: rgba(255,255,255,0.4); font-size: 0.9rem; font-style: italic; }
    </style>
</head>

<body>

<div class="tv-container">
    <div class="main-content-row">
        <div class="sidebar-cell">
            <div class="sidebar-header-wrapper" style="padding-bottom: 20%">
                <a href="dashboard.php">
                    <img src="/assets/logo-white.png" class="img-fluid" style="width:30%" alt="logo">
                </a>
            </div>

            <h5 class="text-white mt-5 mb-3">World Cup Matches</h5>

            <?php if (!empty($upcoming_matches)): ?>
                <?php foreach ($upcoming_matches as $i => $m): ?>
                    <?php $style = $card_styles[$i % count($card_styles)]; ?>
                    <div class="card mb-3" style="border-radius: 10px;">
                        <div class="card-header text-white inter fw-bold <?= $style ?>"><?= htmlspecialchars($m['stage']) ?></div>
                        <div class="card-body text-dark" style="padding:10px 12px; background:#fff; border-radius:0 0 10px 10px;">
                            <div class="row g-0 align-items-center">
                                <div class="col-md-7" style="max-width:62%;">
                                    <span class="inter fw-bold text-dark" style="display:block; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; font-size:0.9rem;"><?= htmlspecialchars($m['home_team']) ?></span>
                                    <span class="inter fw-bold text-dark" style="display:block; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; font-size:0.9rem;"><?= htmlspecialchars($m['away_team']) ?></span>
                                    <?php if (!empty($m['stadium'])): ?><span class="stadium-location inter"><i class="bi bi-geo-alt"></i> <?= $m['stadium'] ?></span><?php endif; ?>
                                </div>
                                <div class="col-md-5 text-end" style="max-width:38%;">
                                    <span class="inter text-secondary fw-bold" style="line-height:1.2; display:block; font-size:0.78rem;"><?= $m['schedule'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-white-50 inter" style="font-size:0.8rem;">No matches scheduled.</p>
            <?php endif; ?>
        </div>

        <div class="carousel-cell" style="background-image: url('assets/slide/<?= htmlspecialchars($live_image) ?>');"></div>
    </div>

    <div class="ticker-row">
        <div class="ticker-container-cell">
            <div class="ticker-flex-layout">
                <div class="ticker-label">Live Score :</div>
                <div id="scoreTicker" class="ticker-scores-inner">
                    <?php if (!empty($live_scores)): ?>
                        <?php foreach ($live_scores as $s): ?>
                            <?php
                                $badge = $s['finished'] === 'TRUE'
                                    ? '<span class="score-badge-ft">FT</span>'
                                    : '<span class="score-badge-live">LIVE</span>';
                            ?>
                            <div class="score-item">
                                <?= $badge ?>
                                <span><?= htmlspecialchars($s['home_team']) ?></span>
                                <span class="score-num"><?= htmlspecialchars($s['home_score']) ?></span>
                                <span class="score-vs">:</span>
                                <span class="score-num"><?= htmlspecialchars($s['away_score']) ?></span>
                                <span><?= htmlspecialchars($s['away_team']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="bottom-right-logo-cell">
            <div class="score-banner-card" id="scoreBanner">
                <?php if (!empty($live_scores)): ?>
                    <?php $s = $live_scores[0]; ?>
                    <div class="banner-team"><?= htmlspecialchars($s['home_team']) ?></div>
                    <div class="banner-score"><span class="score-num score-big"><?= htmlspecialchars($s['home_score']) ?></span><span class="score-vs">-</span><span class="score-num score-big"><?= htmlspecialchars($s['away_score']) ?></span></div>
                    <div class="banner-team"><?= htmlspecialchars($s['away_team']) ?></div>
                <?php else: ?>
                    <div class="banner-team">Home</div>
                    <div class="banner-score"><span class="score-num score-big">0</span><span class="score-vs">-</span><span class="score-num score-big">0</span></div>
                    <div class="banner-team">Away</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
    crossorigin="anonymous"></script>
<script>
// Carousel slide poll
setInterval(() => {
    fetch(window.location.href)
    .then(r => r.text())
    .then(html => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const newBg = doc.querySelector('.carousel-cell').style.backgroundImage;
        const el = document.querySelector('.carousel-cell');
        if (el.style.backgroundImage !== newBg) el.style.backgroundImage = newBg;
    }).catch(e => console.warn("Slide poll:", e));
}, 4000);

// Score ticker poll — updates every 30s
function renderScoreItem(s) {
    const badge = s.finished === 'TRUE'
        ? '<span class="score-badge-ft">FT</span>'
        : '<span class="score-badge-live">LIVE</span>';
    return '<div class="score-item">'
        + badge
        + '<span>' + s.home_team + '</span>'
        + '<span class="score-num">' + s.home_score + '</span>'
        + '<span class="score-vs">:</span>'
        + '<span class="score-num">' + s.away_score + '</span>'
        + '<span>' + s.away_team + '</span>'
        + '</div>';
}

function renderBanner(scores) {
    const el = document.getElementById('scoreBanner');
    if (scores.length > 0) {
        const s = scores[0];
        el.innerHTML = '<div class="banner-team">' + s.home_team + '</div>'
            + '<div class="banner-score"><span class="score-num score-big">' + s.home_score + '</span><span class="score-vs">-</span><span class="score-num score-big">' + s.away_score + '</span></div>'
            + '<div class="banner-team">' + s.away_team + '</div>';
    } else {
        el.innerHTML = '<div class="banner-team">Home</div>'
            + '<div class="banner-score"><span class="score-num score-big">0</span><span class="score-vs">-</span><span class="score-num score-big">0</span></div>'
            + '<div class="banner-team">Away</div>';
    }
}

setInterval(() => {
    fetch('api_games.php')
    .then(r => r.json())
    .then(data => {
        const scores = data.scores || [];
        document.getElementById('scoreTicker').innerHTML = scores.map(renderScoreItem).join('');
        renderBanner(scores);
    }).catch(e => console.warn("Score poll:", e));
}, 30000);
</script>
</body>
</html>
