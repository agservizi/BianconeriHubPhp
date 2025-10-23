<?php
// Basic application configuration for BianconeriHub

if (!function_exists('loadEnvFile')) {
    function loadEnvFile(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || $trimmed[0] === '#') {
                continue;
            }

            if (strpos($trimmed, '=') === false) {
                continue;
            }

            [$name, $value] = explode('=', $trimmed, 2);
            $name = trim($name);
            $value = trim($value);

            if ($value !== '') {
                $firstChar = $value[0];
                $lastChar = substr($value, -1);
                if (($firstChar === "'" && $lastChar === "'") || ($firstChar === '"' && $lastChar === '"')) {
                    $value = substr($value, 1, -1);
                }
            }

            $value = str_replace(['\\n', '\\r'], ["\n", "\r"], $value);

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv($name . '=' . $value);
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        return $default;
    }
}

$envPath = __DIR__ . '/.env';
loadEnvFile($envPath);

$timezone = env('APP_TIMEZONE');
if (is_string($timezone) && $timezone !== '') {
    date_default_timezone_set($timezone);
}

$sessionName = env('SESSION_NAME', 'bianconerihub_session');
if (session_status() === PHP_SESSION_NONE) {
    if (is_string($sessionName) && $sessionName !== '') {
        session_name($sessionName);
    }
    session_start();
}

$siteName = env('APP_NAME', 'BianconeriHub');
$siteTagline = env('APP_TAGLINE', 'Il cuore pulsante dei tifosi juventini');
$baseUrl = rtrim((string) env('BASE_URL', ''), '/');
$appDebug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);

$databaseConfig = [
    'driver' => env('DB_DRIVER', 'mysql'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'database' => env('DB_NAME', 'bianconerihub'),
    'username' => env('DB_USER', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
];

function getDatabaseConfig(): array
{
    global $databaseConfig;

    return $databaseConfig;
}

function getDatabaseConnection(): ?PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    global $appDebug;

    $config = getDatabaseConfig();
    $driver = $config['driver'] ?? 'mysql';

    if ($driver !== 'mysql') {
        trigger_error('Unsupported database driver: ' . $driver, E_USER_WARNING);
        return null;
    }

    $host = $config['host'] ?? '127.0.0.1';
    $port = $config['port'] ?? 3306;
    $database = $config['database'] ?? '';
    $charset = $config['charset'] ?? 'utf8mb4';
    $username = $config['username'] ?? '';
    $password = $config['password'] ?? '';

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

    try {
        $connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Database connection failed: ' . $exception->getMessage());
        if ($appDebug) {
            throw $exception;
        }

        return null;
    }

    return $connection;
}

function formatItalianDate(DateTimeInterface $dateTime): string
{
    static $months = [
        1 => 'gennaio',
        2 => 'febbraio',
        3 => 'marzo',
        4 => 'aprile',
        5 => 'maggio',
        6 => 'giugno',
        7 => 'luglio',
        8 => 'agosto',
        9 => 'settembre',
        10 => 'ottobre',
        11 => 'novembre',
        12 => 'dicembre',
    ];

    $day = $dateTime->format('j');
    $month = (int) $dateTime->format('n');
    $year = $dateTime->format('Y');

    return sprintf('%d %s %s', $day, $months[$month] ?? $month, $year);
}

function normalizeToTimestamp($value): int
{
    if ($value instanceof DateTimeInterface) {
        return $value->getTimestamp();
    }

    if (is_numeric($value)) {
        return (int) $value;
    }

    if (is_string($value)) {
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return $timestamp;
        }
    }

    return time();
}

function generateSlug(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($normalized !== false) {
        $value = $normalized;
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
    $value = trim($value, '-');

    return $value;
}

function buildNewsSlug(string $title, string $link): string
{
    $base = generateSlug($title);

    if ($base === '') {
        $base = 'news';
    }

    $hashSource = $link !== '' ? $link : $title;
    $hash = substr(md5($hashSource), 0, 6);

    return $base . '-' . $hash;
}

function sanitizeFeedExcerpt(?string $value, int $maxLength = 280): string
{
    if (!is_string($value)) {
        return '';
    }

    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(strip_tags($decoded));

    if ($text === '') {
        return '';
    }

    if (mb_strlen($text) > $maxLength) {
        $text = rtrim(mb_substr($text, 0, $maxLength - 1)) . '…';
    }

    return $text;
}

function sanitizeFeedBody(?string $value): string
{
    if (!is_string($value)) {
        return '';
    }

    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $decoded);
    $decoded = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $decoded);
    $decoded = preg_replace('/<br\s*\/?\s*>/i', "\n", $decoded);
    $decoded = preg_replace('/<\/p>/i', "</p>\n", $decoded);
    $text = strip_tags($decoded);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    $text = trim($text);

    if (mb_strlen($text) > 5000) {
        $text = rtrim(mb_substr($text, 0, 5000)) . '…';
    }

    return $text;
}

function normalizeRemoteUrl(?string $url): string
{
    if (!is_string($url)) {
        return '';
    }

    $trimmed = trim($url);
    if ($trimmed === '') {
        return '';
    }

    if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
        return '';
    }

    return $trimmed;
}

function getAppCacheFile(string $fileName): string
{
    $baseDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bianconerihub';

    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0775, true);
    }

    return $baseDir . DIRECTORY_SEPARATOR . ltrim($fileName, DIRECTORY_SEPARATOR);
}

