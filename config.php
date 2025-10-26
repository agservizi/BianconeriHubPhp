<?php
// Basic application configuration for BianconeriHub

$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (is_file($autoloadFile)) {
    require_once $autoloadFile;
}

if (!function_exists('loadEnvFile')) {
    function loadEnvFile(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || $trimmed[0] === '#') {
                continue;
            }

            if (strpos($trimmed, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $trimmed, 2);
            $key = trim($key);

            if ($key === '') {
                continue;
            }

            $value = trim($value);
            if ($value !== '') {
                $quote = $value[0];
                if (($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
                    $value = substr($value, 1, -1);
                }
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

$envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
if (is_file($envPath)) {
    loadEnvFile($envPath);
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

$appName = env('APP_NAME', 'BianconeriHub');
$appTagline = env('APP_TAGLINE', 'Il cuore pulsante dei tifosi juventini');
$appTimezone = env('APP_TIMEZONE', 'Europe/Rome');
$appDebug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);

if (is_string($appTimezone) && $appTimezone !== '') {
    @date_default_timezone_set($appTimezone);
}

$sessionName = env('SESSION_NAME', 'bianconerihub_session');
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (is_string($sessionName) && $sessionName !== '') {
        session_name($sessionName);
    }

    $cookieParams = session_get_cookie_params();
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path' => $cookieParams['path'] ?: '/',
        'domain' => $cookieParams['domain'],
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $secure ? 'None' : 'Lax',
    ]);

    session_start();
}

function getDatabaseConfig(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $config = [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', 3306),
        'database' => env('DB_NAME', 'bianconerihub'),
        'username' => env('DB_USER', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ];

    return $config;
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

function getApplicationBaseUrl(): string
{
    static $cached;

    if ($cached !== null) {
        return $cached;
    }

    $base = trim((string) env('BASE_URL', ''));

    if ($base === '') {
        $httpsFlag = $_SERVER['HTTPS'] ?? '';
        $isSecure = is_string($httpsFlag) && strtolower($httpsFlag) !== 'off' && $httpsFlag !== '';
        $scheme = $isSecure ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

        if (strpos($host, ':') === false) {
            $port = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 0;
            if ($port > 0 && !in_array($port, [80, 443], true)) {
                $host .= ':' . $port;
            }
        }

        $base = $scheme . '://' . $host;
    }

    $cached = rtrim($base, '/');

    return $cached;
}

function appUrl(string $path = ''): string
{
    $base = getApplicationBaseUrl();
    $normalized = ltrim($path);

    if ($normalized === '') {
        return $base;
    }

    if (stripos($normalized, 'http://') === 0 || stripos($normalized, 'https://') === 0) {
        return $normalized;
    }

    $firstChar = $normalized[0];

    if ($firstChar === '?') {
        return $base . '/index.php' . $normalized;
    }

    if ($firstChar === '/') {
        return $base . $normalized;
    }

    return $base . '/' . ltrim($normalized, '/');
}

function getMailFromAddress(): string
{
    $address = trim((string) env('MAIL_FROM_ADDRESS', ''));

    if ($address === '') {
        $address = 'no-reply@bianconerihub.com';
    }

    return $address;
}

function getMailFromName(): string
{
    $name = trim((string) env('MAIL_FROM_NAME', 'BianconeriHub'));

    return $name !== '' ? $name : 'BianconeriHub';
}

function getResendClient()
{
    static $initialised = false;
    static $client = null;

    if ($initialised) {
        return $client;
    }

    $initialised = true;

    $apiKey = trim((string) env('RESEND_API_KEY', ''));

    if ($apiKey === '' || !class_exists('Resend')) {
        return $client;
    }

    try {
        $client = call_user_func(['Resend', 'client'], $apiKey);
    } catch (Throwable $exception) {
        error_log('[BianconeriHub] Unable to initialise Resend client: ' . $exception->getMessage());
        $client = null;
    }

    return $client;
}

function renderEmailLayout(array $data): string
{
    $title = (string) ($data['title'] ?? '');
    $preheader = (string) ($data['preheader'] ?? '');

    $paragraphs = $data['paragraphs'] ?? [];
    if (!is_array($paragraphs)) {
        $paragraphs = [$paragraphs];
    }

    $cta = $data['cta'] ?? null;
    $ctaLabel = '';
    $ctaUrl = '';
    if (is_array($cta)) {
        $ctaLabel = trim((string) ($cta['label'] ?? ''));
        $ctaUrl = trim((string) ($cta['url'] ?? ''));
    }

    $footerLines = $data['footer'] ?? [];
    if (!is_array($footerLines)) {
        $footerLines = [$footerLines];
    }
    if (empty($footerLines)) {
        $footerLines = [
            'Se non hai richiesto questa email puoi ignorarla in sicurezza.',
            '© ' . date('Y') . ' BianconeriHub. Tutti i diritti riservati.',
        ];
    }

    $paragraphHtml = '';
    foreach ($paragraphs as $paragraph) {
        $paragraphText = trim((string) $paragraph);
        if ($paragraphText === '') {
            continue;
        }
        $paragraphHtml .= '<p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#e5e7eb;">' . htmlspecialchars($paragraphText, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $ctaHtml = '';
    if ($ctaLabel !== '' && $ctaUrl !== '') {
        $ctaHtml = '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0"><tr><td><a href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block; padding:14px 28px; background-color:#ffffff; color:#000000; font-weight:600; border-radius:9999px; text-decoration:none;">' . htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') . '</a></td></tr></table>';
    }

    $footerHtml = '';
    foreach ($footerLines as $line) {
        $lineText = trim((string) $line);
        if ($lineText === '') {
            continue;
        }
        $footerHtml .= '<p style="margin:0 0 8px; font-size:12px; line-height:1.6; color:#9ca3af;">' . htmlspecialchars($lineText, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $titleEscaped = htmlspecialchars($title !== '' ? $title : 'BianconeriHub', ENT_QUOTES, 'UTF-8');
    $preheaderBlock = $preheader !== ''
        ? '<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; line-height:0;">' . htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8') . '</div>'
        : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$titleEscaped}</title>
</head>
<body style="margin:0; padding:0; background-color:#080808; color:#f9fafb; font-family:'Inter',Arial,sans-serif;">
    {$preheaderBlock}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#080808; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:90%; background-color:#111827; border-radius:24px; overflow:hidden; border:1px solid rgba(255,255,255,0.08);">
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 16px; font-size:24px; font-weight:700; color:#ffffff; letter-spacing:0.02em;">{$titleEscaped}</h1>
                            {$paragraphHtml}
                            {$ctaHtml}
                            <p style="margin:24px 0 0; font-size:15px; line-height:1.6; color:#e5e7eb;">Forza Juventus,<br><strong style="color:#ffffff;">Il team di BianconeriHub</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px; background-color:#0b0f19;">
                            {$footerHtml}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

function buildEmailPlainText(string $title, array $paragraphs, ?string $ctaLabel, ?string $ctaUrl, array $footerLines = []): string
{
    $lines = [];
    $normalizedTitle = trim($title);

    if ($normalizedTitle !== '') {
        $lines[] = $normalizedTitle;
        $lines[] = str_repeat('=', mb_strlen($normalizedTitle));
        $lines[] = '';
    }

    foreach ($paragraphs as $paragraph) {
        $paragraphText = trim((string) $paragraph);
        if ($paragraphText === '') {
            continue;
        }
        $lines[] = $paragraphText;
        $lines[] = '';
    }

    $ctaLabelText = $ctaLabel !== null ? trim($ctaLabel) : '';
    $ctaUrlText = $ctaUrl !== null ? trim($ctaUrl) : '';

    if ($ctaLabelText !== '' && $ctaUrlText !== '') {
        $lines[] = $ctaLabelText . ': ' . $ctaUrlText;
        $lines[] = '';
    }

    $lines[] = 'Forza Juventus!';
    $lines[] = 'Il team di BianconeriHub';

    $filteredFooter = [];
    foreach ($footerLines as $line) {
        $lineText = trim((string) $line);
        if ($lineText !== '') {
            $filteredFooter[] = $lineText;
        }
    }

    if (!empty($filteredFooter)) {
        $lines[] = '';
        foreach ($filteredFooter as $line) {
            $lines[] = $line;
        }
    }

    return trim(implode("\n", $lines));
}

function sendEmailMessage(string $toEmail, string $subject, string $htmlBody, string $textBody = '', ?string $toName = null): bool
{
    $client = getResendClient();
    if (!$client) {
        return false;
    }

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $fromEmail = getMailFromAddress();
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $fromName = getMailFromName();
    $from = $fromName !== '' ? sprintf('%s <%s>', $fromName, $fromEmail) : $fromEmail;
    $recipient = ($toName !== null && trim($toName) !== '') ? sprintf('%s <%s>', trim($toName), $toEmail) : $toEmail;

    $payload = [
        'from' => $from,
        'to' => [$recipient],
        'subject' => $subject,
        'html' => $htmlBody,
    ];

    if ($textBody !== '') {
        $payload['text'] = $textBody;
    }

    try {
        $client->emails->send($payload);
        return true;
    } catch (Throwable $exception) {
        error_log('[BianconeriHub] Unable to send email via Resend: ' . $exception->getMessage());
    }

    return false;
}

function sendWelcomeEmail(array $user): void
{
    $email = trim((string) ($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $displayName = buildUserDisplayName($user['first_name'] ?? null, $user['last_name'] ?? null, (string) ($user['username'] ?? ''));

    $subject = 'Benvenuto su BianconeriHub';
    $ctaUrl = appUrl('?page=community');

    $paragraphs = [
        'Ciao ' . $displayName . ',',
        'Benvenuto a bordo di BianconeriHub! Da oggi hai accesso alla nostra curva digitale con discussioni live, statistiche aggiornate e contenuti esclusivi dedicati ai tifosi juventini.',
        'Personalizza il tuo profilo, ottieni badge speciali e inizia subito a interagire con la community bianconera.',
    ];

    $footerLines = [
        'Ricevi questa email perché hai appena creato un account su BianconeriHub.',
        '© ' . date('Y') . ' BianconeriHub. Tutti i diritti riservati.',
    ];

    $html = renderEmailLayout([
        'title' => $subject,
        'preheader' => 'Hai ufficialmente un posto nella nostra curva digitale.',
        'paragraphs' => $paragraphs,
        'cta' => [
            'label' => 'Entra nella community',
            'url' => $ctaUrl,
        ],
        'footer' => $footerLines,
    ]);

    $text = buildEmailPlainText($subject, $paragraphs, 'Entra nella community', $ctaUrl, $footerLines);

    sendEmailMessage($email, $subject, $html, $text, $displayName);
}

function notifyAdminNewRegistration(array $user): void
{
    $adminEmail = 'ag.servizi16@gmail.com';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $username = trim((string) ($user['username'] ?? ''));
    $email = trim((string) ($user['email'] ?? ''));
    $displayName = buildUserDisplayName($user['first_name'] ?? null, $user['last_name'] ?? null, $username);
    try {
        $registeredAt = (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('d/m/Y H:i');
    } catch (Throwable $exception) {
        $registeredAt = date('d/m/Y H:i');
    }

    $subject = $username !== ''
        ? 'Nuova registrazione: ' . $username
        : 'Nuova registrazione su BianconeriHub';

    $paragraphs = [
        'Ciao,',
        'Un nuovo tifoso ha appena completato la registrazione su BianconeriHub.',
        'Nome visualizzato: ' . ($displayName !== '' ? $displayName : 'Non specificato'),
        'Username: ' . ($username !== '' ? $username : 'Non specificato'),
        'Email: ' . ($email !== '' ? $email : 'Non specificata'),
        'Registrato il: ' . $registeredAt,
    ];

    $footerLines = [
        '© ' . date('Y') . ' BianconeriHub. Monitoraggio registrazioni automatico.',
    ];

    $html = renderEmailLayout([
        'title' => 'Nuovo utente iscritto',
        'preheader' => 'Un nuovo tifoso ha aderito alla community.',
        'paragraphs' => $paragraphs,
        'footer' => $footerLines,
    ]);

    $text = buildEmailPlainText('Nuovo utente iscritto', $paragraphs, null, null, $footerLines);

    sendEmailMessage($adminEmail, $subject, $html, $text, 'AG Servizi');
}

function sendPasswordResetEmail(array $user, string $token, DateTimeInterface $expiresAt): bool
{
    $email = trim((string) ($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $displayName = buildUserDisplayName($user['first_name'] ?? null, $user['last_name'] ?? null, (string) ($user['username'] ?? ''));
    $subject = 'Reimposta la tua password BianconeriHub';

    $resetUrl = appUrl('?page=password_reset&token=' . urlencode($token));
    $expiresLabel = $expiresAt->format('d/m/Y H:i');

    $paragraphs = [
        'Ciao ' . $displayName . ',',
        'Abbiamo ricevuto la tua richiesta di reimpostare la password per accedere a BianconeriHub.',
        'Il link resterà valido per 60 minuti (fino alle ' . $expiresLabel . '). Usalo solo se hai avviato tu la procedura.',
        'Se non hai richiesto tu il reset, puoi ignorare in sicurezza questo messaggio.',
    ];

    $footerLines = [
        'Se il pulsante non dovesse funzionare, copia e incolla questo link nel tuo browser: ' . $resetUrl,
        '© ' . date('Y') . ' BianconeriHub. Tutti i diritti riservati.',
    ];

    $html = renderEmailLayout([
        'title' => $subject,
        'preheader' => 'Usa il link per impostare una nuova password entro i prossimi 60 minuti.',
        'paragraphs' => $paragraphs,
        'cta' => [
            'label' => 'Reimposta password',
            'url' => $resetUrl,
        ],
        'footer' => $footerLines,
    ]);

    $text = buildEmailPlainText($subject, $paragraphs, 'Reimposta password', $resetUrl, $footerLines);

    return sendEmailMessage($email, $subject, $html, $text, $displayName);
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

    static $lengthFn = null;
    static $substrFn = null;

    if ($lengthFn === null) {
        $lengthFn = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        $substrFn = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    }

    if ($lengthFn($text) > $maxLength) {
        $slice = $substrFn($text, 0, $maxLength - 1);
        $text = rtrim($slice) . '…';
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
    $decoded = preg_replace('/<br\s*\/??\s*>/i', "\n", $decoded);
    $decoded = preg_replace('/<\/p>/i', "</p>\n", $decoded);
    $text = strip_tags($decoded);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    $text = trim($text);

    static $lengthFn = null;
    static $substrFn = null;

    if ($lengthFn === null) {
        $lengthFn = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        $substrFn = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    }

    if ($lengthFn($text) > 5000) {
        $slice = $substrFn($text, 0, 5000);
        $text = rtrim($slice) . '…';
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

        $homeTeamCheck = $pdo->query("SHOW COLUMNS FROM matches LIKE 'home_team'");
        if ($homeTeamCheck && $homeTeamCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE matches ADD COLUMN home_team VARCHAR(120) DEFAULT NULL AFTER opponent");
        }

        $awayTeamCheck = $pdo->query("SHOW COLUMNS FROM matches LIKE 'away_team'");
        if ($awayTeamCheck && $awayTeamCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE matches ADD COLUMN away_team VARCHAR(120) DEFAULT NULL AFTER home_team");
        }

        $juventusHomeCheck = $pdo->query("SHOW COLUMNS FROM matches LIKE 'juventus_is_home'");
        if ($juventusHomeCheck && $juventusHomeCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE matches ADD COLUMN juventus_is_home TINYINT(1) DEFAULT NULL AFTER away_team");
        }

        $statusCodeCheck = $pdo->query("SHOW COLUMNS FROM matches LIKE 'status_code'");
        if ($statusCodeCheck && $statusCodeCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE matches ADD COLUMN status_code VARCHAR(40) DEFAULT NULL AFTER status");
        }

        $homeScoreCheck = $pdo->query("SHOW COLUMNS FROM matches LIKE 'home_score'");
        if ($homeScoreCheck && $homeScoreCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE matches ADD COLUMN home_score TINYINT UNSIGNED DEFAULT NULL AFTER status_code");
        }

        $awayScoreCheck = $pdo->query("SHOW COLUMNS FROM matches LIKE 'away_score'");
        if ($awayScoreCheck && $awayScoreCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE matches ADD COLUMN away_score TINYINT UNSIGNED DEFAULT NULL AFTER home_score");
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

function extractMatchScores(?array $score): array
{
    if (!is_array($score)) {
        return [null, null];
    }

    $sections = [
        $score['fullTime'] ?? null,
        $score['regularTime'] ?? null,
        $score['halfTime'] ?? null,
    ];

    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }

        $home = $section['home'] ?? $section['homeTeam'] ?? null;
        $away = $section['away'] ?? $section['awayTeam'] ?? null;

        if (is_numeric($home) && is_numeric($away)) {
            return [(int) $home, (int) $away];
        }
    }

    $home = $score['fullTime']['homeTeam'] ?? $score['fullTime']['home'] ?? null;
    $away = $score['fullTime']['awayTeam'] ?? $score['fullTime']['away'] ?? null;

    return [
        is_numeric($home) ? (int) $home : null,
        is_numeric($away) ? (int) $away : null,
    ];
}

function estimateMatchMinuteLabel(?DateTimeInterface $kickoff): ?string
{
    if (!$kickoff instanceof DateTimeInterface) {
        return null;
    }

    $now = new DateTime();
    $secondsDiff = $now->getTimestamp() - $kickoff->getTimestamp();
    if ($secondsDiff < 0) {
        return null;
    }

    $minutes = (int) floor($secondsDiff / 60);

    if ($minutes < 45) {
        return (string) max(1, $minutes + 1);
    }

    if ($minutes < 60) {
        return '45+';
    }

    if ($minutes < 105) {
        $adjusted = $minutes - 15;
        $minute = max(46, $adjusted + 1);
        return $minute >= 90 ? '90+' : (string) $minute;
    }

    if ($minutes < 120) {
        return '90+';
    }

    return '90+';
}

function buildMatchLiveMetadata(array $match): array
{
    $statusCode = strtoupper((string) ($match['status_code'] ?? ''));
    if ($statusCode === '') {
        $statusCode = 'SCHEDULED';
    }

    $kickoff = $match['kickoff_at'] ?? null;
    if (!$kickoff instanceof DateTimeInterface && isset($match['kickoff_at']) && $match['kickoff_at'] instanceof DateTimeInterface) {
        $kickoff = $match['kickoff_at'];
    }

    $homeScore = $match['home_score'] ?? null;
    $awayScore = $match['away_score'] ?? null;

    $homeScore = is_numeric($homeScore) ? (int) $homeScore : null;
    $awayScore = is_numeric($awayScore) ? (int) $awayScore : null;

    $isLive = $statusCode === 'IN_PLAY';
    $isPaused = $statusCode === 'PAUSED';
    $isFinished = $statusCode === 'FINISHED';
    $isPostponed = $statusCode === 'POSTPONED';

    $minuteLabel = null;
    if (($isLive || $isPaused) && $kickoff instanceof DateTimeInterface) {
        $minuteLabel = estimateMatchMinuteLabel($kickoff);
    }

    return [
        'status_code' => $statusCode,
        'status_label' => $match['status'] ?? '',
        'home_score' => $homeScore,
        'away_score' => $awayScore,
        'is_live' => $isLive,
        'is_paused' => $isPaused,
        'is_finished' => $isFinished,
        'is_postponed' => $isPostponed,
        'minute_label' => $minuteLabel,
    ];
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

        [$homeScore, $awayScore] = extractMatchScores($match['score'] ?? null);
        $statusRaw = strtoupper((string) ($match['status'] ?? ''));
        $statusLabel = mapFootballDataStatus($match['status'] ?? null, $match['score'] ?? null);

        $matchesToPersist[] = [
            'external_id' => (string) $match['id'],
            'competition' => $match['competition']['name'] ?? 'Juventus',
            'opponent' => $opponent,
            'home_team' => $homeTeamName !== '' ? $homeTeamName : ($isHome ? 'Juventus' : $opponent),
            'away_team' => $awayTeamName !== '' ? $awayTeamName : ($isHome ? $opponent : 'Juventus'),
            'juventus_is_home' => $isHome ? 1 : 0,
            'venue' => $venue,
            'kickoff_at' => $kickoffUtc->format('Y-m-d H:i:s'),
            'status' => $statusLabel,
            'status_code' => $statusRaw !== '' ? $statusRaw : 'SCHEDULED',
            'home_score' => $homeScore,
            'away_score' => $awayScore,
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
        ensureUserConsentsTable($pdo);

        $pdo->beginTransaction();

        $delete = $pdo->prepare('DELETE FROM matches WHERE source = :source AND kickoff_at >= (NOW() - INTERVAL 30 DAY)');
        $delete->execute(['source' => 'football-data']);

        $insert = $pdo->prepare('INSERT INTO matches (external_id, source, competition, opponent, home_team, away_team, juventus_is_home, venue, kickoff_at, status, status_code, home_score, away_score, broadcast) VALUES (:external_id, :source, :competition, :opponent, :home_team, :away_team, :juventus_is_home, :venue, :kickoff_at, :status, :status_code, :home_score, :away_score, :broadcast) ON DUPLICATE KEY UPDATE competition = VALUES(competition), opponent = VALUES(opponent), home_team = VALUES(home_team), away_team = VALUES(away_team), juventus_is_home = VALUES(juventus_is_home), venue = VALUES(venue), kickoff_at = VALUES(kickoff_at), status = VALUES(status), status_code = VALUES(status_code), home_score = VALUES(home_score), away_score = VALUES(away_score), broadcast = VALUES(broadcast), updated_at = CURRENT_TIMESTAMP');

        foreach ($matchesToPersist as $row) {
            $insert->execute([
                'external_id' => $row['external_id'],
                'source' => 'football-data',
                'competition' => $row['competition'],
                'opponent' => $row['opponent'],
                'home_team' => $row['home_team'],
                'away_team' => $row['away_team'],
                'juventus_is_home' => $row['juventus_is_home'],
                'venue' => $row['venue'],
                'kickoff_at' => $row['kickoff_at'],
                'status' => $row['status'],
                'status_code' => $row['status_code'],
                'home_score' => $row['home_score'],
                'away_score' => $row['away_score'],
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
    $sessionActive = session_status() === PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli' && !defined('BH_NEWS_SYNC_SCRIPT');
    $sessionTimestamp = ($sessionActive && isset($_SESSION[$sessionKey])) ? (int) $_SESSION[$sessionKey] : 0;

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
                    if ($sessionActive) {
                        $_SESSION[$sessionKey] = $dbTimestamp;
                    }
                }
            } catch (PDOException $exception) {
                error_log('[BianconeriHub] Unable to read news sync timestamp: ' . $exception->getMessage());
            }
        }
    }

    if (!$force && $lastSyncTimestamp !== null && $lastSyncTimestamp !== 0 && (time() - $lastSyncTimestamp) < 900) {
        return;
    }

    if (!function_exists('simplexml_load_string')) {
        error_log('[BianconeriHub] SimpleXML extension not available; news sync skipped.');
        return;
    }

    $feedUrl = env('NEWS_FEED_URL', 'https://www.tuttojuve.com/rss');
    if (!is_string($feedUrl) || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
        error_log('[BianconeriHub] Invalid NEWS_FEED_URL configuration.');
        return;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 6,
            'user_agent' => 'BianconeriHubBot/1.0 (+https://bianconerihub.local)',
        ],
    ]);

    if (function_exists('error_clear_last')) {
        error_clear_last();
    }

    $feedContent = @file_get_contents($feedUrl, false, $context);
    if ($feedContent === false) {
        $lastError = error_get_last();
        $detail = $lastError && isset($lastError['message']) ? $lastError['message'] : 'unknown error';
        error_log('[BianconeriHub] Unable to download news feed: ' . $detail);
        return;
    }

    libxml_use_internal_errors(true);
    $lengthFn = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
    $substrFn = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    $xml = simplexml_load_string($feedContent, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml || !isset($xml->channel->item)) {
        $errors = libxml_get_errors();
        if (!empty($errors)) {
            $firstError = reset($errors);
            $message = is_object($firstError) && property_exists($firstError, 'message')
                ? trim((string) $firstError->message)
                : 'invalid XML structure';
            error_log('[BianconeriHub] Unable to parse news feed XML: ' . $message);
        } else {
            error_log('[BianconeriHub] Unable to parse news feed XML: invalid structure.');
        }
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
        if ($lengthFn($category) > 40) {
            $category = $substrFn($category, 0, 40);
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
    if ($sessionActive) {
        $_SESSION[$sessionKey] = $lastSyncTimestamp;
    }
}

function triggerPeriodicNewsSync(): void
{
    static $checked = false;

    if ($checked || defined('BH_NEWS_SYNC_SCRIPT')) {
        return;
    }

    $checked = true;

    $cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }

    if (!is_dir($cacheDir)) {
        return;
    }

    $timestampFile = $cacheDir . DIRECTORY_SEPARATOR . 'news_sync_last_run.txt';
    $lockFile = $cacheDir . DIRECTORY_SEPARATOR . 'news_sync.lock';

    $now = time();
    $lastRun = is_file($timestampFile) ? (int) trim((string) @file_get_contents($timestampFile)) : 0;
    if ($lastRun > 0 && ($now - $lastRun) < 1800) {
        return;
    }

    $lockHandle = @fopen($lockFile, 'c+');
    if (!$lockHandle) {
        return;
    }

    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        fclose($lockHandle);
        return;
    }

    $lastRun = is_file($timestampFile) ? (int) trim((string) @file_get_contents($timestampFile)) : 0;
    if ($lastRun > 0 && ($now - $lastRun) < 1800) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        return;
    }

    @file_put_contents($timestampFile, (string) $now, LOCK_EX);

    $phpBinary = PHP_BINARY ?: 'php';
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'sync_news.php';

    $commandExecuted = false;

    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        $escapedPhp = '"' . str_replace('"', '\"', $phpBinary) . '"';
        $escapedScript = '"' . str_replace('"', '\"', $scriptPath) . '"';
        $command = 'start /B "" ' . $escapedPhp . ' ' . $escapedScript;
        $process = @popen($command, 'r');
        if ($process !== false) {
            pclose($process);
            $commandExecuted = true;
        }
    } else {
        $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($scriptPath) . ' > /dev/null 2>&1 &';
        @exec($command);
        $commandExecuted = true;
    }

    if (!$commandExecuted) {
        try {
            syncNewsFeed(true);
        } catch (Throwable $exception) {
            error_log('[BianconeriHub] Fallback news sync failed: ' . $exception->getMessage());
        }
    }

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
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

function getCommunityStories(int $limit = 8): array
{
    $limit = max(1, min($limit, 20));

    $pdo = getDatabaseConnection();
    if (!$pdo || !communityPostsExtendedSchemaAvailable($pdo)) {
        return [];
    }

    try {
        $statement = $pdo->prepare(
            'SELECT p.id, p.user_id, p.story_title, p.story_caption, p.story_credit, p.media_url, p.created_at, p.published_at, u.username, u.badge
             FROM community_posts p
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.content_type = "story" AND (p.status = "published" OR p.status IS NULL OR p.status = "")
             ORDER BY p.published_at DESC, p.created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load community stories: ' . $exception->getMessage());
        return [];
    }

    if (empty($rows)) {
        return [];
    }

    $storyIds = [];
    foreach ($rows as $row) {
        $storyId = (int) ($row['id'] ?? 0);
        if ($storyId > 0) {
            $storyIds[] = $storyId;
        }
    }

    $mediaMap = !empty($storyIds) ? getCommunityPostMedia($storyIds) : [];

    $stories = [];
    foreach ($rows as $row) {
        $storyId = (int) ($row['id'] ?? 0);
        if ($storyId <= 0) {
            continue;
        }

        $mediaList = $mediaMap[$storyId] ?? [];
        $coverPath = '';
        if (!empty($mediaList)) {
            $firstMedia = $mediaList[0];
            $coverPath = (string) ($firstMedia['path'] ?? '');
        }

        if ($coverPath === '') {
            $coverPath = trim((string) ($row['media_url'] ?? ''));
        }

        $title = trim((string) ($row['story_title'] ?? ''));
        if ($coverPath === '' || $title === '') {
            continue;
        }

        $stories[] = [
            'id' => $storyId,
            'user_id' => (int) ($row['user_id'] ?? 0),
            'title' => $title,
            'caption' => trim((string) ($row['story_caption'] ?? '')),
            'credit' => trim((string) ($row['story_credit'] ?? '')),
            'image' => $coverPath,
            'media' => $mediaList,
            'author' => $row['username'] ?? 'Tifoso',
            'badge' => $row['badge'] ?? 'Tifoso',
            'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
            'published_at' => normalizeToTimestamp($row['published_at'] ?? ($row['created_at'] ?? time())),
        ];
    }

    return $stories;
}

function getFanSpotlight(): array
{
    $limit = 8;
    $stories = getCommunityStories($limit);

    $spotlight = [];
    $seenIds = [];

    foreach ($stories as $story) {
        $storyId = (int) ($story['id'] ?? 0);
        if ($storyId <= 0) {
            continue;
        }

        $credit = trim((string) ($story['credit'] ?? ''));
        $authorHandle = isset($story['author']) ? (string) $story['author'] : '';
        if ($credit === '' && $authorHandle !== '') {
            $credit = '@' . ltrim($authorHandle, '@');
        }

        $spotlight[] = [
            'id' => $storyId,
            'title' => trim((string) ($story['title'] ?? '')),
            'caption' => trim((string) ($story['caption'] ?? '')),
            'image' => trim((string) ($story['image'] ?? '')),
            'credit' => $credit,
            'author' => $story['author'] ?? 'Tifoso',
            'badge' => $story['badge'] ?? 'Tifoso',
            'published_at' => $story['published_at'] ?? time(),
            'permalink' => '?page=community#post-' . $storyId,
        ];

        $seenIds[$storyId] = true;
    }

    if (count($spotlight) < $limit) {
        $candidates = getCommunityPosts(0, $limit * 3);

        foreach ($candidates as $post) {
            $postId = (int) ($post['id'] ?? 0);
            if ($postId <= 0 || isset($seenIds[$postId])) {
                continue;
            }

            $type = strtolower((string) ($post['content_type'] ?? 'text'));
            if (!in_array($type, ['story', 'photo', 'gallery'], true)) {
                continue;
            }

            $coverPath = '';
            if (!empty($post['media']) && is_array($post['media'])) {
                $firstMedia = $post['media'][0] ?? [];
                $coverPath = trim((string) ($firstMedia['path'] ?? ''));
            }

            if ($coverPath === '') {
                $coverPath = trim((string) ($post['media_url'] ?? ''));
            }

            if ($coverPath === '') {
                continue;
            }

            $title = trim((string) ($post['story_title'] ?? ''));
            if ($title === '') {
                $plainContent = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($post['content_rendered'] ?? ''))));
                if ($plainContent !== '') {
                    if (mb_strlen($plainContent) > 80) {
                        $title = mb_substr($plainContent, 0, 77) . '...';
                    } else {
                        $title = $plainContent;
                    }
                } else {
                    $authorDisplay = trim((string) ($post['author_display_name'] ?? ($post['author'] ?? 'Tifoso')));
                    $title = 'Momento condiviso da ' . ($authorDisplay !== '' ? $authorDisplay : 'tifoso');
                }
            }

            $caption = trim((string) ($post['story_caption'] ?? ''));
            if ($caption === '') {
                $plainContent = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($post['content_rendered'] ?? ''))));
                if ($plainContent !== '') {
                    $caption = mb_strlen($plainContent) > 140
                        ? mb_substr($plainContent, 0, 137) . '...'
                        : $plainContent;
                }
            } elseif (mb_strlen($caption) > 140) {
                $caption = mb_substr($caption, 0, 137) . '...';
            }

            $credit = trim((string) ($post['story_credit'] ?? ''));
            $authorHandle = isset($post['author_username']) ? (string) $post['author_username'] : (string) ($post['author'] ?? '');
            if ($credit === '' && $authorHandle !== '') {
                $credit = '@' . ltrim($authorHandle, '@');
            }

            $spotlight[] = [
                'id' => $postId,
                'title' => $title,
                'caption' => $caption,
                'image' => $coverPath,
                'credit' => $credit,
                'author' => $post['author_display_name'] ?? ($post['author'] ?? 'Tifoso'),
                'badge' => $post['badge'] ?? 'Tifoso',
                'published_at' => $post['published_at'] ?? time(),
                'permalink' => '?page=community#post-' . $postId,
            ];

            $seenIds[$postId] = true;

            if (count($spotlight) >= $limit) {
                break;
            }
        }
    }

    return $spotlight;
}

// Register pages for the simple router
$availablePages = [
    'home' => __DIR__ . '/pages/home.php',
    'news' => __DIR__ . '/pages/news.php',
    'news_article' => __DIR__ . '/pages/news_article.php',
    'partite' => __DIR__ . '/pages/partite.php',
    'community' => __DIR__ . '/pages/community.php',
    'profile' => __DIR__ . '/pages/profile.php',
    'profile_settings' => __DIR__ . '/pages/profile_settings.php',
    'profile_search' => __DIR__ . '/pages/profile_search.php',
    'user_profile' => __DIR__ . '/pages/user_profile.php',
    'login' => __DIR__ . '/pages/login.php',
    'register' => __DIR__ . '/pages/register.php',
    'password_forgot' => __DIR__ . '/pages/password_forgot.php',
    'password_reset' => __DIR__ . '/pages/password_reset.php',
];

$pageTitles = [
    'home' => 'Home',
    'news' => 'Notizie',
    'news_article' => 'Notizia',
    'partite' => 'Partite',
    'community' => 'Community',
    'profile' => 'Profilo',
    'profile_settings' => 'Impostazioni profilo',
    'profile_search' => 'Cerca tifosi',
    'user_profile' => 'Profilo tifoso',
    'login' => 'Accedi',
    'register' => 'Registrati',
    'password_forgot' => 'Password dimenticata',
    'password_reset' => 'Reimposta password',
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
        'profile' => ['label' => 'Profilo', 'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0 .75.75 0 0 1-.75.75h-13.5a.75.75 0 0 1-.75-.75Z'],
    ];
}

/**
 * Retrieve news entries (static seed for first iteration).
 */
function getNewsItems(): array
{
    triggerPeriodicNewsSync();
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
        $statement = $pdo->query('SELECT id, competition, opponent, home_team, away_team, juventus_is_home, venue, kickoff_at, status, status_code, home_score, away_score, broadcast FROM matches WHERE kickoff_at >= (NOW() - INTERVAL 1 DAY) ORDER BY kickoff_at ASC');
        $matches = [];

        foreach ($statement as $row) {
            $kickoff = isset($row['kickoff_at']) ? new DateTime($row['kickoff_at']) : new DateTime();
            $juventusIsHome = null;
            if (isset($row['juventus_is_home'])) {
                $juventusIsHome = (int) $row['juventus_is_home'] === 1;
            }
            $matches[] = [
                'id' => (int) ($row['id'] ?? 0),
                'competition' => $row['competition'] ?? '',
                'opponent' => $row['opponent'] ?? '',
                'home_team' => $row['home_team'] ?? null,
                'away_team' => $row['away_team'] ?? null,
                'juventus_is_home' => $juventusIsHome,
                'venue' => $row['venue'] ?? '',
                'status' => $row['status'] ?? '',
                'status_code' => $row['status_code'] ?? null,
                'home_score' => isset($row['home_score']) && is_numeric($row['home_score']) ? (int) $row['home_score'] : null,
                'away_score' => isset($row['away_score']) && is_numeric($row['away_score']) ? (int) $row['away_score'] : null,
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

function buildUserDisplayName(?string $firstName, ?string $lastName, string $username): string
{
    $safeUsername = trim($username) !== '' ? trim($username) : 'Tifoso';
    $first = trim((string) $firstName);
    $last = trim((string) $lastName);

    if ($first === '' && $last === '') {
        return $safeUsername;
    }

    $parts = [];
    if ($first !== '') {
        $parts[] = preg_replace('/\s+/', ' ', $first);
    }
    if ($last !== '') {
        $parts[] = preg_replace('/\s+/', ' ', $last);
    }

    $display = trim(implode(' ', $parts));

    return $display !== '' ? $display : $safeUsername;
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
    $statement = $pdo->query('SELECT id, username, email, badge, first_name, last_name, created_at FROM users ORDER BY created_at DESC');
        $users = [];

        foreach ($statement as $row) {
            $users[] = [
                'id' => (int) ($row['id'] ?? 0),
                'username' => $row['username'] ?? '',
                'first_name' => isset($row['first_name']) ? (string) $row['first_name'] : null,
                'last_name' => isset($row['last_name']) ? (string) $row['last_name'] : null,
                'email' => $row['email'] ?? '',
                'badge' => $row['badge'] ?? 'Tifoso',
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
                'display_name' => buildUserDisplayName($row['first_name'] ?? null, $row['last_name'] ?? null, (string) ($row['username'] ?? '')),
            ];
        }

        return $users;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load registered users: ' . $exception->getMessage());
    }

    return [];
}

function findUserById(int $userId): ?array
{
    $userId = $userId > 0 ? $userId : 0;
    if ($userId <= 0) {
        return null;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return null;
    }

    try {
    $statement = $pdo->prepare('SELECT id, username, email, badge, first_name, last_name, avatar_url, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'username' => $row['username'] ?? '',
            'first_name' => isset($row['first_name']) ? (string) $row['first_name'] : null,
            'last_name' => isset($row['last_name']) ? (string) $row['last_name'] : null,
            'email' => $row['email'] ?? '',
            'badge' => $row['badge'] ?? 'Tifoso',
            'avatar_url' => $row['avatar_url'] ?? null,
            'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
            'updated_at' => isset($row['updated_at']) ? normalizeToTimestamp($row['updated_at']) : null,
            'display_name' => buildUserDisplayName($row['first_name'] ?? null, $row['last_name'] ?? null, (string) ($row['username'] ?? '')),
        ];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to find user by id: ' . $exception->getMessage());
    }

    return null;
}

function ensureUserProfilesTable(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS user_profiles (
            user_id INT UNSIGNED NOT NULL,
            bio TEXT DEFAULT NULL,
            location VARCHAR(120) DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            favorite_player VARCHAR(120) DEFAULT NULL,
            favorite_memory VARCHAR(255) DEFAULT NULL,
            cover_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            CONSTRAINT user_profiles_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $checked = true;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Unable to ensure user_profiles table: ' . $exception->getMessage());
        $checked = false;
    }
}

function ensurePasswordResetsTable(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY password_resets_token_hash_unique (token_hash),
            KEY password_resets_user_id_foreign (user_id),
            KEY password_resets_expires_at_index (expires_at),
            CONSTRAINT password_resets_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $checked = true;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Unable to ensure password_resets table: ' . $exception->getMessage());
        $checked = false;
    }
}

function userProfilesTableAvailable(?PDO $connection = null): bool
{
    static $cache;

    if ($cache !== null) {
        return $cache;
    }

    $pdo = $connection ?? getDatabaseConnection();
    if (!$pdo) {
        $cache = false;
        return $cache;
    }

    try {
        $pdo->query('SELECT user_id FROM user_profiles LIMIT 1');
        $cache = true;
    } catch (PDOException $exception) {
        $sqlState = $exception->getCode();
        if ($sqlState === '42S02' || stripos($exception->getMessage(), 'user_profiles') !== false) {
            $cache = false;
        } else {
            error_log('[BianconeriHub] Failed checking user_profiles availability: ' . $exception->getMessage());
            $cache = false;
        }
    }

    return $cache;
}

function ensureUserConsentsTable(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS user_consents (
            user_id INT UNSIGNED NOT NULL,
            cookie_consent_at DATETIME DEFAULT NULL,
            privacy_policy_accepted_at DATETIME DEFAULT NULL,
            data_processing_acknowledged_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            CONSTRAINT user_consents_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $checked = true;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Unable to ensure user_consents table: ' . $exception->getMessage());
        $checked = false;
    }
}

function recordDefaultUserConsents(PDO $pdo, int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    ensureUserConsentsTable($pdo);

    try {
        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $statement = $pdo->prepare('INSERT INTO user_consents (user_id, cookie_consent_at, privacy_policy_accepted_at, data_processing_acknowledged_at) VALUES (:user_id, :ts_insert, :ts_insert, :ts_insert) ON DUPLICATE KEY UPDATE cookie_consent_at = :ts_update, privacy_policy_accepted_at = :ts_update, data_processing_acknowledged_at = :ts_update');
        $statement->execute([
            'user_id' => $userId,
            'ts_insert' => $timestamp,
            'ts_update' => $timestamp,
        ]);
    } catch (Throwable $exception) {
        error_log('[BianconeriHub] Unable to record default user consents: ' . $exception->getMessage());
    }
}

function grantDefaultPrivacyConsents(): void
{
    $grantedAt = time();
    $_SESSION['bh_cookie_consent'] = true;
    $_SESSION['bh_privacy_consent'] = true;
    $_SESSION['bh_consent_granted_at'] = $grantedAt;

    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $expiresAt = $grantedAt + (365 * 24 * 60 * 60);
    $options = [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => $secure ? 'None' : 'Lax',
    ];

    $cookieNames = ['bh_cookie_consent', 'bh_privacy_consent', 'bh_data_processing_consent'];
    $canSendHeaders = !headers_sent();

    foreach ($cookieNames as $name) {
        $_COOKIE[$name] = 'accepted';
        if ($canSendHeaders) {
            setcookie($name, 'accepted', $options);
        }
    }
}

function getCommunityFollowersCount(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return 0;
    }

    try {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM community_followers WHERE user_id = :user');
        $statement->execute(['user' => $userId]);

        return (int) ($statement->fetchColumn() ?: 0);
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to count community followers: ' . $exception->getMessage());
    }

    return 0;
}

function getUserProfileView(string $username, ?int $viewerId = null): ?array
{
    $normalized = trim($username);
    if ($normalized === '') {
        return null;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return null;
    }

    ensureUserProfilesTable($pdo);

    try {
        $statement = $pdo->prepare('SELECT u.id, u.username, u.email, u.badge, u.first_name, u.last_name, u.avatar_url, u.created_at, u.updated_at,
            p.bio, p.location, p.website, p.favorite_player, p.favorite_memory, p.cover_path
            FROM users u
            LEFT JOIN user_profiles p ON p.user_id = u.id
            WHERE LOWER(u.username) = LOWER(:username)
            LIMIT 1');
        $statement->execute(['username' => $normalized]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        $userId = (int) ($row['id'] ?? 0);
        $viewerId = $viewerId ? max(0, $viewerId) : 0;
        $isCurrentUser = $viewerId > 0 && $viewerId === $userId;
        $viewerCanFollow = $viewerId > 0 && !$isCurrentUser;
        $isFollowing = $viewerCanFollow ? isCommunityFollower($userId, $viewerId) : false;

        return [
            'id' => $userId,
            'username' => $row['username'] ?? '',
            'first_name' => isset($row['first_name']) ? (string) $row['first_name'] : null,
            'last_name' => isset($row['last_name']) ? (string) $row['last_name'] : null,
            'email' => $row['email'] ?? '',
            'badge' => $row['badge'] ?? 'Tifoso',
            'avatar_url' => $row['avatar_url'] ?? null,
            'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
            'updated_at' => isset($row['updated_at']) ? normalizeToTimestamp($row['updated_at']) : null,
            'display_name' => buildUserDisplayName($row['first_name'] ?? null, $row['last_name'] ?? null, (string) ($row['username'] ?? '')),
            'bio' => trim((string) ($row['bio'] ?? '')),
            'location' => trim((string) ($row['location'] ?? '')),
            'website' => trim((string) ($row['website'] ?? '')),
            'favorite_player' => trim((string) ($row['favorite_player'] ?? '')),
            'favorite_memory' => trim((string) ($row['favorite_memory'] ?? '')),
            'cover_path' => trim((string) ($row['cover_path'] ?? '')),
            'followers_count' => getCommunityFollowersCount($userId),
            'following_count' => getCommunityFollowingCount($userId),
            'viewer_can_follow' => $viewerCanFollow,
            'is_following' => $isFollowing,
            'is_current_user' => $isCurrentUser,
        ];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load user profile view: ' . $exception->getMessage());
    }

    return null;
}

function saveUserProfileSettings(int $userId, array $input): array
{
    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Utente non valido.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio non disponibile al momento.'];
    }

    ensureUserProfilesTable($pdo);

    $firstName = trim((string) ($input['first_name'] ?? ''));
    if ($firstName !== '') {
        $firstName = preg_replace('/\s+/', ' ', $firstName);
        if (mb_strlen($firstName) > 80) {
            $firstName = mb_substr($firstName, 0, 80);
        }
    }

    $lastName = trim((string) ($input['last_name'] ?? ''));
    if ($lastName !== '') {
        $lastName = preg_replace('/\s+/', ' ', $lastName);
        if (mb_strlen($lastName) > 80) {
            $lastName = mb_substr($lastName, 0, 80);
        }
    }

    $bio = trim((string) ($input['bio'] ?? ''));
    if (mb_strlen($bio) > 500) {
        $bio = mb_substr($bio, 0, 500);
    }

    $location = trim((string) ($input['location'] ?? ''));
    if (mb_strlen($location) > 120) {
        $location = mb_substr($location, 0, 120);
    }

    $website = trim((string) ($input['website'] ?? ''));
    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        $website = '';
    }

    $favoritePlayer = trim((string) ($input['favorite_player'] ?? ''));
    if (mb_strlen($favoritePlayer) > 120) {
        $favoritePlayer = mb_substr($favoritePlayer, 0, 120);
    }

    $favoriteMemory = trim((string) ($input['favorite_memory'] ?? ''));
    if (mb_strlen($favoriteMemory) > 255) {
        $favoriteMemory = mb_substr($favoriteMemory, 0, 255);
    }

    try {
        $pdo->beginTransaction();

        $userUpdate = $pdo->prepare('UPDATE users SET first_name = :first_name, last_name = :last_name WHERE id = :user_id');
        $userUpdate->execute([
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'user_id' => $userId,
        ]);

        $statement = $pdo->prepare('INSERT INTO user_profiles (user_id, bio, location, website, favorite_player, favorite_memory)
            VALUES (:user_id, :bio, :location, :website, :favorite_player, :favorite_memory)
            ON DUPLICATE KEY UPDATE bio = VALUES(bio), location = VALUES(location), website = VALUES(website), favorite_player = VALUES(favorite_player), favorite_memory = VALUES(favorite_memory), updated_at = CURRENT_TIMESTAMP');
        $statement->execute([
            'user_id' => $userId,
            'bio' => $bio !== '' ? $bio : null,
            'location' => $location !== '' ? $location : null,
            'website' => $website !== '' ? $website : null,
            'favorite_player' => $favoritePlayer !== '' ? $favoritePlayer : null,
            'favorite_memory' => $favoriteMemory !== '' ? $favoriteMemory : null,
        ]);

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }

        if (isset($_SESSION['bh_current_user']) && (int) ($_SESSION['bh_current_user']['id'] ?? 0) === $userId) {
            $_SESSION['bh_current_user']['first_name'] = $firstName !== '' ? $firstName : null;
            $_SESSION['bh_current_user']['last_name'] = $lastName !== '' ? $lastName : null;
            $sessionUsername = $_SESSION['bh_current_user']['username'] ?? '';
            $_SESSION['bh_current_user']['display_name'] = buildUserDisplayName($_SESSION['bh_current_user']['first_name'] ?? null, $_SESSION['bh_current_user']['last_name'] ?? null, (string) $sessionUsername);
        }

        return ['success' => true];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[BianconeriHub] Failed to save user profile settings: ' . $exception->getMessage());
    }

    return ['success' => false, 'message' => 'Impossibile aggiornare il profilo in questo momento.'];
}

function getProfileUploadDirectory(string $type): string
{
    $base = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile';
    $subDirectory = $type === 'cover' ? 'covers' : 'avatars';
    $target = $base . DIRECTORY_SEPARATOR . $subDirectory;

    if (!is_dir($target)) {
        @mkdir($target, 0775, true);
    }

    return $target;
}

function deleteProfileImage(?string $relativePath): void
{
    if (!is_string($relativePath) || $relativePath === '') {
        return;
    }

    $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath);
    if (strpos($normalized, 'uploads' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR) !== 0) {
        return;
    }

    $absolute = __DIR__ . DIRECTORY_SEPARATOR . $normalized;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

function getProfileImageProcessingLimits(string $type): array
{
    if ($type === 'cover') {
        return ['max_width' => 1920, 'max_height' => 1080];
    }

    return ['max_width' => 1024, 'max_height' => 1024];
}

function loadProfileImageResource(string $path, string $mime)
{
    switch ($mime) {
        case 'image/jpeg':
            return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null;
        case 'image/png':
            return function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null;
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
        case 'image/gif':
            return function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : null;
        default:
            return null;
    }
}

function applyExifOrientationToImage($image, string $path)
{
    if (!function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($path);
    if (!$exif || !isset($exif['Orientation'])) {
        return $image;
    }

    $orientation = (int) $exif['Orientation'];
    if ($orientation === 1) {
        return $image;
    }

    $rotate = null;
    $flip = null;

    switch ($orientation) {
        case 2:
            $flip = IMG_FLIP_HORIZONTAL;
            break;
        case 3:
            $rotate = 180;
            break;
        case 4:
            $flip = IMG_FLIP_VERTICAL;
            break;
        case 5:
            $rotate = -90;
            $flip = IMG_FLIP_HORIZONTAL;
            break;
        case 6:
            $rotate = -90;
            break;
        case 7:
            $rotate = -90;
            $flip = IMG_FLIP_VERTICAL;
            break;
        case 8:
            $rotate = 90;
            break;
    }

    if ($rotate !== null && function_exists('imagerotate')) {
        $rotated = @imagerotate($image, $rotate, 0);
        if ($rotated !== false && $rotated !== null) {
            imagedestroy($image);
            $image = $rotated;
        }
    }

    if ($flip !== null && function_exists('imageflip')) {
        @imageflip($image, $flip);
    }

    return $image;
}

function determineProfileImageFormats(string $mime): array
{
    $formats = [];
    $supportsWebp = function_exists('imagewebp');
    $supportsJpeg = function_exists('imagejpeg');
    $supportsPng = function_exists('imagepng');
    $hasAlphaSource = in_array($mime, ['image/png', 'image/gif', 'image/webp'], true);

    if ($supportsWebp) {
        $formats[] = 'webp';
    }

    if (!$hasAlphaSource && $supportsJpeg) {
        $formats[] = 'jpeg';
    }

    if ($supportsPng) {
        $formats[] = 'png';
    }

    if ($supportsJpeg && !in_array('jpeg', $formats, true)) {
        $formats[] = 'jpeg';
    }

    return array_values(array_unique($formats));
}

function getCommunityImageProcessingLimits(): array
{
    return ['max_width' => 1920, 'max_height' => 1920];
}

function optimiseCommunityImageWithGd(string $sourcePath, string $mime, string $targetDirectory, string $baseName, int $maxBytes): array
{
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        return ['success' => false, 'message' => 'L\'ottimizzazione immagini non è disponibile sul server.'];
    }

    $sourceImage = loadProfileImageResource($sourcePath, $mime);
    if (!$sourceImage) {
        return ['success' => false, 'message' => 'Formato immagine non supportato.'];
    }

    if (function_exists('imagepalettetotruecolor')) {
        imagepalettetotruecolor($sourceImage);
    }

    if ($mime === 'image/jpeg') {
        $oriented = applyExifOrientationToImage($sourceImage, $sourcePath);
        if ($oriented !== $sourceImage) {
            imagedestroy($sourceImage);
            $sourceImage = $oriented;
        }
    }

    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        imagedestroy($sourceImage);
        return ['success' => false, 'message' => 'Impossibile leggere le dimensioni dell\'immagine.'];
    }

    $limits = getCommunityImageProcessingLimits();
    $scaleLimit = min(
        $limits['max_width'] / max($sourceWidth, 1),
        $limits['max_height'] / max($sourceHeight, 1),
        1.0
    );

    if ($scaleLimit <= 0 || !is_finite($scaleLimit)) {
        $scaleLimit = 1.0;
    }

    $formats = determineProfileImageFormats($mime);
    if (empty($formats)) {
        imagedestroy($sourceImage);
        return ['success' => false, 'message' => 'Nessun formato di salvataggio disponibile.'];
    }

    $lastError = 'Impossibile ottimizzare l\'immagine caricata.';
    foreach ($formats as $format) {
        $extension = $format === 'jpeg' ? 'jpg' : $format;
        $filename = $baseName . '.' . $extension;
        $destination = rtrim($targetDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        $result = saveOptimizedProfileImage($sourceImage, $sourceWidth, $sourceHeight, $destination, $format, $scaleLimit, $maxBytes);

        if ($result['success']) {
            imagedestroy($sourceImage);
            return [
                'success' => true,
                'filename' => $filename,
                'mime' => $format === 'jpeg' ? 'image/jpeg' : 'image/' . $extension,
            ];
        }

        $lastError = $result['message'] ?? $lastError;
    }

    imagedestroy($sourceImage);

    return ['success' => false, 'message' => $lastError];
}

function saveOptimizedProfileImage($sourceImage, int $sourceWidth, int $sourceHeight, string $destination, string $format, float $scaleLimit, int $maxBytes): array
{
    $scale = $scaleLimit > 0 && is_finite($scaleLimit) ? min(1.0, max($scaleLimit, 0.05)) : 1.0;
    $quality = $format === 'jpeg' ? 86 : ($format === 'webp' ? 82 : 90);
    $minQuality = $format === 'jpeg' ? 65 : ($format === 'webp' ? 55 : 60);

    for ($attempt = 0; $attempt < 7; $attempt++) {
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($format === 'jpeg') {
            imagealphablending($canvas, true);
            $background = imagecolorallocate($canvas, 16, 16, 16);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $background);
        } else {
            imagealphablending($canvas, false);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
            imagesavealpha($canvas, true);
        }

        imagecopyresampled($canvas, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        $tempPath = $destination . '.tmp';
        if (is_file($tempPath)) {
            @unlink($tempPath);
        }

        $saveResult = false;
        if ($format === 'webp' && function_exists('imagewebp')) {
            if (function_exists('imagepalettetotruecolor')) {
                imagepalettetotruecolor($canvas);
            }
            $saveResult = imagewebp($canvas, $tempPath, $quality);
        } elseif ($format === 'jpeg' && function_exists('imagejpeg')) {
            $saveResult = imagejpeg($canvas, $tempPath, $quality);
        } elseif ($format === 'png' && function_exists('imagepng')) {
            if (function_exists('imagepalettetotruecolor')) {
                imagepalettetotruecolor($canvas);
            }
            $saveResult = imagepng($canvas, $tempPath, 9);
        }

        imagedestroy($canvas);

        if (!$saveResult || !is_file($tempPath)) {
            @unlink($tempPath);
            return ['success' => false, 'message' => 'Impossibile salvare l\'immagine elaborata.'];
        }

        clearstatcache(true, $tempPath);
        $savedSize = filesize($tempPath);
        if ($savedSize > 0 && $savedSize <= $maxBytes) {
            $moved = @rename($tempPath, $destination);
            if (!$moved) {
                $moved = @copy($tempPath, $destination);
                @unlink($tempPath);
            }

            if ($moved) {
                return ['success' => true];
            }

            return ['success' => false, 'message' => 'Impossibile salvare l\'immagine elaborata.'];
        }

        @unlink($tempPath);

        if ($format === 'png') {
            $scale *= 0.85;
            if ($scale < 0.35) {
                break;
            }
            continue;
        }

        if ($quality > $minQuality) {
            $quality = max($minQuality, $quality - 8);
            continue;
        }

        $scale *= 0.85;
        if ($scale < 0.35) {
            break;
        }
    }

    return ['success' => false, 'message' => 'Impossibile ridurre le dimensioni dell\'immagine sotto i limiti consentiti.'];
}

function optimiseProfileImageWithGd(array $file, string $type, int $userId, string $mime, string $directory): array
{
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        return ['success' => false, 'message' => 'L\'elaborazione immagini non è disponibile sul server.'];
    }

    $sourceImage = loadProfileImageResource($file['tmp_name'], $mime);
    if (!$sourceImage) {
        return ['success' => false, 'message' => 'Immagine non supportata.'];
    }

    if (function_exists('imagepalettetotruecolor')) {
        imagepalettetotruecolor($sourceImage);
    }

    if ($mime === 'image/jpeg') {
        $sourceImage = applyExifOrientationToImage($sourceImage, $file['tmp_name']);
    }

    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        imagedestroy($sourceImage);
        return ['success' => false, 'message' => 'Impossibile leggere le dimensioni dell\'immagine.'];
    }

    $limits = getProfileImageProcessingLimits($type);
    $scaleLimit = min(
        $limits['max_width'] / max($sourceWidth, 1),
        $limits['max_height'] / max($sourceHeight, 1),
        1.0
    );
    if ($scaleLimit <= 0 || !is_finite($scaleLimit)) {
        $scaleLimit = 1.0;
    }

    try {
        $random = bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        $random = uniqid('img', true);
    }

    $formats = determineProfileImageFormats($mime);
    if (empty($formats)) {
        imagedestroy($sourceImage);
        return ['success' => false, 'message' => 'Nessun formato di salvataggio disponibile.'];
    }

    $baseName = $type === 'cover' ? 'cover' : 'avatar';
    $subDirectory = $type === 'cover' ? 'covers' : 'avatars';
    $maxBytes = 7 * 1024 * 1024;
    $lastError = 'Impossibile ottimizzare l\'immagine caricata.';

    foreach ($formats as $format) {
        $extension = $format === 'jpeg' ? 'jpg' : $format;
        $filename = sprintf('%s-%d-%s.%s', $baseName, $userId, $random, $extension);
        $destination = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        $result = saveOptimizedProfileImage($sourceImage, $sourceWidth, $sourceHeight, $destination, $format, $scaleLimit, $maxBytes);

        if ($result['success']) {
            imagedestroy($sourceImage);
            return [
                'success' => true,
                'path' => 'uploads/profile/' . $subDirectory . '/' . $filename,
            ];
        }

        $lastError = $result['message'] ?? $lastError;
    }

    imagedestroy($sourceImage);

    return ['success' => false, 'message' => $lastError];
}

function storeProfileImageUpload(?array $file, string $type, int $userId): array
{
    if (!is_array($file) || !isset($file['error'])) {
        return ['success' => false, 'message' => 'Nessun file selezionato.'];
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Caricamento non riuscito.'];
    }

    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['success' => false, 'message' => 'File non valido.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return ['success' => false, 'message' => 'File vuoto non valido.'];
    }

    $absoluteLimit = 32 * 1024 * 1024;
    if ($size > $absoluteLimit) {
        return ['success' => false, 'message' => 'L\'immagine è troppo pesante (limite 32MB).'];
    }

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']);
    }

    if ($mime === '' || $mime === false) {
        return ['success' => false, 'message' => 'Impossibile determinare il tipo di file.'];
    }
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        return ['success' => false, 'message' => 'Formato immagine non supportato.'];
    }

    $directory = getProfileUploadDirectory($type);
    $maxStoredBytes = 7 * 1024 * 1024;
    $lastOptimizationError = null;

    $optimized = optimiseProfileImageWithGd($file, $type, $userId, $mime, $directory);
    if ($optimized['success']) {
        return [
            'success' => true,
            'path' => str_replace(['\\'], '/', $optimized['path']),
        ];
    }

    $lastOptimizationError = $optimized['message'] ?? null;

    if ($size > $maxStoredBytes) {
        return ['success' => false, 'message' => $lastOptimizationError ?: 'Impossibile ottimizzare automaticamente l\'immagine caricata. Riducila manualmente e riprova.'];
    }

    try {
        $random = bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        $random = uniqid('img', true);
    }

    $extension = $allowed[$mime];
    $filename = sprintf('%s-%d-%s.%s', $type === 'cover' ? 'cover' : 'avatar', $userId, $random, $extension);
    $destination = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Impossibile salvare il file sul server.'];
    }

    $relative = 'uploads/profile/' . ($type === 'cover' ? 'covers/' : 'avatars/') . $filename;

    return [
        'success' => true,
        'path' => str_replace(['\\'], '/', $relative),
    ];
}

