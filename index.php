<?php
date_default_timezone_set('Asia/Kuala_Lumpur');

// 1. Read the live configuration file written by the dashboard panel
$config_file = 'active_slide.txt';
$live_image = file_exists($config_file) ? trim(file_get_contents($config_file)) : '10.png';

// 2. Fetch and Cache Live World Cup Data
$cache_file = 'worldcup_matches_cache.json';
$stadium_cache_file = 'stadiums_cache.json';
$cache_time = 300; // Cache data for 5 minutes
$stadium_cache_time = 3600; // Stadium data changes rarely - cache 1 hour

$remote_url = 'https://worldcup26.ir/get/games';
$stadium_url = 'https://worldcup26.ir/get/stadiums';
$json_data = null;

// --- Fetch games ---
if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
    $json_data = file_get_contents($cache_file);
} else {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $remote_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    $json_data = curl_exec($ch);
    curl_close($ch);

    if ($json_data) {
        file_put_contents($cache_file, $json_data);
    } elseif (file_exists($cache_file)) {
        $json_data = file_get_contents($cache_file);
    }
}

// --- Fetch stadiums and build timezone map ---
$stadium_timezones = [];
$stadium_info = []; // stadium_id => [name, city, country]

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

// Try to fetch stadiums from API
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

// Parse stadium data and build timezone map
if ($stadiums_json) {
    $stadium_data = json_decode($stadiums_json, true);
    $stadium_list = isset($stadium_data['stadiums']) ? $stadium_data['stadiums'] : [];

    foreach ($stadium_list as $s) {
        $sid = $s['id'];
        $city = $s['city_en'];
        $country = $s['country_en'];

        // Store stadium info for display
        $stadium_info[$sid] = [
            'name' => $s['name_en'],
            'city' => $city,
            'country' => $country,
        ];

        // Resolve timezone from city name
        $tz = null;
        // Try exact city match first
        if (isset($city_timezone_map[$city])) {
            $tz = $city_timezone_map[$city];
        } else {
            // Try fuzzy match: check if any map key appears in the city string
            foreach ($city_timezone_map as $city_key => $tz_val) {
                if (stripos($city, $city_key) !== false) {
                    $tz = $tz_val;
                    break;
                }
            }
        }

        // Fallback by region
        if (!$tz) {
            $region = $s['region'] ?? '';
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

$data = json_decode($json_data, true);
$upcoming_matches = [];

$games_list = isset($data['games']) ? $data['games'] : (is_array($data) ? $data : []);

if (!empty($games_list)) {
    $malaysia_tz = new DateTimeZone('Asia/Kuala_Lumpur');
    $current_timestamp = time();

    foreach ($games_list as $game) {
        if (isset($game['home_team_name_en']) && !empty($game['home_team_name_en'])) {

            $date_str = $game['local_date']; // Format: "06/11/2026 13:00"

            $stadium_id = isset($game['stadium_id']) ? (string)$game['stadium_id'] : '';
            $tz_name = isset($stadium_timezones[$stadium_id])
                ? $stadium_timezones[$stadium_id]
                : 'America/New_York';
            $local_tz = new DateTimeZone($tz_name);

            $date = DateTime::createFromFormat('m/d/Y H:i', $date_str, $local_tz);

            if ($date) {
                $date->setTimezone($malaysia_tz);
                $formatted_date = $date->format('D, j M') . '<br>' . $date->format('g:ia') . ' <span class="tz-badge">MYT</span>';
                $timestamp = $date->getTimestamp();
            } else {
                $formatted_date = $game['local_date'];
                $timestamp = $current_timestamp;
            }

            // Stadium display info
            $stadium_display = '';
            if (isset($stadium_info[$stadium_id])) {
                $s = $stadium_info[$stadium_id];
                $stadium_display = htmlspecialchars($s['city']) . ', ' . htmlspecialchars($s['country']);
            }

            $upcoming_matches[] = [
                'stage' => isset($game['group']) && !empty($game['group']) ? 'Group ' . $game['group'] : 'Match Stage',
                'home_team' => $game['home_team_name_en'],
                'away_team' => $game['away_team_name_en'],
                'schedule' => $formatted_date,
                'timestamp' => $timestamp,
                'stadium' => $stadium_display,
            ];
        }
    }

    // Sort chronologically
    usort($upcoming_matches, function($a, $b) {
        return $a['timestamp'] <=> $b['timestamp'];
    });

    // Filter out matches completed more than 2.5 hours ago
    $upcoming_matches = array_filter($upcoming_matches, function($match) use ($current_timestamp) {
        return $match['timestamp'] >= ($current_timestamp - 9000);
    });

    // Keep top 4
    $upcoming_matches = array_slice(array_values($upcoming_matches), 0, 4);
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
        @font-face {
            font-family: 'FWC2026-NormalRegular';
            src: url('/fonts/FWC2026-NormalRegular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        @font-face {
            font-family: 'Inter-Custom';
            src: url('/assets/fonts/Inter_18pt-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }
        
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            background-color: #000;
            color: #fff;
            overflow: hidden;
            font-family: 'FWC2026-NormalRegular', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        h1, h2, h3, h4, h5, h6, span, div, p {
            font-family: 'FWC2026-NormalRegular', sans-serif;
        }

        .inter { 
            font-family: 'Inter-Custom', sans-serif !important; 
        }

        .tv-container {
            height: 100vh;
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .main-content-row {
            display: table-row;
            height: 88vh;
        }

        .sidebar-cell {
            display: table-cell;
            width: 16%;
            vertical-align: top;
            padding: 20px 15px;
            background-image: url(assets/bg-sidebar.png);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .sidebar-header-wrapper {
            position: relative;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .carousel-cell {
            display: table-cell;
            width: 84%;
            color: #1a1a1a;
            vertical-align: middle;
            text-align: center;
            position: relative;
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            box-shadow: 
            inset 4px 4px 30px rgba(0, 0, 0, 0.1), 
            inset -4px -4px 30px rgba(0, 0, 0, 0.1);
        }

        .ticker-row {
            display: table-row;
            height: 12vh;
            background-image: url(assets/bg-sidebar2.png);
            background-repeat: no-repeat;
            background-size: contain;
            background-position: left;
        }

        .ticker-container-cell {
            display: table-cell;
            vertical-align: middle;
            padding: 0 15px;
        }

        .ticker-flex-layout {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
        }

        .ticker-label {
            font-weight: 700;
            font-size: 1.15rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            padding-right: 20px;
            color: #ffffff;
        }

        .bottom-right-logo-cell {
            display: table-cell;
            width: 70px;
            vertical-align: middle;
            text-align: center;
            background-color: #000000;
        }

        .red{ background-color: #D40101; border-radius: 10px; }
        .dark-red{ background-color: #731311; border-radius: 10px; }
        .green{ background-color: #00C953; border-radius: 10px; }
        .dark-green{ background-color: #004E3C; border-radius: 10px; }
        .card{ border: none; }
        .card-header:first-child{ border-radius: 9px 9px 0 0; }

        /* MYT timezone badge */
        .tz-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 3px;
            padding: 0 4px;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            vertical-align: middle;
            line-height: 1.3;
        }

        /* Stadium location text */
        .stadium-location {
            font-size: 0.65rem;
            color: #6c757d;
            display: block;
            margin-top: 2px;
            line-height: 1.2;
        }
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
                    <?php foreach ($upcoming_matches as $index => $match): ?>
                        <?php $style = $card_styles[$index % count($card_styles)]; ?>
                        <div class="card mb-3" style="border-radius: 10px;">
                            <div class="card-header text-white inter fw-bold <?php echo $style; ?>">
                                <?php echo htmlspecialchars($match['stage']); ?>
                            </div>
                            <div class="card-body text-dark" style="padding: 10px 12px; background-color: #ffffff; border-radius: 0 0 10px 10px;">
                                <div class="row g-0 align-items-center">
                                    <div class="col-md-7" style="max-width: 62%;">
                                        <span class="inter fw-bold text-dark" style="display:block; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; font-size:0.9rem;">
                                            <?php echo htmlspecialchars($match['home_team']); ?>
                                        </span>
                                        <span class="inter fw-bold text-dark" style="display:block; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; font-size:0.9rem;">
                                            <?php echo htmlspecialchars($match['away_team']); ?>
                                        </span>
                                        <?php if (!empty($match['stadium'])): ?>
                                            <span class="stadium-location inter">
                                                <i class="bi bi-geo-alt"></i> <?php echo $match['stadium']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-5 text-end" style="max-width: 38%;">
                                        <span class="inter text-secondary fw-bold" style="line-height: 1.2; display: block; font-size: 0.78rem;">
                                            <?php echo $match['schedule']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-white-50 inter" style="font-size:0.8rem;">No matches scheduled.</p>
                <?php endif; ?>
            </div>

            <div class="carousel-cell" style="background-image: url('assets/slide/<?php echo htmlspecialchars($live_image); ?>');">
            </div>
        </div>

        <div class="ticker-row">
            <div class="ticker-container-cell">
                <div class="ticker-flex-layout">
                    <div></div>
                    <div class="d-flex justify-content-end ticker-label">Live Score :</div>
                </div>
            </div>

            <div class="bottom-right-logo-cell">
                <div class="d-flex bd-highlight mb-3">
                    <div class="p-2 bd-highlight">
                        <div class="card">
                            <div class="d-flex bd-highlight">
                                <span class="text-dark px-2 py-2">Coming Soon</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
        crossorigin="anonymous"></script>
        
    <script>
        setInterval(() => {
            fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newBg = doc.querySelector('.carousel-cell').style.backgroundImage;
                const activeContainer = document.querySelector('.carousel-cell');
                if(activeContainer.style.backgroundImage !== newBg) {
                    activeContainer.style.backgroundImage = newBg;
                }
            }).catch(err => console.warn("Polling slide failure:", err));
        }, 4000);
    </script>
</body>
</html>