function ensureMatchesTableSupportsExternalSource(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    try {
        $columnCheck = $pdo->query("SHOW COLUMNS FROM matches LIKE 'external_id'");
        if ($columnCheck && $columnCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE matches ADD COLUMN external_id VARCHAR(50) DEFAULT NULL AFTER id");
        }

        $sourceCheck = $pdo->query("SHOW COLUMNS FROM matches LIKE 'source'");
        if ($sourceCheck && $sourceCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE matches ADD COLUMN source VARCHAR(40) DEFAULT NULL AFTER external_id");
        }

        $uniqueIndexCheck = $pdo->query("SHOW INDEX FROM matches WHERE Key_name = 'matches_external_id_unique'");
        if ($uniqueIndexCheck && $uniqueIndexCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE matches ADD UNIQUE KEY matches_external_id_unique (external_id)");
        }

        $sourceIndexCheck = $pdo->query("SHOW INDEX FROM matches WHERE Key_name = 'matches_source_index'");
        if ($sourceIndexCheck && $sourceIndexCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE matches ADD KEY matches_source_index (source)");
        }
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Unable to ensure matches schema: ' . $exception->getMessage());
    }

    $checked = true;
}

function getFootballDataCompetitionCodes(): array
{
    return ['SA', 'CL', 'CI', 'SUC'];
}

function footballDataRequest(string $endpoint, array $query = []): ?array
{
    $apiKey = trim((string) env('FOOTBALL_DATA_API_KEY', ''));
    if ($apiKey === '') {
        return null;
    }

    $baseUrl = 'https://api.football-data.org/v4';
    $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'X-Auth-Token: ' . $apiKey,
        'Accept: application/json',
    ];

    $responseBody = null;
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'BianconeriHub/1.0',
        ]);

        $responseBody = curl_exec($curl);
        if ($responseBody === false) {
            error_log('[BianconeriHub] Football-Data request failed: ' . curl_error($curl));
        }
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => implode("\r\n", array_merge($headers, [
                    'User-Agent: BianconeriHub/1.0',
                ])),
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $line, $matches)) {
                    $statusCode = (int) $matches[1];
                    break;
                }
            }
        }
    }

    if (!is_string($responseBody) || $responseBody === '') {
        return null;
    }

    if ($statusCode >= 400 && $statusCode !== 0) {
        error_log('[BianconeriHub] Football-Data response error: HTTP ' . $statusCode);
        return null;
    }

    $decoded = json_decode($responseBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[BianconeriHub] Football-Data invalid JSON: ' . json_last_error_msg());
        return null;
    }

    return is_array($decoded) ? $decoded : null;
}

function mapFootballDataStatus(?string $status, ?array $score = null): string
{
    if (!is_string($status) || $status === '') {
        return 'Da definire';
    }

    $normalized = strtoupper($status);
    $labels = [
        'SCHEDULED' => 'In programma',
        'TIMED' => 'Calcio d\'inizio confermato',
        'POSTPONED' => 'Rinviata',
        'IN_PLAY' => 'In corso',
        'PAUSED' => 'Intervallo',
        'FINISHED' => 'Terminata',
        'CANCELLED' => 'Annullata',
    ];

    if ($normalized === 'FINISHED' && is_array($score)) {
        $home = $score['fullTime']['home'] ?? $score['fullTime']['homeTeam'] ?? null;
        $away = $score['fullTime']['away'] ?? $score['fullTime']['awayTeam'] ?? null;

        if (is_numeric($home) && is_numeric($away)) {
            return sprintf('Finale %d-%d', (int) $home, (int) $away);
        }
    }

    if ($normalized === 'IN_PLAY' && is_array($score)) {
        $home = $score['fullTime']['home'] ?? null;
        $away = $score['fullTime']['away'] ?? null;

        if (is_numeric($home) && is_numeric($away)) {
            return sprintf('Live %d-%d', (int) $home, (int) $away);
        }
    }

    return $labels[$normalized] ?? ucfirst(strtolower($normalized));
}