function updateUserAvatar(int $userId, ?array $file): array
{
    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Utente non valido.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio non disponibile.'];
    }

    $user = findUserById($userId);
    if (!$user) {
        return ['success' => false, 'message' => 'Utente non trovato.'];
    }

    $stored = storeProfileImageUpload($file, 'avatar', $userId);
    if (!$stored['success']) {
        return $stored;
    }

    $path = $stored['path'];

    try {
        $statement = $pdo->prepare('UPDATE users SET avatar_url = :avatar, updated_at = NOW() WHERE id = :id');
        $statement->execute([
            'avatar' => $path,
            'id' => $userId,
        ]);
    } catch (PDOException $exception) {
        deleteProfileImage($path);
        error_log('[BianconeriHub] Failed to update user avatar: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Impossibile aggiornare l\'avatar al momento.'];
    }

    if (!empty($user['avatar_url'])) {
        deleteProfileImage($user['avatar_url']);
    }

    if (isset($_SESSION['bh_current_user']) && (int) ($_SESSION['bh_current_user']['id'] ?? 0) === $userId) {
        $_SESSION['bh_current_user']['avatar_url'] = $path;
    }

    return ['success' => true, 'path' => $path];
}

function updateUserCover(int $userId, ?array $file): array
{
    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Utente non valido.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio non disponibile.'];
    }

    ensureUserProfilesTable($pdo);

    $stored = storeProfileImageUpload($file, 'cover', $userId);
    if (!$stored['success']) {
        return $stored;
    }

    $path = $stored['path'];

    try {
        $pdo->beginTransaction();

        $current = $pdo->prepare('SELECT cover_path FROM user_profiles WHERE user_id = :id FOR UPDATE');
        $current->execute(['id' => $userId]);
        $row = $current->fetch();
        $previous = $row && isset($row['cover_path']) ? trim((string) $row['cover_path']) : '';

        $statement = $pdo->prepare('INSERT INTO user_profiles (user_id, cover_path)
            VALUES (:user_id, :cover_path)
            ON DUPLICATE KEY UPDATE cover_path = VALUES(cover_path), updated_at = CURRENT_TIMESTAMP');
        $statement->execute([
            'user_id' => $userId,
            'cover_path' => $path,
        ]);

        $pdo->commit();

        if ($previous !== '') {
            deleteProfileImage($previous);
        }
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        deleteProfileImage($path);
        error_log('[BianconeriHub] Failed to update user cover: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Impossibile aggiornare la copertina al momento.'];
    }

    return ['success' => true, 'path' => $path];
}

function getUserCommunityPosts(int $authorId, int $offset = 0, int $limit = 6): array
{
    $authorId = $authorId > 0 ? $authorId : 0;
    if ($authorId <= 0) {
        return ['posts' => [], 'has_more' => false, 'next_offset' => $offset];
    }

    $limit = max(1, min($limit, 20));
    $offset = max(0, $offset);

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['posts' => [], 'has_more' => false, 'next_offset' => $offset];
    }

    $viewer = getLoggedInUser();
    $viewerId = isset($viewer['id']) ? (int) $viewer['id'] : 0;
    $viewerIsAuthor = $viewerId > 0 && $viewerId === $authorId;
    $extended = function_exists('communityPostsExtendedSchemaAvailable') ? communityPostsExtendedSchemaAvailable($pdo) : false;

    $baseColumns = 'p.id, p.user_id, p.content, p.content_type, p.media_url, p.created_at';
    if ($extended) {
        $baseColumns .= ', p.poll_question, p.poll_options, p.story_title, p.story_caption, p.story_credit, p.status, p.published_at';
    }

    $sql = 'SELECT ' . $baseColumns . ', u.username, u.badge
        FROM community_posts p
        INNER JOIN users u ON u.id = p.user_id
        WHERE p.user_id = :author';

    if ($extended) {
        $sql .= ' AND (p.status IS NULL OR p.status = "published"';
        if ($viewerIsAuthor) {
            $sql .= ' OR p.status IN ("draft", "scheduled")';
        }
        $sql .= ')';
    }

    $sql .= ' ORDER BY COALESCE(p.published_at, p.created_at) DESC LIMIT :limit OFFSET :offset';

    $statement = $pdo->prepare($sql);
    $statement->bindValue(':author', $authorId, PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    $rows = $statement->fetchAll();
    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        $rows = array_slice($rows, 0, $limit);
    }

    if (empty($rows)) {
        return ['posts' => [], 'has_more' => false, 'next_offset' => $offset];
    }

    $postIds = [];
    foreach ($rows as $row) {
        $postId = (int) ($row['id'] ?? 0);
        if ($postId > 0) {
            $postIds[$postId] = $postId;
        }
    }

    $likes = [];
    $supports = [];
    $comments = [];

    if (!empty($postIds)) {
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));

        try {
            $likesStatement = $pdo->prepare('SELECT post_id, COUNT(*) AS total FROM community_post_reactions WHERE reaction_type = "like" AND post_id IN (' . $placeholders . ') GROUP BY post_id');
            $likesStatement->execute(array_values($postIds));
            foreach ($likesStatement as $row) {
                $postId = (int) ($row['post_id'] ?? 0);
                if ($postId > 0) {
                    $likes[$postId] = (int) ($row['total'] ?? 0);
                }
            }

            $supportsStatement = $pdo->prepare('SELECT post_id, COUNT(*) AS total FROM community_post_reactions WHERE reaction_type = "support" AND post_id IN (' . $placeholders . ') GROUP BY post_id');
            $supportsStatement->execute(array_values($postIds));
            foreach ($supportsStatement as $row) {
                $postId = (int) ($row['post_id'] ?? 0);
                if ($postId > 0) {
                    $supports[$postId] = (int) ($row['total'] ?? 0);
                }
            }

            $commentsStatement = $pdo->prepare('SELECT post_id, COUNT(*) AS total FROM community_post_comments WHERE post_id IN (' . $placeholders . ') GROUP BY post_id');
            $commentsStatement->execute(array_values($postIds));
            foreach ($commentsStatement as $row) {
                $postId = (int) ($row['post_id'] ?? 0);
                if ($postId > 0) {
                    $comments[$postId] = (int) ($row['total'] ?? 0);
                }
            }
        } catch (PDOException $exception) {
            error_log('[BianconeriHub] Failed to aggregate community post stats: ' . $exception->getMessage());
        }
    }

    $mediaMap = getCommunityPostMedia(array_values($postIds));

    $viewerReactions = [
        'like' => [],
        'support' => [],
    ];

    if ($viewerId > 0 && !empty($postIds)) {
        try {
            $reactionStatement = $pdo->prepare('SELECT post_id, reaction_type FROM community_post_reactions WHERE user_id = :user_id AND post_id IN (' . $placeholders . ')');
            $reactionStatement->execute(array_merge([$viewerId], array_values($postIds)));
            foreach ($reactionStatement as $row) {
                $postId = (int) ($row['post_id'] ?? 0);
                $type = $row['reaction_type'] ?? '';
                if ($postId > 0 && isset($viewerReactions[$type])) {
                    $viewerReactions[$type][$postId] = true;
                }
            }
        } catch (PDOException $exception) {
            error_log('[BianconeriHub] Failed to load viewer reactions: ' . $exception->getMessage());
        }
    }

    $viewerCanFollow = $viewerId > 0 && !$viewerIsAuthor;
    $isFollowingAuthor = $viewerCanFollow ? isCommunityFollower($authorId, $viewerId) : false;

    $posts = [];
    foreach ($rows as $row) {
        $postId = (int) ($row['id'] ?? 0);
        if ($postId <= 0) {
            continue;
        }

        $content = (string) ($row['content'] ?? '');
        $mentions = extractMentionsFromText($content);
        $mentionMap = getUsersByUsernames($mentions);
        $rendered = renderCommunityContent($content, $mentionMap);

        $pollOptions = [];
        if (!empty($row['poll_options'])) {
            try {
                $decoded = json_decode((string) $row['poll_options'], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    foreach ($decoded as $optionValue) {
                        $optionText = trim((string) $optionValue);
                        if ($optionText !== '') {
                            $pollOptions[] = mb_substr($optionText, 0, 120);
                        }
                    }
                }
            } catch (\JsonException $exception) {
                $pollOptions = [];
            }
        }

        $posts[] = [
            'id' => $postId,
            'user_id' => (int) ($row['user_id'] ?? 0),
            'author' => $row['username'] ?? 'Tifoso',
            'badge' => $row['badge'] ?? 'Tifoso',
            'content' => $content,
            'content_rendered' => $rendered['html'],
            'mentions' => $rendered['mentions'],
            'content_type' => $row['content_type'] ?? 'text',
            'media_url' => $row['media_url'] ?? '',
            'media' => $mediaMap[$postId] ?? [],
            'poll_question' => $extended ? ($row['poll_question'] ?? '') : '',
            'poll_options' => $pollOptions,
            'story_title' => $extended ? ($row['story_title'] ?? '') : '',
            'story_caption' => $extended ? ($row['story_caption'] ?? '') : '',
            'story_credit' => $extended ? ($row['story_credit'] ?? '') : '',
            'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
            'published_at' => $extended ? normalizeToTimestamp($row['published_at'] ?? ($row['created_at'] ?? time())) : normalizeToTimestamp($row['created_at'] ?? time()),
            'likes_count' => $likes[$postId] ?? 0,
            'supports_count' => $supports[$postId] ?? 0,
            'comments_count' => $comments[$postId] ?? 0,
            'has_liked' => isset($viewerReactions['like'][$postId]),
            'has_supported' => isset($viewerReactions['support'][$postId]),
            'viewer_can_follow' => false,
            'is_following_author' => $isFollowingAuthor,
        ];
    }

    return [
        'posts' => $posts,
        'has_more' => $hasMore,
        'next_offset' => $offset + count($posts),
        'viewer_can_follow' => $viewerCanFollow,
        'is_following_author' => $isFollowingAuthor,
    ];
}