function syncJuventusMatchesFromApi(bool $force = false): void
{
    static $hasSynced = false;

    if ($hasSynced && !$force) {
        return;
    }

    $hasSynced = true;

    $cacheFile = getAppCacheFile('football-data-matches.cache');
    $cacheTtl = 1800; // 30 minutes

    if (!$force && is_file($cacheFile)) {
        $lastSync = (int) trim((string) @file_get_contents($cacheFile));
        if ($lastSync > 0 && (time() - $lastSync) < $cacheTtl) {
            return;
        }
    }

    $apiKey = trim((string) env('FOOTBALL_DATA_API_KEY', ''));
    if ($apiKey === '') {
        return;
    }

    $utcNow = new DateTime('now', new DateTimeZone('UTC'));
    $query = [
        'competitions' => implode(',', getFootballDataCompetitionCodes()),
        'status' => 'SCHEDULED,TIMED,POSTPONED,IN_PLAY,PAUSED',
        'limit' => 50,
        'dateFrom' => $utcNow->format('Y-m-d'),
        'dateTo' => $utcNow->modify('+180 days')->format('Y-m-d'),
    ];

    $response = footballDataRequest('/teams/109/matches', $query);
    if (!$response || !isset($response['matches']) || !is_array($response['matches'])) {
        return;
    }

    $matchesToPersist = [];
    $competitions = getFootballDataCompetitionCodes();
    $localTimezone = new DateTimeZone(date_default_timezone_get());

    foreach ($response['matches'] as $match) {
        $competitionCode = $match['competition']['code'] ?? '';
        if ($competitionCode === '' || !in_array($competitionCode, $competitions, true)) {
            continue;
        }

        if (!isset($match['id'], $match['utcDate'])) {
            continue;
        }

        try {
            $kickoffUtc = new DateTime($match['utcDate'], new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            continue;
        }

        $kickoffUtc->setTimezone($localTimezone);

        $homeTeamName = $match['homeTeam']['shortName'] ?? $match['homeTeam']['name'] ?? '';
        $awayTeamName = $match['awayTeam']['shortName'] ?? $match['awayTeam']['name'] ?? '';
        $homeTeamId = isset($match['homeTeam']['id']) ? (int) $match['homeTeam']['id'] : null;

        $isHome = $homeTeamId === 109 || stripos($homeTeamName, 'Juventus') !== false;
        $opponent = $isHome ? ($awayTeamName !== '' ? $awayTeamName : 'Avversario da definire') : ($homeTeamName !== '' ? $homeTeamName : 'Avversario da definire');

        $venue = $match['venue'] ?? '';
        if (trim($venue) === '') {
            $venue = $isHome ? 'Allianz Stadium' : ($homeTeamName !== '' ? $homeTeamName : 'Da definire');
        }

        $matchesToPersist[] = [
            'external_id' => (string) $match['id'],
            'competition' => $match['competition']['name'] ?? 'Juventus',
            'opponent' => $opponent,
            'venue' => $venue,
            'kickoff_at' => $kickoffUtc->format('Y-m-d H:i:s'),
            'status' => mapFootballDataStatus($match['status'] ?? null, $match['score'] ?? null),
            'broadcast' => '',
        ];
    }

    if (empty($matchesToPersist)) {
        return;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return;
    }

    ensureMatchesTableSupportsExternalSource($pdo);

    try {
        $pdo->beginTransaction();

        $delete = $pdo->prepare('DELETE FROM matches WHERE source = :source AND kickoff_at >= (NOW() - INTERVAL 30 DAY)');
        $delete->execute(['source' => 'football-data']);

        $insert = $pdo->prepare('INSERT INTO matches (external_id, source, competition, opponent, venue, kickoff_at, status, broadcast) VALUES (:external_id, :source, :competition, :opponent, :venue, :kickoff_at, :status, :broadcast) ON DUPLICATE KEY UPDATE competition = VALUES(competition), opponent = VALUES(opponent), venue = VALUES(venue), kickoff_at = VALUES(kickoff_at), status = VALUES(status), broadcast = VALUES(broadcast), updated_at = CURRENT_TIMESTAMP');

        foreach ($matchesToPersist as $row) {
            $insert->execute([
                'external_id' => $row['external_id'],
                'source' => 'football-data',
                'competition' => $row['competition'],
                'opponent' => $row['opponent'],
                'venue' => $row['venue'],
                'kickoff_at' => $row['kickoff_at'],
                'status' => $row['status'],
                'broadcast' => $row['broadcast'],
            ]);
        }

        $pdo->commit();
        @file_put_contents($cacheFile, (string) time());
    } catch (PDOException $exception) {
        $pdo->rollBack();
        error_log('[BianconeriHub] Unable to sync matches from Football-Data: ' . $exception->getMessage());
    }
}

function syncNewsFeed(bool $force = false): void
{
    static $lastSyncTimestamp = null;

    $sessionKey = 'bh_news_last_sync';
    $sessionTimestamp = isset($_SESSION[$sessionKey]) ? (int) $_SESSION[$sessionKey] : 0;

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return;
    }

    if ($lastSyncTimestamp === null) {
        $lastSyncTimestamp = $sessionTimestamp;

        if ($lastSyncTimestamp === 0) {
            try {
                $result = $pdo->query('SELECT UNIX_TIMESTAMP(MAX(updated_at)) AS last_sync FROM news');
                $dbTimestamp = (int) ($result->fetchColumn() ?: 0);
                if ($dbTimestamp > 0) {
                    $lastSyncTimestamp = $dbTimestamp;
                    $_SESSION[$sessionKey] = $dbTimestamp;
                }
            } catch (PDOException $exception) {
                error_log('[BianconeriHub] Unable to read news sync timestamp: ' . $exception->getMessage());
            }
        }
    }

    if (!$force && $lastSyncTimestamp !== null && $lastSyncTimestamp !== 0 && (time() - $lastSyncTimestamp) < 900) {
        return;
    }

    $feedUrl = env('NEWS_FEED_URL', 'https://www.tuttojuve.com/rss');
    if (!is_string($feedUrl) || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
        return;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 6,
            'user_agent' => 'BianconeriHubBot/1.0 (+https://bianconerihub.local)',
        ],
    ]);

    $feedContent = @file_get_contents($feedUrl, false, $context);
    if ($feedContent === false) {
        return;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($feedContent, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml || !isset($xml->channel->item)) {
        libxml_clear_errors();
        return;
    }

    $insert = $pdo->prepare('INSERT INTO news (title, slug, tag, excerpt, body, image_path, source_url, published_at) VALUES (:title, :slug, :tag, :excerpt, :body, :image_path, :source_url, :published_at) ON DUPLICATE KEY UPDATE title = VALUES(title), tag = VALUES(tag), excerpt = VALUES(excerpt), body = VALUES(body), image_path = VALUES(image_path), source_url = VALUES(source_url), published_at = VALUES(published_at), updated_at = CURRENT_TIMESTAMP');

    $count = 0;
    foreach ($xml->channel->item as $item) {
        if ($count >= 40) {
            break;
        }

        $title = trim((string) ($item->title ?? ''));
        $link = trim((string) ($item->link ?? ''));

        if ($title === '' || $link === '') {
            $count++;
            continue;
        }

        $slug = buildNewsSlug($title, $link);
        $category = trim((string) ($item->category[0] ?? ''));
        if ($category === '') {
            $category = 'TuttoJuve';
        }
        if (mb_strlen($category) > 40) {
            $category = mb_substr($category, 0, 40);
        }

        $description = (string) ($item->description ?? '');
        $contentNamespace = $item->children('content', true);
        $contentEncoded = '';
        if ($contentNamespace && isset($contentNamespace->encoded)) {
            $contentEncoded = (string) $contentNamespace->encoded;
        }

        $bodyRaw = $contentEncoded !== '' ? $contentEncoded : $description;
        $excerpt = sanitizeFeedExcerpt($description !== '' ? $description : $bodyRaw, 280);
        $body = sanitizeFeedBody($bodyRaw !== '' ? $bodyRaw : $description);

        if ($body === '') {
            $body = $excerpt;
        }

        $pubDate = trim((string) ($item->pubDate ?? ''));
        $publishedAt = null;
        if ($pubDate !== '') {
            $timestamp = strtotime($pubDate);
            if ($timestamp !== false) {
                $publishedAt = date('Y-m-d H:i:s', $timestamp);
            }
        }

        $imageUrl = '';
        if (isset($item->enclosure)) {
            $enclosure = $item->enclosure;
            if (isset($enclosure['url'])) {
                $imageUrl = normalizeRemoteUrl((string) $enclosure['url']);
            }
        }

        if ($imageUrl === '') {
            $mediaNamespace = $item->children('media', true);
            if ($mediaNamespace && isset($mediaNamespace->content)) {
                $mediaUrl = (string) $mediaNamespace->content['url'];
                $imageUrl = normalizeRemoteUrl($mediaUrl);
            }
        }

        try {
            $insert->execute([
                'title' => $title,
                'slug' => $slug,
                'tag' => $category,
                'excerpt' => $excerpt,
                'body' => $body,
                'image_path' => $imageUrl,
                'source_url' => $link,
                'published_at' => $publishedAt,
            ]);
        } catch (PDOException $exception) {
            error_log('[BianconeriHub] Failed to sync news item: ' . $exception->getMessage());
        }

        $count++;
    }

    libxml_clear_errors();
    $lastSyncTimestamp = time();
    $_SESSION[$sessionKey] = $lastSyncTimestamp;
}