function searchCommunityUsers(string $query, int $limit = 12, int $offset = 0): array
{
    $term = trim($query);
    $limit = max(1, min($limit, 50));
    $offset = max(0, $offset);

    $response = [
        'query' => $term,
        'results' => [],
        'total' => 0,
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => false,
        'too_short' => false,
        'minimum_length' => 2,
    ];

    if ($term === '') {
        return $response;
    }

    if (mb_strlen($term) < $response['minimum_length']) {
        $response['too_short'] = true;
        return $response;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return $response;
    }

    $currentUser = getLoggedInUser();
    $currentUserId = isset($currentUser['id']) ? (int) $currentUser['id'] : 0;

    $likeTerm = '%' . $term . '%';

    try {
        $countStatement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username LIKE :term');
        $countStatement->execute(['term' => $likeTerm]);
        $total = (int) ($countStatement->fetchColumn() ?: 0);
        $response['total'] = $total;

        if ($total === 0) {
            return $response;
        }

    $sql = <<<SQL
SELECT u.id, u.username, u.first_name, u.last_name, u.badge, u.avatar_url, u.created_at,
       CASE WHEN f.follower_id IS NULL THEN 0 ELSE 1 END AS is_following
FROM users u
LEFT JOIN community_followers f ON f.user_id = u.id AND f.follower_id = :viewer
WHERE u.username LIKE :term
ORDER BY u.username ASC
LIMIT $limit OFFSET $offset
SQL;

        $statement = $pdo->prepare($sql);
        $statement->bindValue(':viewer', $currentUserId, PDO::PARAM_INT);
        $statement->bindValue(':term', $likeTerm, PDO::PARAM_STR);
        $statement->execute();

        $results = [];
        foreach ($statement as $row) {
            $userId = (int) ($row['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $createdAt = normalizeToTimestamp($row['created_at'] ?? time());

            $firstName = isset($row['first_name']) ? (string) $row['first_name'] : null;
            $lastName = isset($row['last_name']) ? (string) $row['last_name'] : null;

            $results[] = [
                'id' => $userId,
                'username' => $row['username'] ?? '',
                'display_name' => buildUserDisplayName($firstName, $lastName, (string) ($row['username'] ?? '')),
                'badge' => $row['badge'] ?? 'Tifoso',
                'avatar_url' => $row['avatar_url'] ?? null,
                'created_at' => $createdAt,
                'is_following' => ((int) ($row['is_following'] ?? 0)) === 1,
                'viewer_can_follow' => $currentUserId > 0 && $currentUserId !== $userId,
                'is_current_user' => $currentUserId > 0 && $currentUserId === $userId,
            ];
        }

        $response['results'] = $results;
        $response['has_more'] = ($offset + count($results)) < $total;

        return $response;
    } catch (PDOException $exception) {
        $sqlState = $exception->getCode();
        $message = $exception->getMessage();
        $missingFollowersTable = $sqlState === '42S02' || stripos($message, 'community_followers') !== false;

        if ($missingFollowersTable) {
            error_log('[BianconeriHub] Followers table not available, using fallback search: ' . $message);

            try {
                $fallbackSql = <<<SQL
SELECT u.id, u.username, u.first_name, u.last_name, u.badge, u.avatar_url, u.created_at
FROM users u
WHERE u.username LIKE :term
ORDER BY u.username ASC
LIMIT $limit OFFSET $offset
SQL;

                $fallback = $pdo->prepare($fallbackSql);
                $fallback->bindValue(':term', $likeTerm, PDO::PARAM_STR);
                $fallback->execute();

                $results = [];
                foreach ($fallback as $row) {
                    $userId = (int) ($row['id'] ?? 0);
                    if ($userId <= 0) {
                        continue;
                    }

                    $createdAt = normalizeToTimestamp($row['created_at'] ?? time());

                    $firstName = isset($row['first_name']) ? (string) $row['first_name'] : null;
                    $lastName = isset($row['last_name']) ? (string) $row['last_name'] : null;

                    $results[] = [
                        'id' => $userId,
                        'username' => $row['username'] ?? '',
                        'display_name' => buildUserDisplayName($firstName, $lastName, (string) ($row['username'] ?? '')),
                        'badge' => $row['badge'] ?? 'Tifoso',
                        'avatar_url' => $row['avatar_url'] ?? null,
                        'created_at' => $createdAt,
                        'is_following' => false,
                        'viewer_can_follow' => $currentUserId > 0 && $currentUserId !== $userId,
                        'is_current_user' => $currentUserId > 0 && $currentUserId === $userId,
                    ];
                }

                $response['results'] = $results;
                $response['has_more'] = ($offset + count($results)) < $total;

                return $response;
            } catch (PDOException $fallbackException) {
                error_log('[BianconeriHub] Failed to search community users (fallback): ' . $fallbackException->getMessage());
            }
        } else {
            error_log('[BianconeriHub] Failed to search community users: ' . $message);
        }
    }

    return $response;
}

function getUserProfileSummary(int $userId): array
{
    $summary = [
        'counts' => [
            'posts_total' => 0,
            'posts_published' => 0,
            'posts_scheduled' => 0,
            'posts_draft' => 0,
            'polls_created' => 0,
            'comments_written' => 0,
            'news_comments_written' => 0,
            'reactions_left' => 0,
            'reactions_left_breakdown' => ['like' => 0, 'support' => 0],
            'reactions_received' => 0,
            'reactions_received_breakdown' => ['like' => 0, 'support' => 0],
            'news_likes' => 0,
        ],
        'recent_posts' => [],
        'scheduled_posts' => [],
        'draft_posts' => [],
        'recent_comments' => [],
        'recent_news_comments' => [],
        'recent_news_likes' => [],
    ];

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return $summary;
    }

    try {
        $postsStatement = $pdo->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN status = "scheduled" THEN 1 ELSE 0 END) AS scheduled,
                SUM(CASE WHEN status = "draft" THEN 1 ELSE 0 END) AS drafts,
                SUM(CASE WHEN content_type = "poll" THEN 1 ELSE 0 END) AS polls
             FROM community_posts
             WHERE user_id = :user_id'
        );
        $postsStatement->execute(['user_id' => $userId]);
        $postCounts = $postsStatement->fetch() ?: [];

        $summary['counts']['posts_total'] = (int) ($postCounts['total'] ?? 0);
        $summary['counts']['posts_published'] = (int) ($postCounts['published'] ?? 0);
        $summary['counts']['posts_scheduled'] = (int) ($postCounts['scheduled'] ?? 0);
        $summary['counts']['posts_draft'] = (int) ($postCounts['drafts'] ?? 0);
        $summary['counts']['polls_created'] = (int) ($postCounts['polls'] ?? 0);

        $commentsStatement = $pdo->prepare('SELECT COUNT(*) AS total FROM community_post_comments WHERE user_id = :user_id');
        $commentsStatement->execute(['user_id' => $userId]);
        $summary['counts']['comments_written'] = (int) ($commentsStatement->fetchColumn() ?: 0);

        $newsCommentsStatement = $pdo->prepare('SELECT COUNT(*) AS total FROM news_comments WHERE user_id = :user_id');
        $newsCommentsStatement->execute(['user_id' => $userId]);
        $summary['counts']['news_comments_written'] = (int) ($newsCommentsStatement->fetchColumn() ?: 0);

        $reactionsLeftStatement = $pdo->prepare('SELECT reaction_type, COUNT(*) AS total FROM community_post_reactions WHERE user_id = :user_id GROUP BY reaction_type');
        $reactionsLeftStatement->execute(['user_id' => $userId]);
        $reactionsLeftTotal = 0;
        foreach ($reactionsLeftStatement as $row) {
            $type = (string) ($row['reaction_type'] ?? 'like');
            $count = (int) ($row['total'] ?? 0);
            if (isset($summary['counts']['reactions_left_breakdown'][$type])) {
                $summary['counts']['reactions_left_breakdown'][$type] = $count;
            }
            $reactionsLeftTotal += $count;
        }
        $summary['counts']['reactions_left'] = $reactionsLeftTotal;

        $reactionsReceivedStatement = $pdo->prepare(
            'SELECT r.reaction_type, COUNT(*) AS total
             FROM community_post_reactions r
             INNER JOIN community_posts p ON p.id = r.post_id
             WHERE p.user_id = :user_id
             GROUP BY r.reaction_type'
        );
        $reactionsReceivedStatement->execute(['user_id' => $userId]);
        $reactionsReceivedTotal = 0;
        foreach ($reactionsReceivedStatement as $row) {
            $type = (string) ($row['reaction_type'] ?? 'like');
            $count = (int) ($row['total'] ?? 0);
            if (isset($summary['counts']['reactions_received_breakdown'][$type])) {
                $summary['counts']['reactions_received_breakdown'][$type] = $count;
            }
            $reactionsReceivedTotal += $count;
        }
        $summary['counts']['reactions_received'] = $reactionsReceivedTotal;

        $newsLikesStatement = $pdo->prepare('SELECT COUNT(*) AS total FROM news_likes WHERE user_id = :user_id');
        $newsLikesStatement->execute(['user_id' => $userId]);
        $summary['counts']['news_likes'] = (int) ($newsLikesStatement->fetchColumn() ?: 0);

        $recentPostsStatement = $pdo->prepare('SELECT id, content, content_type, status, poll_question, published_at, scheduled_for, created_at, updated_at FROM community_posts WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5');
        $recentPostsStatement->execute(['user_id' => $userId]);
        foreach ($recentPostsStatement as $row) {
            $summary['recent_posts'][] = [
                'id' => (int) ($row['id'] ?? 0),
                'content' => $row['content'] ?? '',
                'content_type' => $row['content_type'] ?? 'text',
                'status' => $row['status'] ?? 'published',
                'poll_question' => $row['poll_question'] ?? '',
                'published_at' => normalizeToTimestamp($row['published_at'] ?? ($row['created_at'] ?? time())),
                'scheduled_for' => isset($row['scheduled_for']) ? normalizeToTimestamp($row['scheduled_for']) : null,
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
                'updated_at' => isset($row['updated_at']) ? normalizeToTimestamp($row['updated_at']) : null,
            ];
        }

        $scheduledStatement = $pdo->prepare('SELECT id, content, content_type, scheduled_for, created_at FROM community_posts WHERE user_id = :user_id AND status = "scheduled" ORDER BY scheduled_for ASC LIMIT 5');
        $scheduledStatement->execute(['user_id' => $userId]);
        foreach ($scheduledStatement as $row) {
            $summary['scheduled_posts'][] = [
                'id' => (int) ($row['id'] ?? 0),
                'content' => $row['content'] ?? '',
                'content_type' => $row['content_type'] ?? 'text',
                'scheduled_for' => isset($row['scheduled_for']) ? normalizeToTimestamp($row['scheduled_for']) : null,
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
            ];
        }

        $draftsStatement = $pdo->prepare('SELECT id, content, content_type, updated_at, created_at FROM community_posts WHERE user_id = :user_id AND status = "draft" ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 5');
        $draftsStatement->execute(['user_id' => $userId]);
        foreach ($draftsStatement as $row) {
            $summary['draft_posts'][] = [
                'id' => (int) ($row['id'] ?? 0),
                'content' => $row['content'] ?? '',
                'content_type' => $row['content_type'] ?? 'text',
                'updated_at' => isset($row['updated_at']) ? normalizeToTimestamp($row['updated_at']) : null,
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
            ];
        }

        $communityCommentsStatement = $pdo->prepare('SELECT c.id, c.content, c.created_at, c.post_id, p.content AS post_content FROM community_post_comments c INNER JOIN community_posts p ON p.id = c.post_id WHERE c.user_id = :user_id ORDER BY c.created_at DESC LIMIT 5');
        $communityCommentsStatement->execute(['user_id' => $userId]);
        foreach ($communityCommentsStatement as $row) {
            $summary['recent_comments'][] = [
                'id' => (int) ($row['id'] ?? 0),
                'content' => $row['content'] ?? '',
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
                'post_id' => (int) ($row['post_id'] ?? 0),
                'post_content' => $row['post_content'] ?? '',
            ];
        }

        $newsCommentsRecentStatement = $pdo->prepare('SELECT c.id, c.content, c.created_at, n.id AS news_id, n.title, n.slug FROM news_comments c INNER JOIN news n ON n.id = c.news_id WHERE c.user_id = :user_id ORDER BY c.created_at DESC LIMIT 5');
        $newsCommentsRecentStatement->execute(['user_id' => $userId]);
        foreach ($newsCommentsRecentStatement as $row) {
            $summary['recent_news_comments'][] = [
                'id' => (int) ($row['id'] ?? 0),
                'content' => $row['content'] ?? '',
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
                'news_id' => (int) ($row['news_id'] ?? 0),
                'news_title' => $row['title'] ?? '',
                'news_slug' => $row['slug'] ?? '',
            ];
        }

        $newsLikesRecentStatement = $pdo->prepare('SELECT nl.news_id, nl.created_at, n.title, n.slug FROM news_likes nl INNER JOIN news n ON n.id = nl.news_id WHERE nl.user_id = :user_id ORDER BY nl.created_at DESC LIMIT 5');
        $newsLikesRecentStatement->execute(['user_id' => $userId]);
        foreach ($newsLikesRecentStatement as $row) {
            $summary['recent_news_likes'][] = [
                'news_id' => (int) ($row['news_id'] ?? 0),
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
                'news_title' => $row['title'] ?? '',
                'news_slug' => $row['slug'] ?? '',
            ];
        }
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to build user profile summary: ' . $exception->getMessage());
    }

    return $summary;
}

if (!function_exists('findUserByUsername')) {
    function findUserByUsername(string $username): ?array
    {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return null;
        }

        try {
            $statement = $pdo->prepare('SELECT id, username, email, password_hash, badge, first_name, last_name, avatar_url, created_at, updated_at FROM users WHERE LOWER(username) = LOWER(:username) LIMIT 1');
            $statement->execute(['username' => $username]);
            $user = $statement->fetch();

            if (!$user) {
                return null;
            }

            $user['id'] = (int) ($user['id'] ?? 0);
            $user['created_at'] = normalizeToTimestamp($user['created_at'] ?? time());
            if (isset($user['updated_at'])) {
                $user['updated_at'] = normalizeToTimestamp($user['updated_at']);
            }

            $user['display_name'] = buildUserDisplayName($user['first_name'] ?? null, $user['last_name'] ?? null, (string) ($user['username'] ?? ''));

            return $user;
        } catch (PDOException $exception) {
            error_log('[BianconeriHub] Failed to find user by username: ' . $exception->getMessage());
        }

        return null;
    }
}

if (!function_exists('findUserByEmail')) {
    function findUserByEmail(string $email): ?array
    {
        $normalized = trim($email);
        if ($normalized === '') {
            return null;
        }

        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return null;
        }

        try {
            $statement = $pdo->prepare('SELECT id, username, email, password_hash, badge, first_name, last_name, avatar_url, created_at, updated_at FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
            $statement->execute(['email' => $normalized]);
            $user = $statement->fetch();

            if (!$user) {
                return null;
            }

            $user['id'] = (int) ($user['id'] ?? 0);
            $user['created_at'] = normalizeToTimestamp($user['created_at'] ?? time());
            if (isset($user['updated_at'])) {
                $user['updated_at'] = normalizeToTimestamp($user['updated_at']);
            }

            $user['display_name'] = buildUserDisplayName($user['first_name'] ?? null, $user['last_name'] ?? null, (string) ($user['username'] ?? ''));

            return $user;
        } catch (PDOException $exception) {
            error_log('[BianconeriHub] Failed to find user by email: ' . $exception->getMessage());
        }

        return null;
    }
}

function registerUser(array $payload): array
{
    $username = trim($payload['username'] ?? '');
    $email = trim($payload['email'] ?? '');
    $password = $payload['password'] ?? '';
    $passwordConfirmation = $payload['password_confirmation'] ?? '';
    $firstName = trim((string) ($payload['first_name'] ?? ''));
    $lastName = trim((string) ($payload['last_name'] ?? ''));

    if ($firstName !== '') {
        $firstName = preg_replace('/\s+/', ' ', $firstName);
        if (mb_strlen($firstName) > 80) {
            $firstName = mb_substr($firstName, 0, 80);
        }
    }

    if ($lastName !== '') {
        $lastName = preg_replace('/\s+/', ' ', $lastName);
        if (mb_strlen($lastName) > 80) {
            $lastName = mb_substr($lastName, 0, 80);
        }
    }

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

        $insert = $pdo->prepare('INSERT INTO users (username, email, password_hash, badge, first_name, last_name) VALUES (:username, :email, :password_hash, :badge, :first_name, :last_name)');
        $insert->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'badge' => 'Nuovo tifoso',
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
        ]);

        $userId = (int) $pdo->lastInsertId();
        recordDefaultUserConsents($pdo, $userId);
        $pdo->commit();

        $userData = [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
            'badge' => 'Nuovo tifoso',
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'display_name' => buildUserDisplayName($firstName !== '' ? $firstName : null, $lastName !== '' ? $lastName : null, $username),
        ];

        sendWelcomeEmail($userData);
        notifyAdminNewRegistration($userData);

        return [
            'success' => true,
            'user' => $userData,
        ];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[BianconeriHub] Failed to register user: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Registrazione non riuscita. Riprova.'];
    }
}

function createPasswordResetToken(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return null;
    }

    ensurePasswordResetsTable($pdo);

    try {
        $pdo->beginTransaction();

        $delete = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
        $delete->execute(['user_id' => $userId]);

        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $exception) {
            $fallback = openssl_random_pseudo_bytes(32);
            $token = is_string($fallback) ? bin2hex($fallback) : '';
        }

        if (!is_string($token) || $token === '') {
            $token = hash('sha256', uniqid('bh-reset', true) . microtime(true));
        }

        $tokenHash = hash('sha256', $token);
        $expiresAt = new DateTimeImmutable('+60 minutes');

        $insert = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)');
        $insert->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        $pdo->commit();

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[BianconeriHub] Unable to create password reset token: ' . $exception->getMessage());
    }

    return null;
}

function getPasswordResetTokenDetails(string $token): ?array
{
    $normalized = trim($token);
    if ($normalized === '') {
        return null;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return null;
    }

    ensurePasswordResetsTable($pdo);

    try {
        $statement = $pdo->prepare('SELECT pr.id, pr.user_id, pr.token_hash, pr.expires_at, pr.created_at, u.username, u.email, u.first_name, u.last_name FROM password_resets pr INNER JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = :token_hash LIMIT 1');
        $statement->execute(['token_hash' => hash('sha256', $normalized)]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        try {
            $expiresAt = new DateTimeImmutable((string) $row['expires_at']);
        } catch (Throwable $exception) {
            $expiresAt = new DateTimeImmutable();
        }

        $userId = (int) ($row['user_id'] ?? 0);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => $userId,
            'expires_at' => $expiresAt,
            'expired' => $expiresAt < new DateTimeImmutable(),
            'user' => [
                'id' => $userId,
                'username' => $row['username'] ?? '',
                'email' => $row['email'] ?? '',
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
            ],
        ];
    } catch (Throwable $exception) {
        error_log('[BianconeriHub] Unable to load password reset token details: ' . $exception->getMessage());
    }

    return null;
}

function completePasswordReset(string $token, string $password, string $passwordConfirmation): array
{
    $normalizedToken = trim($token);
    if ($normalizedToken === '') {
        return ['success' => false, 'status' => 'invalid', 'message' => 'Link di reimpostazione non valido.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'status' => 'validation', 'message' => 'La nuova password deve contenere almeno 6 caratteri.'];
    }

    if ($password !== $passwordConfirmation) {
        return ['success' => false, 'status' => 'validation', 'message' => 'Le password non coincidono.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'status' => 'error', 'message' => 'Servizio momentaneamente non disponibile.'];
    }

    ensurePasswordResetsTable($pdo);

    $tokenHash = hash('sha256', $normalizedToken);

    try {
        $pdo->beginTransaction();

        $statement = $pdo->prepare('SELECT id, user_id, expires_at FROM password_resets WHERE token_hash = :token_hash LIMIT 1 FOR UPDATE');
        $statement->execute(['token_hash' => $tokenHash]);
        $row = $statement->fetch();

        if (!$row) {
            $pdo->rollBack();
            return ['success' => false, 'status' => 'invalid', 'message' => 'Link di reimpostazione non valido o già utilizzato.'];
        }

        try {
            $expiresAt = new DateTimeImmutable((string) $row['expires_at']);
        } catch (Throwable $exception) {
            $expiresAt = new DateTimeImmutable('-1 minute');
        }

        if ($expiresAt < new DateTimeImmutable()) {
            $deleteExpired = $pdo->prepare('DELETE FROM password_resets WHERE id = :id');
            $deleteExpired->execute(['id' => (int) $row['id']]);
            $pdo->commit();

            return ['success' => false, 'status' => 'expired', 'message' => 'Il link di reimpostazione è scaduto. Richiedi una nuova email.'];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $updatePassword = $pdo->prepare('UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :user_id');
        $updatePassword->execute([
            'password_hash' => $passwordHash,
            'user_id' => (int) $row['user_id'],
        ]);

        $deleteToken = $pdo->prepare('DELETE FROM password_resets WHERE id = :id');
        $deleteToken->execute(['id' => (int) $row['id']]);

        $pdo->commit();

        return ['success' => true, 'status' => 'ok'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[BianconeriHub] Unable to complete password reset: ' . $exception->getMessage());
    }

    return ['success' => false, 'status' => 'error', 'message' => 'Impossibile aggiornare la password in questo momento.'];
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
        'first_name' => $user['first_name'] ?? null,
        'last_name' => $user['last_name'] ?? null,
        'email' => $user['email'],
        'badge' => $user['badge'] ?? 'Tifoso',
        'avatar_url' => $user['avatar_url'] ?? null,
        'display_name' => $user['display_name'] ?? buildUserDisplayName($user['first_name'] ?? null, $user['last_name'] ?? null, (string) ($user['username'] ?? '')),
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
    if (!isset($_SESSION['bh_current_user'])) {
        return null;
    }

    $sessionUser = $_SESSION['bh_current_user'];
    $userId = (int) ($sessionUser['id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    static $cachedUser = null;
    static $cachedKey = null;

    $cacheKey = $userId . ':' . ($sessionUser['login_time'] ?? 0);
    if ($cachedUser !== null && $cachedKey === $cacheKey) {
        return $cachedUser;
    }

    $userRecord = findUserById($userId);
    if (is_array($userRecord)) {
        $sessionUser = array_merge($sessionUser, $userRecord);
        $_SESSION['bh_current_user'] = $sessionUser;
    }

    $cachedUser = $sessionUser;
    $cachedKey = $cacheKey;

    return $sessionUser;
}

function isWebPushConfigured(): bool
{
    if (!class_exists('Minishlink\\WebPush\\WebPush')) {
        return false;
    }

    $publicKey = trim((string) env('VAPID_PUBLIC_KEY', ''));
    $privateKey = trim((string) env('VAPID_PRIVATE_KEY', ''));

    return $publicKey !== '' && $privateKey !== '';
}

function getPushVapidPublicKey(): string
{
    return trim((string) env('VAPID_PUBLIC_KEY', ''));
}

function getWebPushClient()
{
    static $client;
    static $initialised = false;

    if ($initialised) {
        return $client;
    }

    $initialised = true;

    if (!isWebPushConfigured()) {
        $client = null;
        return $client;
    }

    $webPushClass = 'Minishlink\\WebPush\\WebPush';
    if (!class_exists($webPushClass)) {
        $client = null;
        return $client;
    }

    $publicKey = getPushVapidPublicKey();
    $privateKey = trim((string) env('VAPID_PRIVATE_KEY', ''));
    $subject = trim((string) env('PUSH_SUBJECT', ''));

    try {
        $client = new $webPushClass([
            'VAPID' => [
                'subject' => $subject !== '' ? $subject : 'mailto:push@bianconerihub.local',
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);
    } catch (\Throwable $exception) {
        error_log('[BianconeriHub] Unable to initialise WebPush client: ' . $exception->getMessage());
        $client = null;
    }

    return $client;
}

function registerPushSubscription(int $userId, ?array $subscription, string $scope = 'global', array $meta = []): array
{
    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Utente non valido per le notifiche.'];
    }

    if (!is_array($subscription)) {
        return ['success' => false, 'message' => 'Dati di sottoscrizione non validi.'];
    }

    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
    $keys = isset($subscription['keys']) && is_array($subscription['keys']) ? $subscription['keys'] : [];
    $publicKey = trim((string) ($keys['p256dh'] ?? ''));
    $authToken = trim((string) ($keys['auth'] ?? ''));

    if ($endpoint === '' || $publicKey === '' || $authToken === '') {
        return ['success' => false, 'message' => 'Impossibile attivare le notifiche su questo dispositivo.'];
    }

    $scopeValue = strtolower($scope);
    if (!in_array($scopeValue, ['global', 'following'], true)) {
        $scopeValue = 'global';
    }

    $deviceName = trim((string) ($meta['device_name'] ?? ''));
    if ($deviceName === '') {
        $deviceName = null;
    }

    $userAgent = trim((string) ($meta['user_agent'] ?? ''));
    if ($userAgent === '') {
        $userAgent = null;
    }

    $contentEncoding = trim((string) ($meta['content_encoding'] ?? 'aes128gcm'));
    if ($contentEncoding === '') {
        $contentEncoding = 'aes128gcm';
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio notifiche non disponibile al momento.'];
    }

    if ($scopeValue === 'following') {
        $followingCount = getCommunityFollowingCount($userId, $pdo);
        if ($followingCount <= 0) {
            return ['success' => false, 'message' => 'Segui almeno un tifoso per attivare le notifiche mirate.'];
        }
    }

    try {
        $pdo->beginTransaction();

        $check = $pdo->prepare('SELECT id FROM user_push_subscriptions WHERE user_id = :user_id AND endpoint = :endpoint LIMIT 1');
        $check->execute([
            'user_id' => $userId,
            'endpoint' => $endpoint,
        ]);

        $existingId = (int) $check->fetchColumn();

        if ($existingId > 0) {
            $update = $pdo->prepare('UPDATE user_push_subscriptions SET public_key = :public_key, auth_token = :auth_token, content_encoding = :content_encoding, device_name = :device_name, user_agent = :user_agent, scope = :scope, updated_at = NOW() WHERE id = :id');
            $update->execute([
                'public_key' => $publicKey,
                'auth_token' => $authToken,
                'content_encoding' => $contentEncoding,
                'device_name' => $deviceName,
                'user_agent' => $userAgent,
                'scope' => $scopeValue,
                'id' => $existingId,
            ]);
        } else {
            $insert = $pdo->prepare('INSERT INTO user_push_subscriptions (user_id, endpoint, public_key, auth_token, content_encoding, device_name, user_agent, scope) VALUES (:user_id, :endpoint, :public_key, :auth_token, :content_encoding, :device_name, :user_agent, :scope)');
            $insert->execute([
                'user_id' => $userId,
                'endpoint' => $endpoint,
                'public_key' => $publicKey,
                'auth_token' => $authToken,
                'content_encoding' => $contentEncoding,
                'device_name' => $deviceName,
                'user_agent' => $userAgent,
                'scope' => $scopeValue,
            ]);
        }

        $pdo->commit();

        return ['success' => true, 'scope' => $scopeValue];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[BianconeriHub] Failed to register push subscription: ' . $exception->getMessage());

        return ['success' => false, 'message' => 'Impossibile salvare le notifiche su questo dispositivo. Riprova più tardi.'];
    }
}

function removePushSubscription(int $userId, string $endpoint): array
{
    $normalizedEndpoint = trim($endpoint);
    if ($normalizedEndpoint === '') {
        return ['success' => false, 'message' => 'Endpoint non valido.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio notifiche non disponibile al momento.'];
    }

    try {
        $delete = $pdo->prepare('DELETE FROM user_push_subscriptions WHERE user_id = :user_id AND endpoint = :endpoint');
        $delete->execute([
            'user_id' => $userId,
            'endpoint' => $normalizedEndpoint,
        ]);

        return ['success' => true];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to remove push subscription: ' . $exception->getMessage());
    }

    return ['success' => false, 'message' => 'Impossibile aggiornare le notifiche in questo momento.'];
}

function removePushSubscriptionById(int $subscriptionId): void
{
    if ($subscriptionId <= 0) {
        return;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return;
    }

    try {
        $delete = $pdo->prepare('DELETE FROM user_push_subscriptions WHERE id = :id');
        $delete->execute(['id' => $subscriptionId]);
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to prune expired push subscription: ' . $exception->getMessage());
    }
}

function getCommunityFollowersIds(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $statement = $pdo->prepare('SELECT follower_id FROM community_followers WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);

        $followers = [];
        foreach ($statement as $row) {
            $followerId = (int) ($row['follower_id'] ?? 0);
            if ($followerId > 0) {
                $followers[$followerId] = $followerId;
            }
        }

        return array_values($followers);
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load community followers: ' . $exception->getMessage());
    }

    return [];
}

function isCommunityFollower(int $userId, int $followerId): bool
{
    if ($userId <= 0 || $followerId <= 0) {
        return false;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $check = $pdo->prepare('SELECT 1 FROM community_followers WHERE user_id = :user AND follower_id = :follower LIMIT 1');
        $check->execute([
            'user' => $userId,
            'follower' => $followerId,
        ]);

        return (bool) $check->fetchColumn();
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to verify community follower: ' . $exception->getMessage());
    }

    return false;
}

function followCommunityUser(int $userId, int $followerId): array
{
    if ($userId <= 0 || $followerId <= 0) {
        return ['success' => false, 'message' => 'Utenti non validi.'];
    }

    if ($userId === $followerId) {
        return ['success' => false, 'message' => 'Non puoi seguire te stesso.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio non disponibile.'];
    }

    try {
        $insert = $pdo->prepare('INSERT IGNORE INTO community_followers (user_id, follower_id) VALUES (:user_id, :follower_id)');
        $insert->execute([
            'user_id' => $userId,
            'follower_id' => $followerId,
        ]);

        $state = $insert->rowCount() === 1 ? 'followed' : 'already_following';

        return [
            'success' => true,
            'state' => $state,
        ];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to follow community user: ' . $exception->getMessage());
    }

    return ['success' => false, 'message' => 'Impossibile seguire questo tifoso al momento.'];
}

function unfollowCommunityUser(int $userId, int $followerId): array
{
    if ($userId <= 0 || $followerId <= 0) {
        return ['success' => false, 'message' => 'Utenti non validi.'];
    }

    if ($userId === $followerId) {
        return ['success' => false, 'message' => 'Non puoi smettere di seguire te stesso.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio non disponibile.'];
    }

    try {
        $delete = $pdo->prepare('DELETE FROM community_followers WHERE user_id = :user_id AND follower_id = :follower_id');
        $delete->execute([
            'user_id' => $userId,
            'follower_id' => $followerId,
        ]);

        $state = $delete->rowCount() > 0 ? 'unfollowed' : 'not_following';

        return [
            'success' => true,
            'state' => $state,
        ];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to unfollow community user: ' . $exception->getMessage());
    }

    return ['success' => false, 'message' => 'Impossibile aggiornare il seguito in questo momento.'];
}

function getCommunityFollowingCount(int $followerId, ?PDO $connection = null): int
{
    if ($followerId <= 0) {
        return 0;
    }

    $pdo = $connection ?? getDatabaseConnection();
    if (!$pdo) {
        return 0;
    }

    try {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM community_followers WHERE follower_id = :follower');
        $statement->execute(['follower' => $followerId]);

        return (int) ($statement->fetchColumn() ?: 0);
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to count community followings: ' . $exception->getMessage());
    }

    return 0;
}

function getPushSubscriptionsForUsers(array $userIds, ?PDO $connection = null): array
{
    $uniqueIds = [];
    foreach ($userIds as $userId) {
        $intId = (int) $userId;
        if ($intId > 0) {
            $uniqueIds[$intId] = $intId;
        }
    }

    if (empty($uniqueIds)) {
        return [];
    }

    $pdo = $connection ?? getDatabaseConnection();
    if (!$pdo) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));

    try {
        $statement = $pdo->prepare('SELECT id, user_id, endpoint, public_key, auth_token, content_encoding, scope FROM user_push_subscriptions WHERE user_id IN (' . $placeholders . ')');
        $statement->execute(array_values($uniqueIds));

        $subscriptions = [];
        foreach ($statement as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            $id = (int) ($row['id'] ?? 0);
            if ($userId <= 0 || $id <= 0) {
                continue;
            }

            if (!isset($subscriptions[$userId])) {
                $subscriptions[$userId] = [];
            }

            $subscriptions[$userId][] = [
                'id' => $id,
                'user_id' => $userId,
                'endpoint' => $row['endpoint'] ?? '',
                'public_key' => $row['public_key'] ?? '',
                'auth_token' => $row['auth_token'] ?? '',
                'content_encoding' => $row['content_encoding'] ?? 'aes128gcm',
                'scope' => $row['scope'] ?? 'global',
            ];
        }

        return $subscriptions;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load push subscriptions for users: ' . $exception->getMessage());
    }

    return [];
}

function getPushSubscriptionsForPost(int $authorId): array
{
    if ($authorId <= 0) {
        return [];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return [];
    }

    $subscriptions = [];

    try {
        $globalStatement = $pdo->prepare('SELECT id, user_id, endpoint, public_key, auth_token, content_encoding, scope FROM user_push_subscriptions WHERE scope = "global" AND user_id <> :author');
        $globalStatement->execute(['author' => $authorId]);

        foreach ($globalStatement as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $subscriptions[$id] = [
                'id' => $id,
                'user_id' => (int) ($row['user_id'] ?? 0),
                'endpoint' => $row['endpoint'] ?? '',
                'public_key' => $row['public_key'] ?? '',
                'auth_token' => $row['auth_token'] ?? '',
                'content_encoding' => $row['content_encoding'] ?? 'aes128gcm',
                'scope' => $row['scope'] ?? 'global',
            ];
        }

        $followers = getCommunityFollowersIds($authorId);
        if (!empty($followers)) {
            $placeholders = implode(',', array_fill(0, count($followers), '?'));
            $followersStatement = $pdo->prepare('SELECT id, user_id, endpoint, public_key, auth_token, content_encoding, scope FROM user_push_subscriptions WHERE user_id IN (' . $placeholders . ') AND scope = "following"');
            $followersStatement->execute(array_values($followers));

            foreach ($followersStatement as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                if ((int) ($row['user_id'] ?? 0) === $authorId) {
                    continue;
                }

                $subscriptions[$id] = [
                    'id' => $id,
                    'user_id' => (int) ($row['user_id'] ?? 0),
                    'endpoint' => $row['endpoint'] ?? '',
                    'public_key' => $row['public_key'] ?? '',
                    'auth_token' => $row['auth_token'] ?? '',
                    'content_encoding' => $row['content_encoding'] ?? 'aes128gcm',
                    'scope' => $row['scope'] ?? 'following',
                ];
            }
        }
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load push subscriptions: ' . $exception->getMessage());
    }

    return array_values(array_filter($subscriptions, static function (array $subscription): bool {
        return $subscription['endpoint'] !== '' && $subscription['public_key'] !== '' && $subscription['auth_token'] !== '';
    }));
}

function findCommunityPostForNotification(int $postId): ?array
{
    if ($postId <= 0) {
        return null;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return null;
    }

    $extended = communityPostsExtendedSchemaAvailable($pdo);

    $columns = 'SELECT p.id, p.user_id, p.content, p.content_type, p.media_url, p.created_at, u.username, u.badge';
    if ($extended) {
        $columns = 'SELECT p.id, p.user_id, p.content, p.content_type, p.media_url, p.created_at, p.published_at, u.username, u.badge';
    }

    $sql = $columns . ' FROM community_posts p INNER JOIN users u ON u.id = p.user_id WHERE p.id = :id LIMIT 1';

    try {
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $postId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        $createdAt = normalizeToTimestamp($row['created_at'] ?? time());
        $publishedAt = $extended
            ? normalizeToTimestamp($row['published_at'] ?? ($row['created_at'] ?? time()))
            : $createdAt;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'content' => $row['content'] ?? '',
            'content_type' => $row['content_type'] ?? 'text',
            'media_url' => $row['media_url'] ?? '',
            'created_at' => $createdAt,
            'published_at' => $publishedAt,
            'author' => $row['username'] ?? 'Tifoso',
            'badge' => $row['badge'] ?? 'Tifoso',
        ];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load community post for notification: ' . $exception->getMessage());
    }

    return null;
}

function truncateForNotification(string $text, int $limit = 140): string
{
    $trimmed = trim($text);

    if ($trimmed === '') {
        return '';
    }

    if (mb_strlen($trimmed) <= $limit) {
        return $trimmed;
    }

    return rtrim(mb_substr($trimmed, 0, max(1, $limit - 1))) . '…';
}

function buildCommunityMentionNotificationPayload(array $post, array $recipient): ?array
{
    global $baseUrl;

    $author = $post['author'] ?? 'Membro';
    $title = $author . ' ti ha menzionato';

    $excerpt = sanitizeFeedBody($post['content'] ?? '');
    $body = truncateForNotification($excerpt !== '' ? $excerpt : 'Ti ha menzionato in un post della community.');

    $relativeUrl = '?page=community#post-' . $post['id'];
    $url = $baseUrl !== '' ? rtrim($baseUrl, '/') . '/' . ltrim($relativeUrl, '/') : $relativeUrl;

    $recipientId = isset($recipient['id']) ? (int) $recipient['id'] : 0;
    if ($recipientId <= 0) {
        return null;
    }

    $payload = [
        'title' => $title,
        'body' => $body,
        'tag' => 'community-mention-' . $post['id'] . '-' . $recipientId,
        'data' => [
            'url' => $url,
            'post_id' => $post['id'],
            'author' => $author,
            'type' => 'mention',
        ],
    ];

    $iconPath = trim((string) env('PUSH_ICON_PATH', ''));
    if ($iconPath !== '') {
        $resolvedIcon = $baseUrl !== '' ? rtrim($baseUrl, '/') . '/' . ltrim($iconPath, '/') : $iconPath;
        $payload['icon'] = $resolvedIcon;
        $payload['badge'] = $resolvedIcon;
    }

    return $payload;
}

function buildCommunityPostNotificationPayload(array $post): ?array
{
    global $baseUrl;

    $author = $post['author'] ?? 'Membro';
    $title = 'Nuovo post di ' . $author;

    $excerpt = sanitizeFeedBody($post['content'] ?? '');
    $body = truncateForNotification($excerpt !== '' ? $excerpt : 'Ha pubblicato un nuovo aggiornamento nella community.');

    $relativeUrl = '?page=community#post-' . $post['id'];
    $url = $baseUrl !== '' ? rtrim($baseUrl, '/') . '/' . ltrim($relativeUrl, '/') : $relativeUrl;

    $payload = [
        'title' => $title,
        'body' => $body,
        'tag' => 'community-post-' . $post['id'],
        'data' => [
            'url' => $url,
            'post_id' => $post['id'],
            'author' => $author,
        ],
    ];

    $iconPath = trim((string) env('PUSH_ICON_PATH', ''));
    if ($iconPath !== '') {
        $resolvedIcon = $baseUrl !== '' ? rtrim($baseUrl, '/') . '/' . ltrim($iconPath, '/') : $iconPath;
        $payload['icon'] = $resolvedIcon;
        $payload['badge'] = $resolvedIcon;
    }

    return $payload;
}

function notifyCommunityPostPublished(int $postId): void
{
    if (!isWebPushConfigured()) {
        return;
    }

    $post = findCommunityPostForNotification($postId);
    if (!$post || $post['user_id'] <= 0) {
        return;
    }

    $subscriptions = getPushSubscriptionsForPost($post['user_id']);
    if (empty($subscriptions)) {
        return;
    }

    $payload = buildCommunityPostNotificationPayload($post);
    if (!is_array($payload)) {
        return;
    }

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (!is_string($payloadJson)) {
        return;
    }

    $webPush = getWebPushClient();
    if (!$webPush) {
        return;
    }

    $subscriptionMap = [];

    $subscriptionClass = 'Minishlink\\WebPush\\Subscription';
    if (!class_exists($subscriptionClass)) {
        return;
    }

    $queueMethod = method_exists($webPush, 'queueNotification') ? 'queueNotification' : (method_exists($webPush, 'sendNotification') ? 'sendNotification' : null);
    if ($queueMethod === null) {
        return;
    }

    foreach ($subscriptions as $subscription) {
        if ($subscription['endpoint'] === '') {
            continue;
        }

        $subscriptionMap[$subscription['endpoint']] = $subscription['id'];

        try {
            $subscriptionObject = $subscriptionClass::create([
                'endpoint' => $subscription['endpoint'],
                'publicKey' => $subscription['public_key'],
                'authToken' => $subscription['auth_token'],
                'contentEncoding' => $subscription['content_encoding'] ?: 'aes128gcm',
            ]);

            $webPush->{$queueMethod}($subscriptionObject, $payloadJson);
        } catch (\Throwable $exception) {
            error_log('[BianconeriHub] Failed to queue push notification: ' . $exception->getMessage());
        }
    }

    foreach ($webPush->flush() as $report) {
        $endpoint = $report->getEndpoint();

        if ($report->isSubscriptionExpired() && isset($subscriptionMap[$endpoint])) {
            removePushSubscriptionById($subscriptionMap[$endpoint]);
        }

        if (!$report->isSuccess()) {
            error_log('[BianconeriHub] Push notification delivery failed: ' . $report->getReason());
        }
    }
}

function notifyCommunityPostMentions(int $postId): void
{
    if ($postId <= 0 || !isWebPushConfigured()) {
        return;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo || !communityPostMentionsTableAvailable($pdo)) {
        return;
    }

    $post = findCommunityPostForNotification($postId);
    if (!$post) {
        return;
    }

    try {
        $statement = $pdo->prepare('SELECT m.id, m.mentioned_user_id, u.username FROM community_post_mentions m INNER JOIN users u ON u.id = m.mentioned_user_id WHERE m.post_id = :post_id AND m.notified_at IS NULL');
        $statement->execute(['post_id' => $postId]);
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load mentions for notification: ' . $exception->getMessage());
        return;
    }

    $mentions = [];
    while ($row = $statement->fetch()) {
        $mentionId = (int) ($row['id'] ?? 0);
        $mentionedUserId = (int) ($row['mentioned_user_id'] ?? 0);
        if ($mentionId <= 0 || $mentionedUserId <= 0 || $mentionedUserId === $post['user_id']) {
            continue;
        }

        $mentions[] = [
            'id' => $mentionId,
            'user_id' => $mentionedUserId,
            'username' => $row['username'] ?? '',
        ];
    }

    if (empty($mentions)) {
        return;
    }

    $userIds = [];
    foreach ($mentions as $mention) {
        $userIds[$mention['user_id']] = $mention['user_id'];
    }

    $subscriptionsByUser = getPushSubscriptionsForUsers(array_values($userIds), $pdo);

    $webPush = getWebPushClient();
    if (!$webPush) {
        markCommunityMentionsAsNotified($pdo, array_column($mentions, 'id'));
        return;
    }

    $subscriptionClass = 'Minishlink\WebPush\Subscription';
    if (!class_exists($subscriptionClass)) {
        markCommunityMentionsAsNotified($pdo, array_column($mentions, 'id'));
        return;
    }

    $queueMethod = method_exists($webPush, 'queueNotification') ? 'queueNotification' : (method_exists($webPush, 'sendNotification') ? 'sendNotification' : null);
    if ($queueMethod === null) {
        markCommunityMentionsAsNotified($pdo, array_column($mentions, 'id'));
        return;
    }

    $subscriptionMap = [];
    $notifiedMentionIds = [];
    $payloadCache = [];

    foreach ($mentions as $mention) {
        $userSubscriptions = $subscriptionsByUser[$mention['user_id']] ?? [];
        if (empty($userSubscriptions)) {
            $notifiedMentionIds[] = $mention['id'];
            continue;
        }

        if (!array_key_exists($mention['user_id'], $payloadCache)) {
            $payload = buildCommunityMentionNotificationPayload($post, [
                'id' => $mention['user_id'],
                'username' => $mention['username'],
            ]);

            $payloadCache[$mention['user_id']] = is_array($payload)
                ? json_encode($payload, JSON_UNESCAPED_UNICODE)
                : null;
        }

        $payloadJson = $payloadCache[$mention['user_id']];
        if (!is_string($payloadJson) || $payloadJson === '') {
            $notifiedMentionIds[] = $mention['id'];
            continue;
        }

        foreach ($userSubscriptions as $subscription) {
            if (($subscription['endpoint'] ?? '') === '' || ($subscription['public_key'] ?? '') === '' || ($subscription['auth_token'] ?? '') === '') {
                continue;
            }

            try {
                $subscriptionObject = $subscriptionClass::create([
                    'endpoint' => $subscription['endpoint'],
                    'publicKey' => $subscription['public_key'],
                    'authToken' => $subscription['auth_token'],
                    'contentEncoding' => $subscription['content_encoding'] ?: 'aes128gcm',
                ]);

                $webPush->{$queueMethod}($subscriptionObject, $payloadJson);
                $subscriptionMap[$subscription['endpoint']] = $subscription['id'];
            } catch (\Throwable $exception) {
                error_log('[BianconeriHub] Failed to queue mention notification: ' . $exception->getMessage());
            }
        }

        $notifiedMentionIds[] = $mention['id'];
    }

    foreach ($webPush->flush() as $report) {
        $endpoint = $report->getEndpoint();

        if ($report->isSubscriptionExpired() && isset($subscriptionMap[$endpoint])) {
            removePushSubscriptionById($subscriptionMap[$endpoint]);
        }

        if (!$report->isSuccess()) {
            error_log('[BianconeriHub] Push notification delivery failed: ' . $report->getReason());
        }
    }

    if (!empty($notifiedMentionIds)) {
        markCommunityMentionsAsNotified($pdo, $notifiedMentionIds);
    }
}

function publishDueCommunityPosts(): int
{
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return 0;
    }

    if (!communityPostsExtendedSchemaAvailable($pdo)) {
        return 0;
    }

    $postIds = [];

    try {
        $pdo->beginTransaction();

        $candidates = $pdo->prepare('SELECT id FROM community_posts WHERE status = "scheduled" AND scheduled_for IS NOT NULL AND scheduled_for <= NOW() FOR UPDATE');
        $candidates->execute();

        foreach ($candidates as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $postIds[] = $id;
            }
        }

        if (empty($postIds)) {
            $pdo->commit();
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $update = $pdo->prepare('UPDATE community_posts SET status = "published", published_at = NOW(), scheduled_for = NULL WHERE id IN (' . $placeholders . ')');
        $update->execute($postIds);

        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[BianconeriHub] Failed to publish scheduled community posts: ' . $exception->getMessage());

        return 0;
    }

    foreach ($postIds as $postId) {
        try {
            notifyCommunityPostPublished($postId);
        } catch (\Throwable $exception) {
            error_log('[BianconeriHub] Failed to send publication notification for post ' . $postId . ': ' . $exception->getMessage());
        }

        try {
            notifyCommunityPostMentions($postId);
        } catch (\Throwable $exception) {
            error_log('[BianconeriHub] Failed to send mention notification for post ' . $postId . ': ' . $exception->getMessage());
        }
    }

    return count($postIds);
}

function getCommunityPostMedia(array $postIds): array
{
    $uniqueIds = [];
    foreach ($postIds as $postId) {
        $intId = (int) $postId;
        if ($intId > 0) {
            $uniqueIds[$intId] = $intId;
        }
    }

    if (empty($uniqueIds)) {
        return [];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));

    try {
        $statement = $pdo->prepare('SELECT id, post_id, file_path, mime_type, position FROM community_post_media WHERE post_id IN (' . $placeholders . ') ORDER BY position ASC, id ASC');
        $statement->execute(array_values($uniqueIds));

        $media = [];
        foreach ($statement as $row) {
            $postId = (int) ($row['post_id'] ?? 0);
            if ($postId <= 0) {
                continue;
            }

            if (!isset($media[$postId])) {
                $media[$postId] = [];
            }

            $media[$postId][] = [
                'id' => (int) ($row['id'] ?? 0),
                'path' => $row['file_path'] ?? '',
                'mime' => $row['mime_type'] ?? '',
                'position' => (int) ($row['position'] ?? 0),
            ];
        }

        return $media;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load community post media: ' . $exception->getMessage());
    }

    return [];
}

function getCommunityMediaAbsolutePath(string $relativePath): string
{
    $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath);
    if (strpos($normalized, 'uploads' . DIRECTORY_SEPARATOR . 'community') !== 0) {
        $normalized = 'uploads' . DIRECTORY_SEPARATOR . 'community' . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
    }

    return __DIR__ . DIRECTORY_SEPARATOR . $normalized;
}

function deleteCommunityMediaFile(string $relativePath): void
{
    $absolute = getCommunityMediaAbsolutePath($relativePath);
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

function extractMentionsFromText(string $content): array
{
    $mentions = [];
    if (preg_match_all('/(?<=^|\s)@([a-z0-9_]{3,30})/iu', $content, $matches)) {
        foreach ($matches[1] as $username) {
            $mentions[strtolower($username)] = $username;
        }
    }

    return array_values($mentions);
}

function getUsersByUsernames(array $usernames): array
{
    if (empty($usernames)) {
        return [];
    }

    $unique = [];
    foreach ($usernames as $username) {
        $trimmed = trim((string) $username);
        if ($trimmed !== '') {
            $unique[strtolower($trimmed)] = $trimmed;
        }
    }

    if (empty($unique)) {
        return [];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($unique), '?'));

    try {
    $statement = $pdo->prepare('SELECT id, username, badge, first_name, last_name FROM users WHERE LOWER(username) IN (' . $placeholders . ')');
        $statement->execute(array_keys($unique));

        $map = [];
        foreach ($statement as $row) {
            $username = $row['username'] ?? '';
            if ($username === '') {
                continue;
            }

            $map[strtolower($username)] = [
                'id' => (int) ($row['id'] ?? 0),
                'username' => $username,
                'badge' => $row['badge'] ?? 'Tifoso',
                'first_name' => isset($row['first_name']) ? (string) $row['first_name'] : null,
                'last_name' => isset($row['last_name']) ? (string) $row['last_name'] : null,
                'display_name' => buildUserDisplayName($row['first_name'] ?? null, $row['last_name'] ?? null, $username),
            ];
        }

        return $map;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to resolve mention usernames: ' . $exception->getMessage());
    }

    return [];
}

function renderCommunityContent(string $content, array $mentionMap): array
{
    $escaped = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $usedMentions = [];

    $rendered = preg_replace_callback(
        '/(?<=^|\s)@([a-z0-9_]{3,30})/iu',
        function ($matches) use ($mentionMap, &$usedMentions) {
            $lookup = strtolower($matches[1]);
            if (!isset($mentionMap[$lookup])) {
                return $matches[0];
            }

            $user = $mentionMap[$lookup];
            $usedMentions[$lookup] = $user['username'];
            $label = '@' . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
            $url = '?page=community&user=' . urlencode($user['username']);

            return str_replace(
                '@' . $matches[1],
                '<a href="' . $url . '" class="mention">' . $label . '</a>',
                $matches[0]
            );
        },
        $escaped
    );

    return [
        'html' => nl2br($rendered),
        'mentions' => array_values($usedMentions),
    ];
}

function normalizeUploadedFiles(array $fileSpec): array
{
    if (!isset($fileSpec['name'])) {
        return [];
    }

    if (!is_array($fileSpec['name'])) {
        return [
            [
                'name' => $fileSpec['name'] ?? '',
                'type' => $fileSpec['type'] ?? '',
                'tmp_name' => $fileSpec['tmp_name'] ?? '',
                'error' => $fileSpec['error'] ?? UPLOAD_ERR_NO_FILE,
                'size' => $fileSpec['size'] ?? 0,
            ],
        ];
    }

    $normalized = [];
    foreach ($fileSpec['name'] as $index => $name) {
        $normalized[] = [
            'name' => $name ?? '',
            'type' => $fileSpec['type'][$index] ?? '',
            'tmp_name' => $fileSpec['tmp_name'][$index] ?? '',
            'error' => $fileSpec['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileSpec['size'][$index] ?? 0,
        ];
    }

    return $normalized;
}

function getCommunityPostForEditing(int $postId, int $userId): ?array
{
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return null;
    }

    try {
    $statement = $pdo->prepare('SELECT id, user_id, content, content_type, poll_question, poll_options, story_title, story_caption, story_credit, shared_news_id, shared_news_title, shared_news_slug, shared_news_excerpt, shared_news_tag, shared_news_image, shared_news_source_url, shared_news_published_at, status, scheduled_for, published_at, media_url FROM community_posts WHERE id = :id AND user_id = :user_id LIMIT 1');
        $statement->execute([
            'id' => $postId,
            'user_id' => $userId,
        ]);

        $row = $statement->fetch();
        if (!$row) {
            return null;
        }

        $pollOptions = [];
        if (!empty($row['poll_options'])) {
            try {
                $decoded = json_decode((string) $row['poll_options'], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    foreach ($decoded as $option) {
                        $optionText = trim((string) $option);
                        if ($optionText !== '') {
                            $pollOptions[] = mb_substr($optionText, 0, 120);
                        }
                    }
                }
            } catch (\JsonException $exception) {
                $pollOptions = [];
            }
        }

        $mediaMap = getCommunityPostMedia([$postId]);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'content' => $row['content'] ?? '',
            'content_type' => $row['content_type'] ?? 'text',
            'poll_question' => $row['poll_question'] ?? '',
            'poll_options' => $pollOptions,
            'story_title' => $row['story_title'] ?? '',
            'story_caption' => $row['story_caption'] ?? '',
            'story_credit' => $row['story_credit'] ?? '',
            'shared_news_id' => isset($row['shared_news_id']) ? (int) $row['shared_news_id'] : 0,
            'shared_news_title' => $row['shared_news_title'] ?? '',
            'shared_news_slug' => $row['shared_news_slug'] ?? '',
            'shared_news_excerpt' => $row['shared_news_excerpt'] ?? '',
            'shared_news_tag' => $row['shared_news_tag'] ?? '',
            'shared_news_image' => $row['shared_news_image'] ?? '',
            'shared_news_source_url' => $row['shared_news_source_url'] ?? '',
            'shared_news_published_at' => $row['shared_news_published_at'] ?? null,
            'status' => $row['status'] ?? 'draft',
            'scheduled_for' => $row['scheduled_for'] ?? null,
            'published_at' => $row['published_at'] ?? null,
            'media_url' => $row['media_url'] ?? '',
            'media' => $mediaMap[$postId] ?? [],
        ];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load community post for editing: ' . $exception->getMessage());
    }

    return null;
}