function getCommunityStats(): array
{
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return [
            'members' => 0,
            'posts' => 0,
            'upcoming_matches' => 0,
            'new_members_week' => 0,
            'daily_interactions' => 0,
            'events_hosted' => 0,
        ];
    }

    $stats = [
        'members' => 0,
        'posts' => 0,
        'upcoming_matches' => 0,
        'new_members_week' => 0,
        'daily_interactions' => 0,
        'events_hosted' => 0,
    ];

    try {
        $stats['members'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $stats['posts'] = (int) $pdo->query('SELECT COUNT(*) FROM community_posts')->fetchColumn();
        $stats['upcoming_matches'] = (int) $pdo->query('SELECT COUNT(*) FROM matches WHERE kickoff_at >= NOW()')->fetchColumn();
        $stats['new_members_week'] = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= (NOW() - INTERVAL 7 DAY)')->fetchColumn();
        $recentComments = (int) $pdo->query('SELECT COUNT(*) FROM community_post_comments WHERE created_at >= (NOW() - INTERVAL 1 DAY)')->fetchColumn();
        $recentReactions = (int) $pdo->query('SELECT COUNT(*) FROM community_post_reactions WHERE created_at >= (NOW() - INTERVAL 1 DAY)')->fetchColumn();
        $stats['daily_interactions'] = $recentComments + $recentReactions;
        $stats['events_hosted'] = (int) $pdo->query('SELECT COUNT(*) FROM matches WHERE kickoff_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)')->fetchColumn();
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to compute community stats: ' . $exception->getMessage());
    }

    return $stats;
}

function getFanSpotlight(): array
{
    return [
        [
            'title' => 'Curva Sud in festa',
            'caption' => 'Un mare di sciarpe contro il Milan. Passione che non dorme mai.',
            'image' => 'https://images.unsplash.com/photo-1489515217757-5fd1be406fef?auto=format&fit=crop&w=960&q=80',
            'credit' => '@bianconera_crew',
        ],
        [
            'title' => 'Trasferta europea',
            'caption' => 'I tifosi in viaggio verso l’Allianz Arena per sostenere la squadra.',
            'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=960&q=80',
            'credit' => '@juventusworld',
        ],
        [
            'title' => 'Generazioni bianconere',
            'caption' => 'Una famiglia allo stadio a tramandare l’amore per la Vecchia Signora.',
            'image' => 'https://images.unsplash.com/photo-1522778119026-d647f0596c20?auto=format&fit=crop&w=960&q=80',
            'credit' => '@famigliajuve',
        ],
    ];
}

// Register pages for the simple router
$availablePages = [
    'home' => __DIR__ . '/pages/home.php',
    'news' => __DIR__ . '/pages/news.php',
    'news_article' => __DIR__ . '/pages/news_article.php',
    'partite' => __DIR__ . '/pages/partite.php',
    'community' => __DIR__ . '/pages/community.php',
    'login' => __DIR__ . '/pages/login.php',
    'register' => __DIR__ . '/pages/register.php',
];

$pageTitles = [
    'home' => 'Home',
    'news' => 'Notizie',
    'news_article' => 'Notizia',
    'partite' => 'Partite',
    'community' => 'Community',
    'login' => 'Accedi',
    'register' => 'Registrati',
];