function getCommunityPosts(int $offset = 0, int $limit = 20): array
{
    publishDueCommunityPosts();

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return [];
    }

    ensureUserProfilesTable($pdo);
    $userProfilesAvailable = userProfilesTableAvailable($pdo);

    $offset = max(0, $offset);
    $limit = max(1, min($limit, 50));

    $extendedSchema = communityPostsExtendedSchemaAvailable($pdo);
    $currentUser = getLoggedInUser();
    $currentUserId = isset($currentUser['id']) ? (int) $currentUser['id'] : 0;

    $authorColumns = 'u.username,
                    u.first_name,
                    u.last_name,
                    u.badge,
                    u.avatar_url AS author_avatar_url';
    if ($userProfilesAvailable) {
        $authorColumns .= ',
                    up.cover_path AS author_cover_path';
    } else {
        $authorColumns .= ',
                    NULL AS author_cover_path';
    }

    if ($extendedSchema) {
    $sql = 'SELECT
            p.id,
            p.user_id,
            p.content,
            p.content_type,
            p.media_url,
            p.poll_question,
            p.poll_options,
            p.story_title,
            p.story_caption,
        p.story_credit,
        p.shared_news_id,
        p.shared_news_title,
        p.shared_news_slug,
        p.shared_news_excerpt,
        p.shared_news_tag,
        p.shared_news_image,
        p.shared_news_source_url,
        p.shared_news_published_at,
            p.created_at,
            p.published_at,
            p.status,
                    ' . $authorColumns . ',
                    (SELECT COUNT(*) FROM community_post_comments c WHERE c.post_id = p.id) AS comments_count,
                    (SELECT COUNT(*) FROM community_post_reactions r WHERE r.post_id = p.id AND r.reaction_type = "like") AS likes_count,
                    (SELECT COUNT(*) FROM community_post_reactions r WHERE r.post_id = p.id AND r.reaction_type = "support") AS supports_count';
    } else {
        $sql = 'SELECT
                    p.id,
                    p.user_id,
                    p.content,
                    p.content_type,
                    p.media_url,
                    p.created_at,
                    ' . $authorColumns . ',
                    (SELECT COUNT(*) FROM community_post_comments c WHERE c.post_id = p.id) AS comments_count,
                    (SELECT COUNT(*) FROM community_post_reactions r WHERE r.post_id = p.id AND r.reaction_type = "like") AS likes_count,
                    (SELECT COUNT(*) FROM community_post_reactions r WHERE r.post_id = p.id AND r.reaction_type = "support") AS supports_count';
    }

    if ($currentUserId > 0) {
        $sql .= ',
                EXISTS(
                    SELECT 1 FROM community_post_reactions r1
                    WHERE r1.post_id = p.id AND r1.user_id = :current_user_like AND r1.reaction_type = "like"
                ) AS has_liked,
                EXISTS(
                    SELECT 1 FROM community_post_reactions r2
                    WHERE r2.post_id = p.id AND r2.user_id = :current_user_support AND r2.reaction_type = "support"
                ) AS has_supported,
                EXISTS(
                    SELECT 1 FROM community_followers f
                    WHERE f.user_id = p.user_id AND f.follower_id = :current_user_follow
                ) AS is_following_author';
    } else {
        $sql .= ', 0 AS has_liked, 0 AS has_supported, 0 AS is_following_author';
    }

    $sql .= ' FROM community_posts p
              INNER JOIN users u ON u.id = p.user_id';

    if ($userProfilesAvailable) {
        $sql .= '
              LEFT JOIN user_profiles up ON up.user_id = u.id';
    }

    if ($extendedSchema) {
        $sql .= ' WHERE (p.status = "published" OR p.status IS NULL OR p.status = "")
                   ORDER BY p.published_at DESC, p.created_at DESC';
    } else {
        $sql .= ' ORDER BY p.created_at DESC';
    }

    $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;

    try {
        $statement = $pdo->prepare($sql);
        if ($currentUserId > 0) {
            $statement->bindValue(':current_user_like', $currentUserId, PDO::PARAM_INT);
            $statement->bindValue(':current_user_support', $currentUserId, PDO::PARAM_INT);
            $statement->bindValue(':current_user_follow', $currentUserId, PDO::PARAM_INT);
        }
        $statement->execute();

        $posts = [];

        $rawPosts = [];
        foreach ($statement as $row) {
            $pollOptions = [];
            if ($extendedSchema && !empty($row['poll_options'])) {
                try {
                    $decoded = json_decode((string) $row['poll_options'], true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        foreach ($decoded as $option) {
                            $optionText = trim((string) $option);
                            if ($optionText !== '') {
                                $pollOptions[] = mb_substr($optionText, 0, 120);
                            }
                        }
                    }
                } catch (\JsonException $exception) {
                    $pollOptions = [];
                }
            }

            $authorId = (int) ($row['user_id'] ?? 0);
            $canFollow = $currentUserId > 0 && $currentUserId !== $authorId;
            $isFollowingAuthor = ((int) ($row['is_following_author'] ?? 0)) === 1;

            $rawPosts[] = [
                'id' => (int) ($row['id'] ?? 0),
                'user_id' => $authorId,
                'author' => $row['username'] ?? 'Tifoso',
                'author_username' => isset($row['username']) ? (string) $row['username'] : '',
                'author_first_name' => isset($row['first_name']) ? (string) $row['first_name'] : null,
                'author_last_name' => isset($row['last_name']) ? (string) $row['last_name'] : null,
                'author_display_name' => buildUserDisplayName($row['first_name'] ?? null, $row['last_name'] ?? null, (string) ($row['username'] ?? 'Tifoso')),
                'badge' => $row['badge'] ?? 'Tifoso',
                'content' => $row['content'] ?? '',
                'content_type' => $row['content_type'] ?? 'text',
                'media_url' => $row['media_url'] ?? '',
                'poll_question' => $extendedSchema ? ($row['poll_question'] ?? '') : '',
                'poll_options' => $pollOptions,
                'story_title' => $extendedSchema ? ($row['story_title'] ?? '') : '',
                'story_caption' => $extendedSchema ? ($row['story_caption'] ?? '') : '',
                'story_credit' => $extendedSchema ? ($row['story_credit'] ?? '') : '',
                'shared_news_id' => $extendedSchema ? (int) ($row['shared_news_id'] ?? 0) : 0,
                'shared_news_title' => $extendedSchema ? ($row['shared_news_title'] ?? '') : '',
                'shared_news_slug' => $extendedSchema ? ($row['shared_news_slug'] ?? '') : '',
                'shared_news_excerpt' => $extendedSchema ? ($row['shared_news_excerpt'] ?? '') : '',
                'shared_news_tag' => $extendedSchema ? ($row['shared_news_tag'] ?? '') : '',
                'shared_news_image' => $extendedSchema ? ($row['shared_news_image'] ?? '') : '',
                'shared_news_source_url' => $extendedSchema ? ($row['shared_news_source_url'] ?? '') : '',
                'shared_news_published_at' => $extendedSchema ? ($row['shared_news_published_at'] ?? null) : null,
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
                'published_at' => $extendedSchema
                    ? normalizeToTimestamp($row['published_at'] ?? ($row['created_at'] ?? time()))
                    : normalizeToTimestamp($row['created_at'] ?? time()),
                'status' => $extendedSchema ? ($row['status'] ?? 'published') : 'published',
                'likes_count' => (int) ($row['likes_count'] ?? 0),
                'supports_count' => (int) ($row['supports_count'] ?? 0),
                'comments_count' => (int) ($row['comments_count'] ?? 0),
                'has_liked' => ((int) ($row['has_liked'] ?? 0)) === 1,
                'has_supported' => ((int) ($row['has_supported'] ?? 0)) === 1,
                'viewer_can_follow' => $canFollow,
                'is_following_author' => $canFollow ? $isFollowingAuthor : false,
                'author_avatar_url' => trim((string) ($row['author_avatar_url'] ?? '')),
                'author_cover_path' => trim((string) ($row['author_cover_path'] ?? '')),
            ];
        }

        if (empty($rawPosts)) {
            return [];
        }

        $pollPostIds = [];
        foreach ($rawPosts as $rawPost) {
            if (($rawPost['content_type'] ?? '') === 'poll') {
                $pollPostIds[] = $rawPost['id'];
            }
        }

        $pollSnapshot = !empty($pollPostIds) ? getCommunityPollVoteSnapshot($pollPostIds, $currentUserId) : [];

        $postIds = array_map(static function (array $post): int {
            return $post['id'];
        }, $rawPosts);

        $mediaMap = getCommunityPostMedia($postIds);

        $allMentions = [];
        foreach ($rawPosts as $post) {
            $mentions = extractMentionsFromText($post['content']);
            foreach ($mentions as $mention) {
                $allMentions[strtolower($mention)] = $mention;
            }
        }

        $mentionMap = getUsersByUsernames(array_values($allMentions));

        $posts = [];
        foreach ($rawPosts as $post) {
            $postMedia = $mediaMap[$post['id']] ?? [];
            if (empty($postMedia) && $post['media_url'] !== '') {
                $postMedia[] = [
                    'id' => 0,
                    'path' => $post['media_url'],
                    'mime' => '',
                    'position' => 0,
                ];
            }

            if ($post['content_type'] === 'poll') {
                $snapshot = $pollSnapshot[$post['id']] ?? [
                    'options' => [],
                    'total_votes' => 0,
                    'viewer_choice' => null,
                ];

                $viewerChoice = array_key_exists('viewer_choice', $snapshot) ? $snapshot['viewer_choice'] : null;
                $totalVotes = (int) ($snapshot['total_votes'] ?? 0);
                $optionsWithStats = [];

                foreach ($post['poll_options'] as $index => $optionLabel) {
                    $votes = (int) ($snapshot['options'][$index] ?? 0);
                    $percentage = $totalVotes > 0 ? (int) round(($votes / $totalVotes) * 100) : 0;
                    if ($percentage < 0) {
                        $percentage = 0;
                    } elseif ($percentage > 100) {
                        $percentage = 100;
                    }

                    $optionsWithStats[] = [
                        'label' => $optionLabel,
                        'votes' => $votes,
                        'percentage' => $percentage,
                        'index' => $index,
                        'is_selected' => $viewerChoice === $index,
                    ];
                }

                $post['poll_options'] = $optionsWithStats;
                $post['poll_total_votes'] = $totalVotes;
                $post['poll_viewer_choice'] = $viewerChoice;
                $post['viewer_has_voted_poll'] = $viewerChoice !== null;
            } else {
                $post['poll_total_votes'] = 0;
                $post['poll_viewer_choice'] = null;
                $post['viewer_has_voted_poll'] = false;
            }

            $contentRender = renderCommunityContent($post['content'], $mentionMap);

            $post['media'] = $postMedia;
            $post['content_rendered'] = $contentRender['html'];
            $post['mentions'] = $contentRender['mentions'];

            if ($post['content_type'] === 'news') {
                $post['shared_news'] = [
                    'id' => isset($post['shared_news_id']) ? (int) $post['shared_news_id'] : 0,
                    'title' => trim((string) ($post['shared_news_title'] ?? '')),
                    'slug' => trim((string) ($post['shared_news_slug'] ?? '')),
                    'excerpt' => trim((string) ($post['shared_news_excerpt'] ?? '')),
                    'tag' => trim((string) ($post['shared_news_tag'] ?? '')),
                    'image' => trim((string) ($post['shared_news_image'] ?? '')),
                    'source_url' => trim((string) ($post['shared_news_source_url'] ?? '')),
                    'published_at' => $post['shared_news_published_at'] ?? null,
                ];
            } else {
                $post['shared_news'] = null;
            }

            $posts[] = $post;
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
        $statement = $pdo->prepare('SELECT id, user_id, status FROM community_posts WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $postId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        $status = $row['status'] ?? 'published';
        if ($status !== 'published') {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'status' => $status,
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

function submitCommunityPollVote(int $postId, int $userId, int $optionIndex): array
{
    if ($postId <= 0 || $userId <= 0) {
        return ['success' => false, 'message' => 'Richiesta di voto non valida.'];
    }

    $normalizedOption = $optionIndex >= 0 ? $optionIndex : -1;
    if ($normalizedOption < 0) {
        return ['success' => false, 'message' => 'Seleziona una delle opzioni disponibili.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio sondaggi non disponibile al momento.'];
    }

    if (!communityPostsExtendedSchemaAvailable($pdo)) {
        return ['success' => false, 'message' => 'Questo sondaggio non è stato ancora aggiornato allo schema più recente.'];
    }

    if (!communityPollVotesTableAvailable($pdo)) {
        return ['success' => false, 'message' => 'Completa la migrazione `community_poll_votes` prima di raccogliere voti.'];
    }

    try {
        $statement = $pdo->prepare('SELECT content_type, poll_options, status FROM community_posts WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $postId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['success' => false, 'message' => 'Post non trovato o rimosso.'];
        }

        if (($row['content_type'] ?? '') !== 'poll') {
            return ['success' => false, 'message' => 'Questo post non prevede votazioni.'];
        }

        if (($row['status'] ?? 'published') !== 'published') {
            return ['success' => false, 'message' => 'Il sondaggio non è ancora pubblico.'];
        }

        $rawOptions = $row['poll_options'] ?? null;
        $pollOptions = [];

        if ($rawOptions !== null && $rawOptions !== '') {
            try {
                $decoded = json_decode((string) $rawOptions, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    foreach ($decoded as $option) {
                        $label = trim((string) $option);
                        if ($label !== '') {
                            $pollOptions[] = mb_substr($label, 0, 120);
                        }
                    }
                }
            } catch (\JsonException $exception) {
                error_log('[BianconeriHub] Invalid poll options JSON for post ' . $postId . ': ' . $exception->getMessage());
            }
        }

        if (!isset($pollOptions[$normalizedOption])) {
            return ['success' => false, 'message' => 'Opzione del sondaggio non valida.'];
        }

        $insert = $pdo->prepare(
            'INSERT INTO community_poll_votes (post_id, user_id, option_index)
             VALUES (:post_id, :user_id, :option_index)
             ON DUPLICATE KEY UPDATE option_index = VALUES(option_index), updated_at = CURRENT_TIMESTAMP'
        );

        $insert->execute([
            'post_id' => $postId,
            'user_id' => $userId,
            'option_index' => $normalizedOption,
        ]);

        return [
            'success' => true,
            'option_index' => $normalizedOption,
            'label' => $pollOptions[$normalizedOption],
        ];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to store poll vote: ' . $exception->getMessage());
    }

    return ['success' => false, 'message' => 'Impossibile registrare il voto. Riprova più tardi.'];
}

function findCommunityCommentById(int $commentId): ?array
{
    if ($commentId <= 0) {
        return null;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return null;
    }

    try {
        $statement = $pdo->prepare('SELECT id, post_id, user_id, parent_comment_id FROM community_post_comments WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $commentId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'post_id' => (int) ($row['post_id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'parent_comment_id' => isset($row['parent_comment_id']) ? (int) $row['parent_comment_id'] : null,
        ];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load community comment #' . $commentId . ': ' . $exception->getMessage());
    }

    return null;
}

function addCommunityComment(int $postId, int $userId, string $content, int $parentCommentId = 0): array
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

    $parentId = $parentCommentId > 0 ? $parentCommentId : 0;
    $parentComment = null;

    if ($parentId > 0) {
        $parentComment = findCommunityCommentById($parentId);
        if (!$parentComment || (int) $parentComment['post_id'] !== $postId) {
            return ['success' => false, 'message' => 'Il commento a cui vuoi rispondere non esiste più.'];
        }

        // Evita catene troppo lunghe obbligando le risposte ad agganciarsi al commento principale
        if (!empty($parentComment['parent_comment_id'])) {
            $rootParentId = (int) $parentComment['parent_comment_id'];
            if ($rootParentId > 0) {
                $parentId = $rootParentId;
            }
        }
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio momentaneamente non disponibile.'];
    }

    try {
        $statement = $pdo->prepare('INSERT INTO community_post_comments (post_id, user_id, content, parent_comment_id) VALUES (:post_id, :user_id, :content, :parent_id)');
        $statement->execute([
            'post_id' => $postId,
            'user_id' => $userId,
            'content' => $trimmed,
            'parent_id' => $parentId > 0 ? $parentId : null,
        ]);

        return [
            'success' => true,
            'comment_id' => (int) $pdo->lastInsertId(),
            'reply_to' => $parentId,
        ];
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to add community comment: ' . $exception->getMessage());
    }

    return ['success' => false, 'message' => 'Impossibile pubblicare il commento al momento.'];
}

function getCommunityComments(int $postId, int $limit = 20, int $viewerId = 0): array
{
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return [];
    }

    $limit = max(1, min($limit, 100));
    $totalLimit = min($limit * 5, 200);

    try {
        $reactionsAvailable = communityCommentReactionsTableAvailable($pdo);

    $selectFields = 'c.id, c.parent_comment_id, c.content, c.created_at, c.updated_at, c.post_id, c.user_id, u.username, u.first_name, u.last_name, u.badge';
        if ($reactionsAvailable) {
            $selectFields .= ', COALESCE(l.likes_count, 0) AS likes_count';
            if ($viewerId > 0) {
                $selectFields .= ', CASE WHEN ul.user_id IS NULL THEN 0 ELSE 1 END AS has_liked';
            } else {
                $selectFields .= ', 0 AS has_liked';
            }
        } else {
            $selectFields .= ', 0 AS likes_count, 0 AS has_liked';
        }

        $sql = 'SELECT ' . $selectFields . '
                FROM community_post_comments c
                INNER JOIN users u ON u.id = c.user_id';

        if ($reactionsAvailable) {
            $sql .= '
                LEFT JOIN (
                    SELECT comment_id, COUNT(*) AS likes_count
                    FROM community_comment_reactions
                    GROUP BY comment_id
                ) l ON l.comment_id = c.id';

            if ($viewerId > 0) {
                $sql .= '
                LEFT JOIN community_comment_reactions ul ON ul.comment_id = c.id AND ul.user_id = :viewer_id';
            }
        }

        $sql .= '
                WHERE c.post_id = :post_id
                ORDER BY c.created_at ASC, c.id ASC
                LIMIT :limit';

        $statement = $pdo->prepare($sql);
        $statement->bindValue(':post_id', $postId, PDO::PARAM_INT);
        if ($viewerId > 0 && $reactionsAvailable) {
            $statement->bindValue(':viewer_id', $viewerId, PDO::PARAM_INT);
        }
        $statement->bindValue(':limit', $totalLimit, PDO::PARAM_INT);
        $statement->execute();

        $byId = [];
        foreach ($statement as $row) {
            $commentId = (int) ($row['id'] ?? 0);
            if ($commentId <= 0) {
                continue;
            }

            $parentId = isset($row['parent_comment_id']) ? (int) $row['parent_comment_id'] : 0;

            $byId[$commentId] = [
                'id' => $commentId,
                'post_id' => (int) ($row['post_id'] ?? $postId),
                'parent_id' => $parentId > 0 ? $parentId : null,
                'author' => buildUserDisplayName($row['first_name'] ?? null, $row['last_name'] ?? null, (string) ($row['username'] ?? 'Tifoso')),
                'author_username' => isset($row['username']) ? (string) $row['username'] : '',
                'author_first_name' => isset($row['first_name']) ? (string) $row['first_name'] : null,
                'author_last_name' => isset($row['last_name']) ? (string) $row['last_name'] : null,
                'badge' => $row['badge'] ?? 'Tifoso',
                'content' => $row['content'] ?? '',
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
                'updated_at' => isset($row['updated_at']) ? normalizeToTimestamp($row['updated_at']) : null,
                'likes' => (int) ($row['likes_count'] ?? 0),
                'has_liked' => ((int) ($row['has_liked'] ?? 0)) === 1,
                'replies' => [],
            ];
        }

        if (empty($byId)) {
            return [];
        }

        foreach ($byId as $commentId => &$comment) {
            $parentId = $comment['parent_id'];
            if ($parentId !== null && isset($byId[$parentId])) {
                $byId[$parentId]['replies'][] = &$comment;
            }
        }
        unset($comment);

        $topLevel = [];
        foreach ($byId as $comment) {
            if ($comment['parent_id'] !== null && isset($byId[$comment['parent_id']])) {
                continue;
            }
            $topLevel[] = $comment;
        }
        if (count($topLevel) > $limit) {
            $topLevel = array_slice($topLevel, 0, $limit);
        }

        return $topLevel;
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load community comments: ' . $exception->getMessage());
    }

    return [];
}

function toggleCommunityCommentReaction(int $commentId, int $userId): array
{
    if ($commentId <= 0 || $userId <= 0) {
        return ['success' => false, 'message' => 'Reazione non valida.'];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio momentaneamente non disponibile.'];
    }

    if (!communityCommentReactionsTableAvailable($pdo)) {
        return ['success' => false, 'message' => 'Le reazioni ai commenti non sono disponibili in questo momento.'];
    }

    $comment = findCommunityCommentById($commentId);
    if (!$comment) {
        return ['success' => false, 'message' => 'Il commento non esiste più.'];
    }

    try {
        $pdo->beginTransaction();

        $check = $pdo->prepare('SELECT 1 FROM community_comment_reactions WHERE comment_id = :comment_id AND user_id = :user_id LIMIT 1');
        $check->execute([
            'comment_id' => $commentId,
            'user_id' => $userId,
        ]);

        if ($check->fetchColumn()) {
            $delete = $pdo->prepare('DELETE FROM community_comment_reactions WHERE comment_id = :comment_id AND user_id = :user_id');
            $delete->execute([
                'comment_id' => $commentId,
                'user_id' => $userId,
            ]);

            $pdo->commit();

            return [
                'success' => true,
                'state' => 'removed',
                'post_id' => (int) ($comment['post_id'] ?? 0),
            ];
        }

        $insert = $pdo->prepare('INSERT INTO community_comment_reactions (comment_id, user_id) VALUES (:comment_id, :user_id)');
        $insert->execute([
            'comment_id' => $commentId,
            'user_id' => $userId,
        ]);

        $pdo->commit();

        return [
            'success' => true,
            'state' => 'added',
            'post_id' => (int) ($comment['post_id'] ?? 0),
        ];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[BianconeriHub] Failed to toggle comment reaction: ' . $exception->getMessage());
    }

    return ['success' => false, 'message' => 'Impossibile aggiornare la reazione in questo momento.'];
}

function resolveCommunityUploadDirectory(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'community';
}

function communityUploadErrorMessage(int $error): string
{
    switch ($error) {
        case UPLOAD_ERR_INI_SIZE:
            return 'La foto supera la dimensione massima consentita dal server.';
        case UPLOAD_ERR_FORM_SIZE:
            return 'La foto è troppo grande. Dimensione massima 5 MB.';
        case UPLOAD_ERR_PARTIAL:
            return 'Il caricamento è stato interrotto. Riprova.';
        case UPLOAD_ERR_NO_FILE:
            return 'Nessun file caricato.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Cartella temporanea non disponibile sul server.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Impossibile salvare la foto sul server.';
        case UPLOAD_ERR_EXTENSION:
            return 'Caricamento interrotto da un’estensione PHP.';
        default:
            return 'Caricamento immagine non riuscito. Riprova.';
    }
}

function detectCommunityUploadMime(string $path): string
{
    $mime = '';
    $finfo = null;
    $detected = null;

    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE) ?: null;
        if ($finfo !== null) {
            $detected = @finfo_file($finfo, $path);
            if (is_string($detected)) {
                $mime = $detected;
            }
            finfo_close($finfo);
        }
    }

    if ($mime === '') {
        $imageInfo = @getimagesize($path);
        if (is_array($imageInfo) && isset($imageInfo['mime']) && is_string($imageInfo['mime'])) {
            $mime = $imageInfo['mime'];
        }
    }

    return strtolower($mime);
}

function communityExtensionFromMime(string $mime): ?string
{
    switch (strtolower($mime)) {
        case 'image/jpeg':
            return 'jpg';
        case 'image/png':
            return 'png';
        case 'image/webp':
            return 'webp';
        case 'image/gif':
            return 'gif';
        default:
            return null;
    }
}

function handleCommunityPhotoUpload(array $file): array
{
    $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'message' => ''];
    }

    if ($error !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => communityUploadErrorMessage($error)];
    }

    $tmpName = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['success' => false, 'message' => 'Caricamento immagine non valido.'];
    }

    $size = isset($file['size']) ? (int) $file['size'] : 0;
    $maxSize = 5 * 1024 * 1024;
    if ($size <= 0 || $size > $maxSize) {
        return ['success' => false, 'message' => 'La foto deve pesare al massimo 5 MB.'];
    }

    $mime = detectCommunityUploadMime($tmpName);
    $extension = communityExtensionFromMime($mime);

    if ($extension === null) {
        return ['success' => false, 'message' => 'Formato immagine non supportato. Usa JPEG, PNG, WEBP o GIF.'];
    }

    $maxOptimizedBytes = 200 * 1024;
    $isGif = $mime === 'image/gif';

    $baseDir = resolveCommunityUploadDirectory();
    $subDir = date('Y') . DIRECTORY_SEPARATOR . date('m');
    $targetDir = $baseDir . DIRECTORY_SEPARATOR . $subDir;

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return ['success' => false, 'message' => 'Impossibile creare la cartella di destinazione sul server.'];
        }
    }

    try {
        $random = bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        $random = bin2hex(openssl_random_pseudo_bytes(8));
    }
    $baseFilename = date('YmdHis') . '-' . $random;
    $finalFilename = null;
    $absolutePath = null;
    $finalMime = $mime;

    if ($isGif) {
        if ($size > $maxOptimizedBytes) {
            return ['success' => false, 'message' => 'Le GIF devono pesare al massimo 200 KB. Riduci l\'animazione e riprova.'];
        }

        $finalFilename = $baseFilename . '.gif';
        $absolutePath = $targetDir . DIRECTORY_SEPARATOR . $finalFilename;

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            return ['success' => false, 'message' => 'Impossibile salvare la foto caricata.'];
        }
    } else {
        $optimized = optimiseCommunityImageWithGd($tmpName, $mime, $targetDir, $baseFilename, $maxOptimizedBytes);

        if ($optimized['success']) {
            $finalFilename = $optimized['filename'];
            $absolutePath = $targetDir . DIRECTORY_SEPARATOR . $finalFilename;
            $finalMime = $optimized['mime'] ?? $finalMime;
            @unlink($tmpName);
        } elseif ($size <= $maxOptimizedBytes) {
            $finalFilename = $baseFilename . '.' . $extension;
            $absolutePath = $targetDir . DIRECTORY_SEPARATOR . $finalFilename;

            if (!move_uploaded_file($tmpName, $absolutePath)) {
                return ['success' => false, 'message' => 'Impossibile salvare la foto caricata.'];
            }
        } else {
            $errorMessage = $optimized['message'] ?? 'Impossibile ottimizzare automaticamente la foto. Riducila manualmente e riprova.';
            return ['success' => false, 'message' => $errorMessage];
        }
    }

    if (!$absolutePath || !is_file($absolutePath)) {
        return ['success' => false, 'message' => 'Impossibile salvare la foto caricata.'];
    }

    @chmod($absolutePath, 0644);

    $finalSize = @filesize($absolutePath);
    if ($finalSize !== false && $finalSize > $maxOptimizedBytes && $finalMime !== 'image/gif') {
        @unlink($absolutePath);
        return ['success' => false, 'message' => 'Non è stato possibile ridurre la foto sotto i 200 KB. Riducila manualmente e riprova.'];
    }

    $relativeSubDir = str_replace(DIRECTORY_SEPARATOR, '/', $subDir);
    $relativePath = 'uploads/community/' . $relativeSubDir . '/' . $finalFilename;

    return [
        'success' => true,
        'relative_path' => $relativePath,
        'absolute_path' => $absolutePath,
        'mime' => $finalMime,
    ];
}

function handleCommunityClipboardUpload(string $dataUrl, string $originalName = ''): array
{
    $trimmed = trim($dataUrl);
    if ($trimmed === '') {
        return ['success' => false, 'message' => ''];
    }

    if (strpos($trimmed, 'base64,') === false) {
        return ['success' => false, 'message' => 'Formato immagine non riconosciuto.'];
    }

    [$header, $base64Data] = explode('base64,', $trimmed, 2);
    if (!preg_match('#^data:(image/(?:jpeg|png|webp|gif))#i', $header, $matches)) {
        return ['success' => false, 'message' => 'Formato immagine non supportato. Usa JPEG, PNG, WEBP o GIF.'];
    }

    $mime = strtolower($matches[1]);
    $binary = base64_decode($base64Data, true);
    if ($binary === false) {
        return ['success' => false, 'message' => 'Impossibile leggere i dati incollati.'];
    }

    $maxSize = 5 * 1024 * 1024;
    if (strlen($binary) > $maxSize) {
        return ['success' => false, 'message' => 'La foto inserita dagli appunti è troppo grande (max 5 MB).'];
    }

    $imageInfo = @getimagesizefromstring($binary);
    if (!$imageInfo || empty($imageInfo['mime'])) {
        return ['success' => false, 'message' => 'I dati incollati non sono un’immagine valida.'];
    }

    $mime = strtolower($imageInfo['mime']);
    $extension = communityExtensionFromMime($mime);

    if ($extension === null) {
        return ['success' => false, 'message' => 'Formato immagine non supportato. Usa JPEG, PNG, WEBP o GIF.'];
    }

    $maxOptimizedBytes = 200 * 1024;
    $binaryLength = strlen($binary);

    $baseDir = resolveCommunityUploadDirectory();
    $subDir = date('Y') . DIRECTORY_SEPARATOR . date('m');
    $targetDir = $baseDir . DIRECTORY_SEPARATOR . $subDir;

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return ['success' => false, 'message' => 'Impossibile creare la cartella di destinazione sul server.'];
        }
    }

    try {
        $random = bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        $random = bin2hex(openssl_random_pseudo_bytes(8));
    }

    $originalBase = trim(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'clipboard';
    $sanitizedBase = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($originalBase));
    $sanitizedBase = trim($sanitizedBase, '-') ?: 'clipboard';
    $baseFilename = sprintf('%s-%s-%s', date('YmdHis'), $sanitizedBase, $random);
    $finalFilename = null;
    $absolutePath = null;
    $finalMime = $mime;

    $temporary = @tempnam($targetDir, 'clip-');
    if ($temporary === false) {
        return ['success' => false, 'message' => 'Impossibile salvare l’immagine incollata.'];
    }

    if (file_put_contents($temporary, $binary) === false) {
        @unlink($temporary);
        return ['success' => false, 'message' => 'Impossibile salvare l’immagine incollata.'];
    }

    if ($mime === 'image/gif') {
        if ($binaryLength > $maxOptimizedBytes) {
            @unlink($temporary);
            return ['success' => false, 'message' => 'Le GIF devono pesare al massimo 200 KB. Riduci l\'animazione e riprova.'];
        }

        $finalFilename = $baseFilename . '.gif';
        $absolutePath = $targetDir . DIRECTORY_SEPARATOR . $finalFilename;

        if (!@rename($temporary, $absolutePath)) {
            if (!@copy($temporary, $absolutePath)) {
                @unlink($temporary);
                return ['success' => false, 'message' => 'Impossibile salvare l’immagine incollata.'];
            }
            @unlink($temporary);
        }
    } else {
        $optimized = optimiseCommunityImageWithGd($temporary, $mime, $targetDir, $baseFilename, $maxOptimizedBytes);
        @unlink($temporary);

        if ($optimized['success']) {
            $finalFilename = $optimized['filename'];
            $absolutePath = $targetDir . DIRECTORY_SEPARATOR . $finalFilename;
            $finalMime = $optimized['mime'] ?? $finalMime;
        } elseif ($binaryLength <= $maxOptimizedBytes) {
            $finalFilename = $baseFilename . '.' . $extension;
            $absolutePath = $targetDir . DIRECTORY_SEPARATOR . $finalFilename;

            if (file_put_contents($absolutePath, $binary) === false) {
                return ['success' => false, 'message' => 'Impossibile salvare l’immagine incollata.'];
            }
        } else {
            $errorMessage = $optimized['message'] ?? 'Impossibile ottimizzare automaticamente la foto. Riducila manualmente e riprova.';
            return ['success' => false, 'message' => $errorMessage];
        }
    }

    if (!$absolutePath || !is_file($absolutePath)) {
        return ['success' => false, 'message' => 'Impossibile salvare l’immagine incollata.'];
    }

    @chmod($absolutePath, 0644);

    $finalSize = @filesize($absolutePath);
    if ($finalSize !== false && $finalSize > $maxOptimizedBytes && $finalMime !== 'image/gif') {
        @unlink($absolutePath);
        return ['success' => false, 'message' => 'Non è stato possibile ridurre la foto sotto i 200 KB. Riducila manualmente e riprova.'];
    }

    $relativeSubDir = str_replace(DIRECTORY_SEPARATOR, '/', $subDir);
    $relativePath = 'uploads/community/' . $relativeSubDir . '/' . $finalFilename;

    return [
        'success' => true,
        'relative_path' => $relativePath,
        'absolute_path' => $absolutePath,
        'mime' => $finalMime,
    ];
}

function communityMediaTableAvailable(?PDO $connection = null): bool
{
    static $cache;

    if ($cache !== null) {
        return $cache;
    }

    $pdo = $connection ?? getDatabaseConnection();
    if (!$pdo) {
        $cache = false;
        return $cache;
    }

    try {
        $pdo->query('SELECT 1 FROM community_post_media LIMIT 1');
        $cache = true;
    } catch (PDOException $exception) {
        $sqlState = $exception->getCode();
        if ($sqlState === '42S02' || stripos($exception->getMessage(), 'community_post_media') !== false) {
            $cache = false;
        } else {
            error_log('[BianconeriHub] Failed checking community_post_media availability: ' . $exception->getMessage());
            $cache = false;
        }
    }

    return $cache;
}

function communityPostsExtendedSchemaAvailable(?PDO $connection = null): bool
{
    static $cache;

    if ($cache !== null) {
        return $cache;
    }

    $pdo = $connection ?? getDatabaseConnection();
    if (!$pdo) {
        $cache = false;
        return $cache;
    }

    try {
        $pdo->query('SELECT status, poll_question, poll_options, story_title, story_caption, story_credit, scheduled_for, published_at, shared_news_id, shared_news_title, shared_news_slug, shared_news_excerpt, shared_news_tag, shared_news_image, shared_news_source_url, shared_news_published_at FROM community_posts LIMIT 1');
        $cache = true;
    } catch (PDOException $exception) {
        $sqlState = $exception->getCode();
        if ($sqlState === '42S22' || stripos($exception->getMessage(), 'unknown column') !== false) {
            $cache = false;
        } else {
            error_log('[BianconeriHub] Failed checking community_posts extended schema: ' . $exception->getMessage());
            $cache = false;
        }
    }

    return $cache;
}

function communityPollVotesTableAvailable(?PDO $connection = null): bool
{
    static $cache;

    if ($cache !== null) {
        return $cache;
    }

    $pdo = $connection ?? getDatabaseConnection();
    if (!$pdo) {
        $cache = false;
        return $cache;
    }

    try {
        $pdo->query('SELECT 1 FROM community_poll_votes LIMIT 1');
        $cache = true;
    } catch (PDOException $exception) {
        $sqlState = $exception->getCode();
        if ($sqlState === '42S02' || stripos($exception->getMessage(), 'community_poll_votes') !== false) {
            $cache = false;
        } else {
            error_log('[BianconeriHub] Failed checking community_poll_votes availability: ' . $exception->getMessage());
            $cache = false;
        }
    }

    return $cache;
}

function communityCommentReactionsTableAvailable(?PDO $connection = null): bool
{
    static $cache;

    if ($cache !== null) {
        return $cache;
    }

    $pdo = $connection ?? getDatabaseConnection();
    if (!$pdo) {
        $cache = false;
        return $cache;
    }

    try {
        $pdo->query('SELECT 1 FROM community_comment_reactions LIMIT 1');
        $cache = true;
    } catch (PDOException $exception) {
        $sqlState = $exception->getCode();
        if ($sqlState === '42S02' || stripos($exception->getMessage(), 'community_comment_reactions') !== false) {
            $cache = false;
        } else {
            error_log('[BianconeriHub] Failed checking community_comment_reactions availability: ' . $exception->getMessage());
            $cache = false;
        }
    }

    return $cache;
}

function communityPostMentionsTableAvailable(?PDO $connection = null): bool
{
    static $cache;

    if ($cache !== null) {
        return $cache;
    }

    $pdo = $connection ?? getDatabaseConnection();
    if (!$pdo) {
        $cache = false;
        return $cache;
    }

    try {
        $pdo->query('SELECT 1 FROM community_post_mentions LIMIT 1');
        $cache = true;
    } catch (PDOException $exception) {
        $sqlState = $exception->getCode();
        if ($sqlState === '42S02' || stripos($exception->getMessage(), 'community_post_mentions') !== false) {
            $cache = false;
        } else {
            error_log('[BianconeriHub] Failed checking community_post_mentions availability: ' . $exception->getMessage());
            $cache = false;
        }
    }

    return $cache;
}

function replaceCommunityPostMentions(PDO $pdo, int $postId, int $authorId, array $mentionUserIds): void
{
    if ($postId <= 0 || !communityPostMentionsTableAvailable($pdo)) {
        return;
    }

    $uniqueIds = [];
    foreach ($mentionUserIds as $userId) {
        $intId = (int) $userId;
        if ($intId > 0 && $intId !== $authorId) {
            $uniqueIds[$intId] = $intId;
        }
    }

    try {
        $statement = $pdo->prepare('SELECT id, mentioned_user_id FROM community_post_mentions WHERE post_id = :post_id');
        $statement->execute(['post_id' => $postId]);

        $existing = [];
        foreach ($statement as $row) {
            $mentionedId = (int) ($row['mentioned_user_id'] ?? 0);
            if ($mentionedId <= 0) {
                continue;
            }

            $existing[$mentionedId] = (int) ($row['id'] ?? 0);
        }

        $toDelete = [];
        foreach ($existing as $mentionedId => $rowId) {
            if (!isset($uniqueIds[$mentionedId])) {
                $toDelete[] = $rowId;
            }
        }

        if (!empty($toDelete)) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $delete = $pdo->prepare('DELETE FROM community_post_mentions WHERE id IN (' . $placeholders . ')');
            $delete->execute($toDelete);
        }

        $toInsert = array_diff(array_values($uniqueIds), array_keys($existing));
        if (!empty($toInsert)) {
            $insert = $pdo->prepare('INSERT INTO community_post_mentions (post_id, author_id, mentioned_user_id) VALUES (:post_id, :author_id, :mentioned_user_id)');
            foreach ($toInsert as $userId) {
                $insert->execute([
                    'post_id' => $postId,
                    'author_id' => $authorId,
                    'mentioned_user_id' => $userId,
                ]);
            }
        }
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to sync community post mentions: ' . $exception->getMessage());
    }
}

function markCommunityMentionsAsNotified(PDO $pdo, array $mentionIds): void
{
    $uniqueIds = [];
    foreach ($mentionIds as $mentionId) {
        $intId = (int) $mentionId;
        if ($intId > 0) {
            $uniqueIds[$intId] = $intId;
        }
    }

    if (empty($uniqueIds)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));

    try {
        $statement = $pdo->prepare('UPDATE community_post_mentions SET notified_at = NOW() WHERE id IN (' . $placeholders . ')');
        $statement->execute(array_values($uniqueIds));
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to mark community mentions as notified: ' . $exception->getMessage());
    }
}

function getCommunityMentionsSummary(int $userId, int $limit = 5, int $offset = 0): array
{
    $summary = [
        'items' => [],
        'total' => 0,
        'unread_count' => 0,
        'has_more' => false,
        'limit' => max(1, (int) $limit),
        'offset' => max(0, (int) $offset),
        'timestamp' => time(),
    ];

    $userId = (int) $userId;
    if ($userId <= 0) {
        return $summary;
    }

    $summary['limit'] = max(1, min($summary['limit'], 25));
    $summary['offset'] = max(0, $summary['offset']);

    $pdo = getDatabaseConnection();
    if (!$pdo || !communityPostMentionsTableAvailable($pdo)) {
        return $summary;
    }

    try {
        $countStatement = $pdo->prepare(
            'SELECT COUNT(*) AS total_mentions,
                    SUM(CASE WHEN viewed_at IS NULL THEN 1 ELSE 0 END) AS unread_mentions
             FROM community_post_mentions
             WHERE mentioned_user_id = :user_id'
        );
        $countStatement->execute(['user_id' => $userId]);
        $counts = $countStatement->fetch();
        $summary['total'] = (int) ($counts['total_mentions'] ?? 0);
        $summary['unread_count'] = (int) ($counts['unread_mentions'] ?? 0);
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to count community mentions: ' . $exception->getMessage());
        return $summary;
    }

    if ($summary['total'] === 0) {
        return $summary;
    }

    $fetchLimit = $summary['limit'] + 1;
    $offset = $summary['offset'];

    $sql = <<<SQL
SELECT
    m.id,
    m.post_id,
    m.author_id,
    m.created_at,
    m.viewed_at,
    p.content,
    p.content_type,
    p.created_at AS post_created_at,
    u.username AS author_username,
    u.first_name,
    u.last_name,
    u.badge,
    u.avatar_url
FROM community_post_mentions m
INNER JOIN community_posts p ON p.id = m.post_id
INNER JOIN users u ON u.id = m.author_id
WHERE m.mentioned_user_id = :user_id
ORDER BY m.created_at DESC, m.id DESC
LIMIT $fetchLimit OFFSET $offset
SQL;

    try {
        $statement = $pdo->prepare($sql);
        $statement->execute(['user_id' => $userId]);
        $rows = $statement->fetchAll();
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to load community mentions: ' . $exception->getMessage());
        return $summary;
    }

    if (!is_array($rows) || empty($rows)) {
        return $summary;
    }

    $summary['has_more'] = count($rows) > $summary['limit'];
    if ($summary['has_more']) {
        $rows = array_slice($rows, 0, $summary['limit']);
    }

    $items = [];

    foreach ($rows as $row) {
        $mentionId = (int) ($row['id'] ?? 0);
        $postId = (int) ($row['post_id'] ?? 0);
        $authorId = (int) ($row['author_id'] ?? 0);

        if ($mentionId <= 0 || $postId <= 0 || $authorId <= 0) {
            continue;
        }

        $authorUsername = trim((string) ($row['author_username'] ?? ''));
        $authorDisplay = buildUserDisplayName($row['first_name'] ?? null, $row['last_name'] ?? null, $authorUsername);

        $content = (string) ($row['content'] ?? '');
        $excerpt = sanitizeFeedExcerpt($content, 160);
        if ($excerpt === '') {
            $excerpt = 'Ti ha menzionato in un post della community.';
        }

        $createdAt = normalizeToTimestamp($row['created_at'] ?? time());

        $items[] = [
            'id' => $mentionId,
            'post_id' => $postId,
            'permalink' => '?page=community#post-' . $postId,
            'author_username' => $authorUsername,
            'author_display_name' => $authorDisplay !== '' ? $authorDisplay : ($authorUsername !== '' ? $authorUsername : 'Membro'),
            'author_badge' => $row['badge'] ?? 'Tifoso',
            'author_avatar_url' => trim((string) ($row['avatar_url'] ?? '')),
            'excerpt' => $excerpt,
            'content_type' => $row['content_type'] ?? 'text',
            'created_at' => $createdAt,
            'created_human' => getHumanTimeDiff($createdAt),
            'is_unread' => empty($row['viewed_at']),
        ];
    }

    $summary['items'] = $items;

    return $summary;
}

function markCommunityMentionsAsViewed(int $userId, ?array $mentionIds = null): int
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return 0;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo || !communityPostMentionsTableAvailable($pdo)) {
        return 0;
    }

    if (is_array($mentionIds) && !empty($mentionIds)) {
        $uniqueIds = [];
        foreach ($mentionIds as $mentionId) {
            $intId = (int) $mentionId;
            if ($intId > 0) {
                $uniqueIds[$intId] = $intId;
            }
        }

        if (empty($uniqueIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));
        $params = array_values($uniqueIds);
        $params[] = $userId;

        try {
            $statement = $pdo->prepare(
                'UPDATE community_post_mentions
                 SET viewed_at = NOW()
                 WHERE id IN (' . $placeholders . ')
                   AND mentioned_user_id = ?
                   AND viewed_at IS NULL'
            );
            $statement->execute($params);

            return (int) $statement->rowCount();
        } catch (PDOException $exception) {
            error_log('[BianconeriHub] Failed to mark specific mentions as viewed: ' . $exception->getMessage());
            return 0;
        }
    }

    try {
        $statement = $pdo->prepare(
            'UPDATE community_post_mentions
             SET viewed_at = NOW()
             WHERE mentioned_user_id = :user_id AND viewed_at IS NULL'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->rowCount();
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to mark mentions as viewed: ' . $exception->getMessage());
    }

    return 0;
}

function getCommunityPollVoteSnapshot(array $postIds, int $viewerId = 0): array
{
    $uniqueIds = [];
    foreach ($postIds as $postId) {
        $intId = (int) $postId;
        if ($intId > 0) {
            $uniqueIds[$intId] = $intId;
        }
    }

    if (empty($uniqueIds)) {
        return [];
    }

    $pdo = getDatabaseConnection();
    if (!$pdo || !communityPollVotesTableAvailable($pdo)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));

    $snapshot = [];

    try {
        $statement = $pdo->prepare(
            'SELECT post_id, option_index, COUNT(*) AS votes
             FROM community_poll_votes
             WHERE post_id IN (' . $placeholders . ')
             GROUP BY post_id, option_index'
        );
        $statement->execute(array_values($uniqueIds));

        foreach ($statement as $row) {
            $postId = (int) ($row['post_id'] ?? 0);
            $optionIndex = (int) ($row['option_index'] ?? 0);
            $votes = (int) ($row['votes'] ?? 0);

            if ($postId <= 0 || $optionIndex < 0) {
                continue;
            }

            if (!isset($snapshot[$postId])) {
                $snapshot[$postId] = [
                    'options' => [],
                    'total_votes' => 0,
                    'viewer_choice' => null,
                ];
            }

            $snapshot[$postId]['options'][$optionIndex] = $votes;
            $snapshot[$postId]['total_votes'] += $votes;
        }
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Failed to aggregate poll votes: ' . $exception->getMessage());
        return [];
    }

    if ($viewerId > 0) {
        try {
            $viewerStatement = $pdo->prepare(
                'SELECT post_id, option_index
                 FROM community_poll_votes
                 WHERE user_id = ? AND post_id IN (' . $placeholders . ')'
            );
            $viewerStatement->execute(array_merge([$viewerId], array_values($uniqueIds)));

            foreach ($viewerStatement as $row) {
                $postId = (int) ($row['post_id'] ?? 0);
                $optionIndex = (int) ($row['option_index'] ?? 0);
                if ($postId <= 0 || $optionIndex < 0) {
                    continue;
                }

                if (!isset($snapshot[$postId])) {
                    $snapshot[$postId] = [
                        'options' => [],
                        'total_votes' => 0,
                        'viewer_choice' => $optionIndex,
                    ];
                } else {
                    $snapshot[$postId]['viewer_choice'] = $optionIndex;
                }
            }
        } catch (PDOException $exception) {
            error_log('[BianconeriHub] Failed to load viewer poll votes: ' . $exception->getMessage());
        }
    }

    return $snapshot;
}