if (!isset($_SESSION['bh_csrf_token'])) {
    try {
        $_SESSION['bh_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Throwable $exception) {
        $_SESSION['bh_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

function getCsrfToken(): string
{
    if (empty($_SESSION['bh_csrf_token'])) {
        return regenerateCsrfToken();
    }

    return $_SESSION['bh_csrf_token'];
}

function regenerateCsrfToken(): string
{
    try {
        $_SESSION['bh_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Throwable $exception) {
        $_SESSION['bh_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }

    return $_SESSION['bh_csrf_token'];
}

function validateCsrfToken(?string $token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }

    $knownToken = $_SESSION['bh_csrf_token'] ?? '';

    return is_string($knownToken) && $knownToken !== '' && hash_equals($knownToken, $token);
}

/**
 * Resolve the human readable title for the requested page.
 */
function resolvePageTitle(string $pageKey, array $pageTitles, string $fallback): string
{
    return $pageTitles[$pageKey] ?? $fallback;
}

/**
 * Navigation entries shared across navbar variants.
 */
function getNavigationItems(): array
{
    return [
        'home' => ['label' => 'Home', 'icon' => 'M3 9.75 12 3l9 6.75V20a1 1 0 0 1-1 1h-5.25a.75.75 0 0 1-.75-.75v-4.5a.75.75 0 0 0-.75-.75H11.7a.75.75 0 0 0-.75.75v4.5a.75.75 0 0 1-.75.75H5a1 1 0 0 1-1-1z'],
        'news' => ['label' => 'News', 'icon' => 'M4.5 6.75h15M4.5 12h15m-15 5.25h9.75'],
        'community' => ['label' => 'Community', 'icon' => 'M9 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm12 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM4.5 15a4.5 4.5 0 0 1 9 0v3a.75.75 0 0 1-.75.75h-7.5A.75.75 0 0 1 4.5 18v-3Zm12 0a4.5 4.5 0 0 1 6 4.031v1.219a.75.75 0 0 1-.75.75h-7.5a.75.75 0 0 1-.75-.75V19.03A4.5 4.5 0 0 1 16.5 15Z'],
        'login' => ['label' => 'Profilo', 'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0 .75.75 0 0 1-.75.75h-13.5a.75.75 0 0 1-.75-.75Z'],
    ];
}

/**
 * Retrieve news entries (static seed for first iteration).
 */
function getNewsItems(): array
{
    syncNewsFeed();

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $statement = $pdo->query('SELECT id, title, slug, tag, excerpt, image_path, source_url, published_at FROM news ORDER BY COALESCE(published_at, created_at) DESC');
        $items = [];

        foreach ($statement as $row) {
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => $row['title'] ?? '',
                'slug' => $row['slug'] ?? '',
                'tag' => $row['tag'] ?? 'Juventus',
                'excerpt' => $row['excerpt'] ?? '',
                'image' => $row['image_path'] ?? '',
                'source_url' => $row['source_url'] ?? '',
                'published_at' => $row['published_at'] ?? null,
            ];
        }

        return $items;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load news items: ' . $exception->getMessage());
    }

    return [];
}

/**
 * Retrieve upcoming matches for the fixture list.
 */
function getUpcomingMatches(): array
{
    syncJuventusMatchesFromApi();

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $statement = $pdo->query('SELECT id, competition, opponent, venue, kickoff_at, status, broadcast FROM matches WHERE kickoff_at >= (NOW() - INTERVAL 1 DAY) ORDER BY kickoff_at ASC');
        $matches = [];

        foreach ($statement as $row) {
            $kickoff = isset($row['kickoff_at']) ? new DateTime($row['kickoff_at']) : new DateTime();
            $matches[] = [
                'id' => (int) ($row['id'] ?? 0),
                'competition' => $row['competition'] ?? '',
                'opponent' => $row['opponent'] ?? '',
                'venue' => $row['venue'] ?? '',
                'status' => $row['status'] ?? '',
                'broadcast' => $row['broadcast'] ?? '',
                'kickoff_at' => $kickoff,
                'date' => formatItalianDate($kickoff),
                'time' => $kickoff->format('H:i'),
            ];
        }

        return $matches;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load matches: ' . $exception->getMessage());
    }

    return [];
}

/**
 * Manage basic flash notifications stored in session.
 */
function setFlash(string $key, string $message, string $variant = 'info'): void
{
    $_SESSION['bh_flash'][$key] = [
        'message' => $message,
        'variant' => $variant,
    ];
}

function pullFlashMessages(): array
{
    $messages = $_SESSION['bh_flash'] ?? [];
    unset($_SESSION['bh_flash']);

    return $messages;
}

/**
 * Retrieve stored users from the session.
 */
function getRegisteredUsers(): array
{
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $statement = $pdo->query('SELECT id, username, email, badge, created_at FROM users ORDER BY created_at DESC');
        $users = [];

        foreach ($statement as $row) {
            $users[] = [
                'id' => (int) ($row['id'] ?? 0),
                'username' => $row['username'] ?? '',
                'email' => $row['email'] ?? '',
                'badge' => $row['badge'] ?? 'Tifoso',
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
            ];
        }

        return $users;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load registered users: ' . $exception->getMessage());
    }

    return [];
}

function findUserByUsername(string $username): ?array
{
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return null;
    }

    try {
        $statement = $pdo->prepare('SELECT id, username, email, password_hash, badge, created_at FROM users WHERE LOWER(username) = LOWER(:username) LIMIT 1');
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        if (!$user) {
            return null;
        }

        $user['id'] = (int) ($user['id'] ?? 0);
        $user['created_at'] = normalizeToTimestamp($user['created_at'] ?? time());

        return $user;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to find user by username: ' . $exception->getMessage());
    }

    return null;
}

function registerUser(array $payload): array
{
    $username = trim($payload['username'] ?? '');
    $email = trim($payload['email'] ?? '');
    $password = $payload['password'] ?? '';
    $passwordConfirmation = $payload['password_confirmation'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        return ['success' => false, 'message' => 'Compila tutti i campi richiesti.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Inserisci un indirizzo email valido.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'La password deve contenere almeno 6 caratteri.'];
    }

    if ($password !== $passwordConfirmation) {
        return ['success' => false, 'message' => 'Le password non coincidono.'];
    }

    if (findUserByUsername($username)) {
        return ['success' => false, 'message' => 'Questo username è già in uso.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio momentaneamente non disponibile. Riprova più tardi.'];
    }

    try {
        $pdo->beginTransaction();

        $duplicateCheck = $pdo->prepare('SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(:username) OR LOWER(email) = LOWER(:email)');
        $duplicateCheck->execute([
            'username' => $username,
            'email' => $email,
        ]);

        if ((int) $duplicateCheck->fetchColumn() > 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Username o email già in uso.'];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $insert = $pdo->prepare('INSERT INTO users (username, email, password_hash, badge) VALUES (:username, :email, :password_hash, :badge)');
        $insert->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'badge' => 'Nuovo tifoso',
        ]);

        $userId = (int) $pdo->lastInsertId();
        $pdo->commit();

        return [
            'success' => true,
            'user' => [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'badge' => 'Nuovo tifoso',
            ],
        ];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[BianconeriHub] Failed to register user: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Registrazione non riuscita. Riprova.'];
    }
}

function attemptLogin(string $username, string $password): array
{
    $user = findUserByUsername($username);

    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Credenziali non valide.'];
    }

    $_SESSION['bh_current_user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'badge' => $user['badge'] ?? 'Tifoso',
        'login_time' => time(),
    ];

    regenerateCsrfToken();

    return ['success' => true, 'user' => $_SESSION['bh_current_user']];
}