function addCommunityPost(int $userId, array $input, array $files = []): array
{
    global $appDebug;
    $lastExceptionMessage = null;

    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Sessione scaduta. Effettua nuovamente l\'accesso e riprova.'];
    }

    $author = findUserById($userId);
    if (!$author) {
        return ['success' => false, 'message' => 'Utente non trovato. Effettua nuovamente l\'accesso e riprova.'];
    }

    $message = trim((string) ($input['message'] ?? ''));
    $mode = strtolower((string) ($input['composer_mode'] ?? 'text'));
    $allowedModes = ['text', 'photo', 'poll', 'story', 'news'];
    if (!in_array($mode, $allowedModes, true)) {
        $mode = 'text';
    }

    $action = strtolower((string) ($input['composer_action'] ?? 'publish'));
    $allowedActions = ['publish', 'schedule', 'draft'];
    if (!in_array($action, $allowedActions, true)) {
        $action = 'publish';
    }

    $draftId = isset($input['draft_id']) ? (int) $input['draft_id'] : 0;
    $scheduleAtRaw = trim((string) ($input['schedule_at'] ?? ''));

    if ($message !== '' && mb_strlen($message) > 500) {
        return ['success' => false, 'message' => 'Il messaggio non può superare i 500 caratteri.'];
    }

    $storyTitle = trim((string) ($input['story_title'] ?? ''));
    $storyCaption = trim((string) ($input['story_caption'] ?? ''));
    $storyCredit = trim((string) ($input['story_credit'] ?? ''));

    $pollQuestion = trim((string) ($input['poll_question'] ?? ''));
    $pollOptionsRaw = $input['poll_options'] ?? [];
    if (!is_array($pollOptionsRaw)) {
        $pollOptionsRaw = [];
    }

    $pollOptions = [];
    foreach ($pollOptionsRaw as $option) {
        $optionText = trim((string) $option);
        if ($optionText !== '') {
            $pollOptions[] = mb_substr($optionText, 0, 120);
        }
    }

    $sharedNewsId = isset($input['shared_news_id']) ? (int) $input['shared_news_id'] : 0;
    $sharedNewsSlugInput = trim((string) ($input['shared_news_slug'] ?? ''));
    $sharedNewsMeta = [
        'id' => 0,
        'title' => '',
        'slug' => '',
        'excerpt' => '',
        'tag' => '',
        'image' => '',
        'source_url' => '',
        'published_at' => null,
    ];

    $mentionedUserIds = [];
    if ($message !== '') {
        $mentionUsernames = extractMentionsFromText($message);
        if (!empty($mentionUsernames)) {
            $mentionLookup = getUsersByUsernames($mentionUsernames);
            foreach ($mentionLookup as $mentionMeta) {
                $mentionedId = (int) ($mentionMeta['id'] ?? 0);
                if ($mentionedId > 0 && $mentionedId !== $userId) {
                    $mentionedUserIds[$mentionedId] = $mentionedId;
                }
            }
        }
    }

    $clipboardData = trim((string) ($input['media_clipboard'] ?? ''));
    $clipboardName = trim((string) ($input['media_clipboard_name'] ?? ''));

    $existingMediaInput = $input['existing_media'] ?? [];
    if (!is_array($existingMediaInput)) {
        $existingMediaInput = [];
    }
    $existingMediaIds = [];
    foreach ($existingMediaInput as $mediaId) {
        $id = (int) $mediaId;
        if ($id > 0) {
            $existingMediaIds[$id] = $id;
        }
    }

    $newUploadFiles = [];
    if (isset($files['media_files'])) {
        foreach (normalizeUploadedFiles($files['media_files']) as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $newUploadFiles[] = $file;
        }
    }

    if (isset($files['media_file'])) {
        foreach (normalizeUploadedFiles($files['media_file']) as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $newUploadFiles[] = $file;
        }
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Servizio momentaneamente non disponibile.'];
    }

    $extendedSchema = communityPostsExtendedSchemaAvailable($pdo);
    if (!$extendedSchema) {
        if ($action !== 'publish') {
            return ['success' => false, 'message' => 'La programmazione dei post non è disponibile sul database corrente.'];
        }

        if ($mode === 'poll') {
            return ['success' => false, 'message' => 'I sondaggi richiedono un aggiornamento del database.'];
        }

        if ($mode === 'story') {
            return ['success' => false, 'message' => 'Le storie richiedono un aggiornamento del database.'];
        }

        if ($mode === 'news') {
            return ['success' => false, 'message' => 'La condivisione delle news richiede un aggiornamento del database.'];
        }
    }

    $mediaTableAvailable = communityMediaTableAvailable($pdo);
    $maxAttachments = $mediaTableAvailable ? 4 : 1;
    if ($mode === 'story') {
        $maxAttachments = 1;
    }

    $attachmentBuffer = count($existingMediaIds) + count($newUploadFiles) + ($clipboardData !== '' ? 1 : 0);
    if ($attachmentBuffer > $maxAttachments) {
        if ($mode === 'story') {
            return ['success' => false, 'message' => 'Le storie possono includere una sola immagine.'];
        }

        if ($maxAttachments === 1) {
            return ['success' => false, 'message' => 'Puoi allegare una sola immagine per post al momento.'];
        }

        return ['success' => false, 'message' => 'Puoi allegare al massimo ' . $maxAttachments . ' immagini per post.'];
    }

    if ($mode === 'text' && $message === '') {
        return ['success' => false, 'message' => 'Il messaggio non può essere vuoto.'];
    }

    if (!in_array($mode, ['photo', 'story'], true) && $attachmentBuffer > 0) {
        return ['success' => false, 'message' => 'Gli allegati sono disponibili solo per i post fotografici o le storie.'];
    }

    if ($mode === 'photo' && $attachmentBuffer === 0) {
        return ['success' => false, 'message' => 'Aggiungi almeno un’immagine al tuo post fotografico.'];
    }

    if ($mode === 'story' && $attachmentBuffer === 0) {
        return ['success' => false, 'message' => 'Aggiungi un’immagine alla tua storia.'];
    }

    if ($mode === 'poll') {
        if ($pollQuestion === '') {
            return ['success' => false, 'message' => 'Inserisci la domanda del sondaggio.'];
        }

        if (count($pollOptions) < 2) {
            return ['success' => false, 'message' => 'Aggiungi almeno due opzioni al sondaggio.'];
        }

        $pollOptions = array_slice($pollOptions, 0, 4);
    } else {
        $pollQuestion = '';
        $pollOptions = [];
    }

    if ($mode === 'news') {
        $selectedNews = null;

        if ($sharedNewsId > 0) {
            $selectedNews = findNewsItemById($sharedNewsId);
        }

        if (!$selectedNews && $sharedNewsSlugInput !== '') {
            $selectedNews = findNewsItemBySlug($sharedNewsSlugInput);
        }

        if (!$selectedNews) {
            return ['success' => false, 'message' => 'Seleziona una notizia valida da condividere.'];
        }

        $sharedNewsMeta['id'] = (int) ($selectedNews['id'] ?? 0);
        if ($sharedNewsMeta['id'] <= 0) {
            return ['success' => false, 'message' => 'La notizia selezionata non è più disponibile.'];
        }

        $sharedNewsMeta['title'] = mb_substr(trim((string) ($selectedNews['title'] ?? '')), 0, 255);
        $sharedNewsMeta['slug'] = mb_substr(trim((string) ($selectedNews['slug'] ?? '')), 0, 255);
        $sharedNewsMeta['excerpt'] = mb_substr(trim(strip_tags((string) ($selectedNews['excerpt'] ?? ''))), 0, 400);
        if ($sharedNewsMeta['excerpt'] === '' && !empty($selectedNews['body'])) {
            $sharedNewsMeta['excerpt'] = mb_substr(trim(strip_tags((string) $selectedNews['body'])), 0, 400);
        }
        $sharedNewsMeta['tag'] = mb_substr(trim((string) ($selectedNews['tag'] ?? '')), 0, 120);
        $sharedNewsMeta['image'] = mb_substr(trim((string) ($selectedNews['image'] ?? ($selectedNews['image_path'] ?? ''))), 0, 255);
        $sharedNewsMeta['source_url'] = mb_substr(trim((string) ($selectedNews['source_url'] ?? '')), 0, 255);

        if ($sharedNewsMeta['title'] === '') {
            return ['success' => false, 'message' => 'La notizia selezionata non è più disponibile.'];
        }

        $newsPublishedRaw = $selectedNews['published_at'] ?? null;
        if ($newsPublishedRaw !== null && $newsPublishedRaw !== '') {
            try {
                $newsPublished = new DateTime($newsPublishedRaw);
                $sharedNewsMeta['published_at'] = $newsPublished->format('Y-m-d H:i:s');
            } catch (\Exception $exception) {
                $sharedNewsMeta['published_at'] = null;
            }
        } else {
            $sharedNewsMeta['published_at'] = null;
        }

        $sharedNewsId = $sharedNewsMeta['id'];
    } else {
        $sharedNewsId = 0;
        $sharedNewsMeta = [
            'id' => 0,
            'title' => '',
            'slug' => '',
            'excerpt' => '',
            'tag' => '',
            'image' => '',
            'source_url' => '',
            'published_at' => null,
        ];
    }

    $scheduleDate = null;
    if ($action === 'schedule') {
        if ($scheduleAtRaw === '') {
            return ['success' => false, 'message' => 'Imposta data e ora di pubblicazione.'];
        }

        $scheduleDate = DateTime::createFromFormat('Y-m-d\TH:i', $scheduleAtRaw);
        if (!$scheduleDate) {
            try {
                $scheduleDate = new DateTime($scheduleAtRaw);
            } catch (\Exception $exception) {
                $scheduleDate = false;
            }
        }

        if (!$scheduleDate) {
            return ['success' => false, 'message' => 'La data programmata non è valida.'];
        }

        $now = new DateTime('+5 minutes');
        if ($scheduleDate <= $now) {
            return ['success' => false, 'message' => 'Programma il post con almeno cinque minuti di anticipo.'];
        }
    }

    $existingPost = null;
    $isUpdating = false;
    if ($draftId > 0) {
        $existingPost = getCommunityPostForEditing($draftId, $userId);
        if (!$existingPost) {
            return ['success' => false, 'message' => 'Bozza non trovata o non autorizzata.'];
        }

        if (!in_array($existingPost['status'], ['draft', 'scheduled'], true)) {
            return ['success' => false, 'message' => 'Questo post è già stato pubblicato.'];
        }

        $isUpdating = true;
    }

    if ($mode !== 'story') {
        $storyTitle = '';
        $storyCaption = '';
        $storyCredit = '';
    } else {
        if ($isUpdating && $existingPost) {
            if ($storyTitle === '' && isset($existingPost['story_title'])) {
                $storyTitle = trim((string) ($existingPost['story_title'] ?? ''));
            }
            if ($storyCaption === '' && isset($existingPost['story_caption'])) {
                $storyCaption = trim((string) ($existingPost['story_caption'] ?? ''));
            }
            if ($storyCredit === '' && isset($existingPost['story_credit'])) {
                $storyCredit = trim((string) ($existingPost['story_credit'] ?? ''));
            }
        }

        if ($storyTitle === '') {
            return ['success' => false, 'message' => 'Aggiungi un titolo alla tua storia.'];
        }

        if (mb_strlen($storyTitle) > 80) {
            return ['success' => false, 'message' => 'Il titolo della storia può avere al massimo 80 caratteri.'];
        }

        if ($storyCaption === '') {
            return ['success' => false, 'message' => 'Aggiungi una descrizione alla tua storia.'];
        }

        if (mb_strlen($storyCaption) > 600) {
            return ['success' => false, 'message' => 'La descrizione della storia può avere al massimo 600 caratteri.'];
        }

        if ($storyCredit !== '' && mb_strlen($storyCredit) > 80) {
            return ['success' => false, 'message' => 'Il credito della storia può avere al massimo 80 caratteri.'];
        }
    }

    $pollOptionsJson = null;
    if ($mode === 'poll') {
        try {
            $pollOptionsJson = json_encode($pollOptions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $exception) {
            error_log('[BianconeriHub] Failed to encode poll options: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Impossibile salvare il sondaggio. Riprova.'];
        }
    }

    $uploads = [];
    foreach ($newUploadFiles as $file) {
        $uploadResult = handleCommunityPhotoUpload($file);
        if (!$uploadResult['success']) {
            foreach ($uploads as $upload) {
                deleteCommunityMediaFile($upload['relative_path']);
            }

            return ['success' => false, 'message' => $uploadResult['message']];
        }

        $uploads[] = [
            'relative_path' => $uploadResult['relative_path'],
            'absolute_path' => $uploadResult['absolute_path'],
            'mime' => $uploadResult['mime'],
        ];
    }

    $status = 'published';
    $scheduledFor = null;
    $publishedAt = null;

    if ($action === 'draft') {
        $status = 'draft';
    } elseif ($action === 'schedule' && $scheduleDate instanceof \DateTimeInterface) {
        $status = 'scheduled';
        $scheduledFor = clone $scheduleDate;
    }

    if ($status === 'published') {
        $publishedAt = new DateTime();
    } elseif ($status === 'scheduled' && $scheduleDate instanceof \DateTimeInterface) {
        $publishedAt = clone $scheduleDate;
    }

    if ($status !== 'scheduled') {
        $scheduledFor = null;
    }

    $existingMediaRecords = $mediaTableAvailable ? ($existingPost['media'] ?? []) : [];
    $keptMedia = [];
    foreach ($existingMediaRecords as $record) {
        if (isset($existingMediaIds[$record['id']])) {
            $keptMedia[] = $record;
        }
    }

    $mediaToDelete = [];
    foreach ($existingMediaRecords as $record) {
        if (!isset($existingMediaIds[$record['id']])) {
            $mediaToDelete[] = $record;
        }
    }

    if ($clipboardData !== '') {
        $clipboardUpload = handleCommunityClipboardUpload($clipboardData, $clipboardName);
        if (!$clipboardUpload['success']) {
            foreach ($uploads as $upload) {
                deleteCommunityMediaFile($upload['relative_path']);
            }

            return ['success' => false, 'message' => $clipboardUpload['message']];
        }

        $uploads[] = [
            'relative_path' => $clipboardUpload['relative_path'],
            'absolute_path' => $clipboardUpload['absolute_path'],
            'mime' => $clipboardUpload['mime'],
        ];
    }

    $totalAttachments = count($keptMedia) + count($uploads);
    if ($mode === 'photo' && $totalAttachments === 0) {
        foreach ($uploads as $upload) {
            deleteCommunityMediaFile($upload['relative_path']);
        }

        return ['success' => false, 'message' => 'Aggiungi almeno un’immagine al tuo post.'];
    }

    if ($mode === 'story' && $totalAttachments === 0) {
        foreach ($uploads as $upload) {
            deleteCommunityMediaFile($upload['relative_path']);
        }

        return ['success' => false, 'message' => 'Aggiungi un’immagine alla tua storia.'];
    }

    if ($mode === 'story' && $totalAttachments > 1) {
        foreach ($uploads as $upload) {
            deleteCommunityMediaFile($upload['relative_path']);
        }

        return ['success' => false, 'message' => 'Le storie possono includere una sola immagine.'];
    }

    $finalContentType = $mode;
    if ($mode === 'photo' && $totalAttachments > 1 && $mediaTableAvailable) {
        $finalContentType = 'gallery';
    }

    try {
        $pdo->beginTransaction();

        if ($isUpdating) {
            if ($extendedSchema) {
                $update = $pdo->prepare('UPDATE community_posts SET content = :content, content_type = :content_type, poll_question = :poll_question, poll_options = :poll_options, story_title = :story_title, story_caption = :story_caption, story_credit = :story_credit, shared_news_id = :shared_news_id, shared_news_title = :shared_news_title, shared_news_slug = :shared_news_slug, shared_news_excerpt = :shared_news_excerpt, shared_news_tag = :shared_news_tag, shared_news_image = :shared_news_image, shared_news_source_url = :shared_news_source_url, shared_news_published_at = :shared_news_published_at, status = :status, scheduled_for = :scheduled_for, published_at = :published_at, updated_at = NOW() WHERE id = :id AND user_id = :user_id');
                $update->execute([
                    'content' => $message,
                    'content_type' => $finalContentType,
                    'poll_question' => $pollQuestion !== '' ? $pollQuestion : null,
                    'poll_options' => $pollOptionsJson,
                    'story_title' => $storyTitle !== '' ? $storyTitle : null,
                    'story_caption' => $storyCaption !== '' ? $storyCaption : null,
                    'story_credit' => $storyCredit !== '' ? $storyCredit : null,
                    'shared_news_id' => $sharedNewsMeta['id'] > 0 ? $sharedNewsMeta['id'] : null,
                    'shared_news_title' => $sharedNewsMeta['title'] !== '' ? $sharedNewsMeta['title'] : null,
                    'shared_news_slug' => $sharedNewsMeta['slug'] !== '' ? $sharedNewsMeta['slug'] : null,
                    'shared_news_excerpt' => $sharedNewsMeta['excerpt'] !== '' ? $sharedNewsMeta['excerpt'] : null,
                    'shared_news_tag' => $sharedNewsMeta['tag'] !== '' ? $sharedNewsMeta['tag'] : null,
                    'shared_news_image' => $sharedNewsMeta['image'] !== '' ? $sharedNewsMeta['image'] : null,
                    'shared_news_source_url' => $sharedNewsMeta['source_url'] !== '' ? $sharedNewsMeta['source_url'] : null,
                    'shared_news_published_at' => $sharedNewsMeta['published_at'],
                    'status' => $status,
                    'scheduled_for' => $scheduledFor ? $scheduledFor->format('Y-m-d H:i:s') : null,
                    'published_at' => $publishedAt ? $publishedAt->format('Y-m-d H:i:s') : null,
                    'id' => $existingPost['id'],
                    'user_id' => $userId,
                ]);
            } else {
                $update = $pdo->prepare('UPDATE community_posts SET content = :content, content_type = :content_type, media_url = NULL, updated_at = NOW() WHERE id = :id AND user_id = :user_id');
                $update->execute([
                    'content' => $message,
                    'content_type' => $finalContentType,
                    'id' => $existingPost['id'],
                    'user_id' => $userId,
                ]);
            }

            $postId = $existingPost['id'];
        } else {
            if ($extendedSchema) {
                $insert = $pdo->prepare('INSERT INTO community_posts (user_id, content, content_type, poll_question, poll_options, story_title, story_caption, story_credit, shared_news_id, shared_news_title, shared_news_slug, shared_news_excerpt, shared_news_tag, shared_news_image, shared_news_source_url, shared_news_published_at, status, scheduled_for, published_at) VALUES (:user_id, :content, :content_type, :poll_question, :poll_options, :story_title, :story_caption, :story_credit, :shared_news_id, :shared_news_title, :shared_news_slug, :shared_news_excerpt, :shared_news_tag, :shared_news_image, :shared_news_source_url, :shared_news_published_at, :status, :scheduled_for, :published_at)');
                $insert->execute([
                    'user_id' => $userId,
                    'content' => $message,
                    'content_type' => $finalContentType,
                    'poll_question' => $pollQuestion !== '' ? $pollQuestion : null,
                    'poll_options' => $pollOptionsJson,
                    'story_title' => $storyTitle !== '' ? $storyTitle : null,
                    'story_caption' => $storyCaption !== '' ? $storyCaption : null,
                    'story_credit' => $storyCredit !== '' ? $storyCredit : null,
                    'shared_news_id' => $sharedNewsMeta['id'] > 0 ? $sharedNewsMeta['id'] : null,
                    'shared_news_title' => $sharedNewsMeta['title'] !== '' ? $sharedNewsMeta['title'] : null,
                    'shared_news_slug' => $sharedNewsMeta['slug'] !== '' ? $sharedNewsMeta['slug'] : null,
                    'shared_news_excerpt' => $sharedNewsMeta['excerpt'] !== '' ? $sharedNewsMeta['excerpt'] : null,
                    'shared_news_tag' => $sharedNewsMeta['tag'] !== '' ? $sharedNewsMeta['tag'] : null,
                    'shared_news_image' => $sharedNewsMeta['image'] !== '' ? $sharedNewsMeta['image'] : null,
                    'shared_news_source_url' => $sharedNewsMeta['source_url'] !== '' ? $sharedNewsMeta['source_url'] : null,
                    'shared_news_published_at' => $sharedNewsMeta['published_at'],
                    'status' => $status,
                    'scheduled_for' => $scheduledFor ? $scheduledFor->format('Y-m-d H:i:s') : null,
                    'published_at' => $publishedAt ? $publishedAt->format('Y-m-d H:i:s') : null,
                ]);
            } else {
                $insert = $pdo->prepare('INSERT INTO community_posts (user_id, content, content_type, media_url) VALUES (:user_id, :content, :content_type, NULL)');
                $insert->execute([
                    'user_id' => $userId,
                    'content' => $message,
                    'content_type' => $finalContentType,
                ]);
            }

            $postId = (int) $pdo->lastInsertId();
        }

        foreach ($mediaToDelete as $record) {
            if ($mediaTableAvailable) {
                $delete = $pdo->prepare('DELETE FROM community_post_media WHERE id = :id AND post_id = :post_id');
                $delete->execute([
                    'id' => (int) $record['id'],
                    'post_id' => $postId,
                ]);
            }

            if (!empty($record['path'])) {
                deleteCommunityMediaFile($record['path']);
            }
        }

        $mediaOrder = [];
        foreach ($keptMedia as $record) {
            $mediaOrder[] = [
                'id' => (int) $record['id'],
                'path' => $record['path'],
                'mime' => $record['mime'] ?? '',
            ];
        }

        foreach ($uploads as $upload) {
            $mediaOrder[] = [
                'id' => 0,
                'path' => $upload['relative_path'],
                'mime' => $upload['mime'],
            ];
        }

        if ($mediaTableAvailable) {
            foreach ($mediaOrder as $index => $mediaItem) {
                if ($mediaItem['id'] > 0) {
                    $updatePosition = $pdo->prepare('UPDATE community_post_media SET position = :position WHERE id = :id AND post_id = :post_id');
                    $updatePosition->execute([
                        'position' => $index,
                        'id' => $mediaItem['id'],
                        'post_id' => $postId,
                    ]);
                } else {
                    $insertMedia = $pdo->prepare('INSERT INTO community_post_media (post_id, file_path, mime_type, position) VALUES (:post_id, :file_path, :mime_type, :position)');
                    $insertMedia->execute([
                        'post_id' => $postId,
                        'file_path' => $mediaItem['path'],
                        'mime_type' => $mediaItem['mime'],
                        'position' => $index,
                    ]);
                }
            }
        }

        $coverPath = $mediaOrder[0]['path'] ?? null;
        $coverUpdate = $pdo->prepare('UPDATE community_posts SET media_url = :media_url WHERE id = :id');
        $coverUpdate->execute([
            'media_url' => $coverPath !== null ? $coverPath : null,
            'id' => $postId,
        ]);

        replaceCommunityPostMentions($pdo, $postId, $userId, array_values($mentionedUserIds));

        $pdo->commit();

        if ($status === 'published') {
            try {
                notifyCommunityPostPublished($postId);
            } catch (\Throwable $exception) {
                error_log('[BianconeriHub] Failed to dispatch push notifications for post ' . $postId . ': ' . $exception->getMessage());
            }

            try {
                notifyCommunityPostMentions($postId);
            } catch (\Throwable $exception) {
                error_log('[BianconeriHub] Failed to dispatch mention notifications for post ' . $postId . ': ' . $exception->getMessage());
            }
        }

        return [
            'success' => true,
            'post_id' => $postId,
            'status' => $status,
        ];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        foreach ($uploads as $upload) {
            deleteCommunityMediaFile($upload['relative_path']);
        }

        $lastExceptionMessage = $exception->getMessage();
        error_log('[BianconeriHub] Failed to add community post: ' . $exception->getMessage());
    }

    if (!empty($appDebug)) {
        $detail = $lastExceptionMessage ?: 'Errore sconosciuto.';
        return ['success' => false, 'message' => 'Impossibile salvare il messaggio: ' . $detail];
    }

    return ['success' => false, 'message' => 'Impossibile salvare il messaggio al momento.'];
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
    triggerPeriodicNewsSync();
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
    $statement = $pdo->prepare('SELECT c.id, c.content, c.created_at, u.username, u.first_name, u.last_name, u.badge, u.avatar_url FROM news_comments c INNER JOIN users u ON u.id = c.user_id WHERE c.news_id = :news_id ORDER BY c.created_at DESC');
        $statement->execute(['news_id' => $newsId]);

        $comments = [];
        foreach ($statement as $row) {
            $comments[] = [
                'id' => (int) ($row['id'] ?? 0),
                'content' => $row['content'] ?? '',
                'created_at' => normalizeToTimestamp($row['created_at'] ?? time()),
                'username' => $row['username'] ?? 'Tifoso',
                'display_name' => buildUserDisplayName($row['first_name'] ?? null, $row['last_name'] ?? null, (string) ($row['username'] ?? 'Tifoso')),
                'first_name' => isset($row['first_name']) ? (string) $row['first_name'] : null,
                'last_name' => isset($row['last_name']) ? (string) $row['last_name'] : null,
                'badge' => $row['badge'] ?? 'Tifoso',
                'avatar_url' => trim((string) ($row['avatar_url'] ?? '')),
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

function getCommunityEmojiOptions(): array
{
    static $options;

    if (is_array($options)) {
        return $options;
    }

    $options = [
        '⚽',
        '🏟️',
        '🖤',
        '🤍',
        '🔥',
        '💪',
        '🙌',
        '🎉',
        '⭐',
        '🏆',
        '👏',
        '💯',
        '🥹',
        '😉',
        '😊',
        '🥳',
    ];

    return $options;
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