function logoutUser(): void
{
    unset($_SESSION['bh_current_user']);
    regenerateCsrfToken();
}

function isUserLoggedIn(): bool
{
    return isset($_SESSION['bh_current_user']);
}

function getLoggedInUser(): ?array
{
    return $_SESSION['bh_current_user'] ?? null;
}

function getCommunityPosts(): array
{
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return [];
    }

    $currentUser = getLoggedInUser();
    $currentUserId = isset($currentUser['id']) ? (int) $currentUser['id'] : 0;

    $sql = 'SELECT
                p.id,
                p.user_id,
                p.content,
                p.created_at,
                u.username,
                u.badge,
                (SELECT COUNT(*) FROM community_post_comments c WHERE c.post_id = p.id) AS comments_count,
                (SELECT COUNT(*) FROM community_post_reactions r WHERE r.post_id = p.id AND r.reaction_type = "like") AS likes_count,
                (SELECT COUNT(*) FROM community_post_reactions r WHERE r.post_id = p.id AND r.reaction_type = "support") AS supports_count';

    if ($currentUserId > 0) {
        $sql .= ',
                EXISTS(
                    SELECT 1 FROM community_post_reactions r1
                    WHERE r1.post_id = p.id AND r1.user_id = :current_user_like AND r1.reaction_type = "like"
                ) AS has_liked,
                EXISTS(
                    SELECT 1 FROM community_post_reactions r2
                    WHERE r2.post_id = p.id AND r2.user_id = :current_user_support AND r2.reaction_type = "support"
                ) AS has_supported';
    } else {
        $sql .= ', 0 AS has_liked, 0 AS has_supported';
    }

    $sql .= ' FROM community_posts p
              INNER JOIN users u ON u.id = p.user_id
              ORDER BY p.created_at DESC';

    try {
        $statement = $pdo->prepare($sql);
        if ($currentUserId > 0) {
            $statement->bindValue(':current_user_like', $currentUserId, PDO::PARAM_INT);
            $statement->bindValue(':current_user_support', $currentUserId, PDO::PARAM_INT);
        }
        $statement->execute();

        $posts = [];

        foreach ($statement as $row) {
            $posts[] = [
                'id' => (int) ($row['id'] ?? 0),
                'user_id' => (int) ($row['user_id'] ?? 0),
                'author' => $row['username'] ?? 'Tifoso',
                'badge' => $row['badge'] ?? 'Tifoso',
                'content' => $row['content'] ?? '',
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
                'likes_count' => (int) ($row['likes_count'] ?? 0),
                'supports_count' => (int) ($row['supports_count'] ?? 0),
                'comments_count' => (int) ($row['comments_count'] ?? 0),
                'has_liked' => ((int) ($row['has_liked'] ?? 0)) === 1,
                'has_supported' => ((int) ($row['has_supported'] ?? 0)) === 1,
            ];
        }

        return $posts;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load community posts: ' . $exception->getMessage());
    }

    return [];
}

function getCommunityPostMeta(int $postId): ?array
{
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return null;
    }

    try {
        $statement = $pdo->prepare('SELECT id, user_id FROM community_posts WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $postId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
        ];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load community post meta: ' . $exception->getMessage());
    }

    return null;
}

function toggleCommunityReaction(int $postId, int $userId, string $reactionType): array
{
    $reaction = strtolower(trim($reactionType));
    if (!in_array($reaction, ['like', 'support'], true)) {
        return ['success' => false, 'message' => 'Reazione non valida.'];
    }

    $postMeta = getCommunityPostMeta($postId);
    if (!$postMeta) {
        return ['success' => false, 'message' => 'Post non trovato.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio momentaneamente non disponibile.'];
    }

    try {
        $pdo->beginTransaction();

        $check = $pdo->prepare('SELECT 1 FROM community_post_reactions WHERE post_id = :post_id AND user_id = :user_id AND reaction_type = :reaction LIMIT 1');
        $check->execute([
            'post_id' => $postId,
            'user_id' => $userId,
            'reaction' => $reaction,
        ]);

        $alreadyReacted = (bool) $check->fetchColumn();

        if ($alreadyReacted) {
            $delete = $pdo->prepare('DELETE FROM community_post_reactions WHERE post_id = :post_id AND user_id = :user_id AND reaction_type = :reaction');
            $delete->execute([
                'post_id' => $postId,
                'user_id' => $userId,
                'reaction' => $reaction,
            ]);
            $pdo->commit();

            return ['success' => true, 'state' => 'removed'];
        }

        $insert = $pdo->prepare('INSERT INTO community_post_reactions (post_id, user_id, reaction_type) VALUES (:post_id, :user_id, :reaction)');
        $insert->execute([
            'post_id' => $postId,
            'user_id' => $userId,
            'reaction' => $reaction,
        ]);

        $pdo->commit();

        return ['success' => true, 'state' => 'added'];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[BianconeriHub] Failed to toggle community reaction: ' . $exception->getMessage());
    }

    return ['success' => false, 'message' => 'Impossibile aggiornare la reazione. Riprova.'];
}

function addCommunityComment(int $postId, int $userId, string $content): array
{
    $trimmed = trim($content);

    if ($trimmed === '') {
        return ['success' => false, 'message' => 'Il commento non può essere vuoto.'];
    }

    if (mb_strlen($trimmed) > 600) {
        return ['success' => false, 'message' => 'Il commento non può superare i 600 caratteri.'];
    }

    $postMeta = getCommunityPostMeta($postId);
    if (!$postMeta) {
        return ['success' => false, 'message' => 'Post non trovato.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio momentaneamente non disponibile.'];
    }

    try {
        $statement = $pdo->prepare('INSERT INTO community_post_comments (post_id, user_id, content) VALUES (:post_id, :user_id, :content)');
        $statement->execute([
            'post_id' => $postId,
            'user_id' => $userId,
            'content' => $trimmed,
        ]);

        return ['success' => true];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to add community comment: ' . $exception->getMessage());
    }

    return ['success' => false, 'message' => 'Impossibile pubblicare il commento al momento.'];
}

function getCommunityComments(int $postId, int $limit = 20): array
{
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return [];
    }

    $limit = max(1, min($limit, 50));

    try {
        $sql = 'SELECT c.id, c.content, c.created_at, u.username, u.badge
                FROM community_post_comments c
                INNER JOIN users u ON u.id = c.user_id
                WHERE c.post_id = :post_id
                ORDER BY c.created_at ASC
                LIMIT ' . $limit;
        $statement = $pdo->prepare($sql);
        $statement->bindValue(':post_id', $postId, PDO::PARAM_INT);
        $statement->execute();

        $comments = [];

        foreach ($statement as $row) {
            $comments[] = [
                'id' => (int) ($row['id'] ?? 0),
                'author' => $row['username'] ?? 'Tifoso',
                'badge' => $row['badge'] ?? 'Tifoso',
                'content' => $row['content'] ?? '',
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
            ];
        }

        return $comments;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load community comments: ' . $exception->getMessage());
    }

    return [];
}

function addCommunityPost(int $userId, string $content): array
{
    $trimmed = trim($content);

    if ($trimmed === '') {
        return ['success' => false, 'message' => 'Il messaggio non può essere vuoto.'];
    }

    if (mb_strlen($trimmed) > 500) {
        return ['success' => false, 'message' => 'Il messaggio non può superare i 500 caratteri.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio momentaneamente non disponibile.'];
    }

    try {
        $statement = $pdo->prepare('INSERT INTO community_posts (user_id, content) VALUES (:user_id, :content)');
        $statement->execute([
            'user_id' => $userId,
            'content' => $trimmed,
        ]);

        return ['success' => true];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to add community post: ' . $exception->getMessage());
    }

    return ['success' => false, 'message' => 'Impossibile pubblicare il messaggio al momento.'];
}

function findMatchById(int $matchId): ?array
{
    syncJuventusMatchesFromApi();

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return null;
    }

    try {
        $statement = $pdo->prepare('SELECT id, competition, opponent, venue, kickoff_at, status, broadcast FROM matches WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $matchId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        $kickoff = isset($row['kickoff_at']) ? new DateTime($row['kickoff_at']) : new DateTime();

        return [
            'id' => (int) ($row['id'] ?? 0),
            'competition' => $row['competition'] ?? '',
            'opponent' => $row['opponent'] ?? '',
            'venue' => $row['venue'] ?? '',
            'status' => $row['status'] ?? '',
            'broadcast' => $row['broadcast'] ?? '',
            'kickoff_at' => $kickoff,
            'date' => formatItalianDate($kickoff),
            'time' => $kickoff->format('H:i'),
        ];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to find match by id: ' . $exception->getMessage());
    }

    return null;
}

function findNewsItemBySlug(string $slug): ?array
{
    syncNewsFeed();

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return null;
    }

    try {
        $statement = $pdo->prepare('SELECT id, title, slug, tag, excerpt, body, image_path, source_url, published_at, created_at FROM news WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => $row['title'] ?? '',
            'slug' => $row['slug'] ?? '',
            'tag' => $row['tag'] ?? 'Juventus',
            'excerpt' => $row['excerpt'] ?? '',
            'body' => $row['body'] ?? '',
            'image' => $row['image_path'] ?? '',
            'source_url' => $row['source_url'] ?? '',
            'published_at' => $row['published_at'] ?? $row['created_at'] ?? null,
        ];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to find news item by slug: ' . $exception->getMessage());
    }

    return null;
}

function findNewsItemById(int $newsId): ?array
{
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return null;
    }

    try {
        $statement = $pdo->prepare('SELECT id, title, slug, tag, excerpt, body, image_path, source_url, published_at, created_at FROM news WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $newsId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => $row['title'] ?? '',
            'slug' => $row['slug'] ?? '',
            'tag' => $row['tag'] ?? 'Juventus',
            'excerpt' => $row['excerpt'] ?? '',
            'body' => $row['body'] ?? '',
            'image' => $row['image_path'] ?? '',
            'source_url' => $row['source_url'] ?? '',
            'published_at' => $row['published_at'] ?? $row['created_at'] ?? null,
        ];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to find news item by id: ' . $exception->getMessage());
    }

    return null;
}

function getNewsComments(int $newsId): array
{
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $statement = $pdo->prepare('SELECT c.id, c.content, c.created_at, u.username, u.badge FROM news_comments c INNER JOIN users u ON u.id = c.user_id WHERE c.news_id = :news_id ORDER BY c.created_at DESC');
        $statement->execute(['news_id' => $newsId]);

        $comments = [];
        foreach ($statement as $row) {
            $comments[] = [
                'id' => (int) ($row['id'] ?? 0),
                'content' => $row['content'] ?? '',
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
                'username' => $row['username'] ?? 'Tifoso',
                'badge' => $row['badge'] ?? 'Tifoso',
            ];
        }

        return $comments;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load news comments: ' . $exception->getMessage());
    }

    return [];
}

function addNewsComment(int $newsId, int $userId, string $content): array
{
    $trimmed = trim($content);

    if ($trimmed === '') {
        return ['success' => false, 'message' => 'Il commento non può essere vuoto.'];
    }

    if (mb_strlen($trimmed) > 800) {
        return ['success' => false, 'message' => 'Il commento non può superare gli 800 caratteri.'];
    }

    $news = findNewsItemById($newsId);
    if (!$news) {
        return ['success' => false, 'message' => 'Notizia non trovata.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio momentaneamente non disponibile.'];
    }

    try {
        $statement = $pdo->prepare('INSERT INTO news_comments (news_id, user_id, content) VALUES (:news_id, :user_id, :content)');
        $statement->execute([
            'news_id' => $newsId,
            'user_id' => $userId,
            'content' => $trimmed,
        ]);

        return ['success' => true];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to add news comment: ' . $exception->getMessage());
    }

    return ['success' => false, 'message' => 'Impossibile pubblicare il commento.'];
}

function toggleNewsLike(int $newsId, int $userId): array
{
    $news = findNewsItemById($newsId);
    if (!$news) {
        return ['success' => false, 'liked' => false, 'message' => 'Notizia non trovata.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'liked' => false, 'message' => 'Servizio momentaneamente non disponibile.'];
    }

    try {
        $insert = $pdo->prepare('INSERT INTO news_likes (news_id, user_id) VALUES (:news_id, :user_id)');
        $insert->execute([
            'news_id' => $newsId,
            'user_id' => $userId,
        ]);

        return ['success' => true, 'liked' => true];
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() === '23000') {
            try {
                $delete = $pdo->prepare('DELETE FROM news_likes WHERE news_id = :news_id AND user_id = :user_id');
                $delete->execute([
                    'news_id' => $newsId,
                    'user_id' => $userId,
                ]);

                return ['success' => true, 'liked' => false];
            } catch (PDOException $innerException) {
                error_log('[BianconeriHub] Failed to toggle news like (delete): ' . $innerException->getMessage());
                return ['success' => false, 'liked' => false, 'message' => 'Impossibile aggiornare il like.'];
            }
        }

        error_log('[BianconeriHub] Failed to toggle news like: ' . $exception->getMessage());
    }

    return ['success' => false, 'liked' => false, 'message' => 'Impossibile aggiornare il like.'];
}

function getNewsEngagementSummary(array $newsIds, ?int $userId = null): array
{
    $filteredIds = [];
    foreach ($newsIds as $id) {
        $intId = (int) $id;
        if ($intId > 0) {
            $filteredIds[$intId] = $intId;
        }
    }

    if (empty($filteredIds)) {
        return [];
    }

    $ids = array_values($filteredIds);

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        $summary = [];
        foreach ($ids as $id) {
            $summary[$id] = ['comments' => 0, 'likes' => 0, 'liked' => false];
        }

        return $summary;
    }

    $summary = [];
    foreach ($ids as $id) {
        $summary[$id] = ['comments' => 0, 'likes' => 0, 'liked' => false];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        $commentsQuery = $pdo->prepare('SELECT news_id, COUNT(*) AS total FROM news_comments WHERE news_id IN (' . $placeholders . ') GROUP BY news_id');
        $commentsQuery->execute($ids);
        while ($row = $commentsQuery->fetch()) {
            $newsId = (int) ($row['news_id'] ?? 0);
            if (isset($summary[$newsId])) {
                $summary[$newsId]['comments'] = (int) ($row['total'] ?? 0);
            }
        }

        $likesQuery = $pdo->prepare('SELECT news_id, COUNT(*) AS total FROM news_likes WHERE news_id IN (' . $placeholders . ') GROUP BY news_id');
        $likesQuery->execute($ids);
        while ($row = $likesQuery->fetch()) {
            $newsId = (int) ($row['news_id'] ?? 0);
            if (isset($summary[$newsId])) {
                $summary[$newsId]['likes'] = (int) ($row['total'] ?? 0);
            }
        }

        if ($userId) {
            $userParams = array_merge([$userId], $ids);
            $userPlaceholders = implode(',', array_fill(0, count($ids), '?'));
            $userLikesQuery = $pdo->prepare('SELECT news_id FROM news_likes WHERE user_id = ? AND news_id IN (' . $userPlaceholders . ')');
            $userLikesQuery->execute($userParams);
            while ($row = $userLikesQuery->fetch()) {
                $newsId = (int) ($row['news_id'] ?? 0);
                if (isset($summary[$newsId])) {
                    $summary[$newsId]['liked'] = true;
                }
            }
        }
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load news engagement summary: ' . $exception->getMessage());
    }

    return $summary;
}

function getHumanTimeDiff($timestamp): string
{
    $diff = time() - normalizeToTimestamp($timestamp);

    if ($diff < 60) {
        return 'Pochi secondi fa';
    }

    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes === 1 ? '1 minuto fa' : $minutes . ' minuti fa';
    }

    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours === 1 ? '1 ora fa' : $hours . ' ore fa';
    }

    $days = floor($diff / 86400);
    return $days === 1 ? '1 giorno fa' : $days . ' giorni fa';
}

function getTimeUntil(DateTimeInterface $futureDate): string
{
    $now = new DateTime();
    if ($futureDate <= $now) {
        return 'In corso o conclusa';
    }

    $interval = $now->diff($futureDate);

    if ($interval->days >= 1) {
        return $interval->days === 1
            ? 'Manca 1 giorno'
            : 'Mancano ' . $interval->days . ' giorni';
    }

    if ($interval->h >= 1) {
        return $interval->h === 1
            ? 'Manca 1 ora'
            : 'Mancano ' . $interval->h . ' ore';
    }

    if ($interval->i >= 1) {
        return $interval->i === 1
            ? 'Manca 1 minuto'
            : 'Mancano ' . $interval->i . ' minuti';
    }

    return 'Mancano pochi secondi';
}

function storeOldInput(array $data): void
{
    $_SESSION['bh_old_input'] = $data;
}

function getOldInput(?string $key = null, $default = '')
{
    $old = $_SESSION['bh_old_input'] ?? [];

    if ($key === null) {
        return $old;
    }

    return $old[$key] ?? $default;
}

function clearOldInput(): void
{
    unset($_SESSION['bh_old_input']);
}

function forgetOldInput(array $keys): void
{
    if (empty($_SESSION['bh_old_input']) || empty($keys)) {
        return;
    }

    foreach ($keys as $key) {
        unset($_SESSION['bh_old_input'][$key]);
    }

    if (empty($_SESSION['bh_old_input'])) {
        unset($_SESSION['bh_old_input']);
    }
}
