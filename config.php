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

// Register pages for the simple router
$availablePages = [
    'home' => __DIR__ . '/pages/home.php',
    'news' => __DIR__ . '/pages/news.php',
    'partite' => __DIR__ . '/pages/partite.php',
    'community' => __DIR__ . '/pages/community.php',
    'login' => __DIR__ . '/pages/login.php',
    'register' => __DIR__ . '/pages/register.php',
];

$pageTitles = [
    'home' => 'Home',
    'news' => 'Notizie',
    'partite' => 'Partite',
    'community' => 'Community',
    'login' => 'Accedi',
    'register' => 'Registrati',
];

if (!isset($_SESSION['bh_users'])) {
    $_SESSION['bh_users'] = [
        [
            'username' => 'chiara96',
            'email' => 'chiara96@example.com',
            'password_hash' => password_hash('forzajuve', PASSWORD_DEFAULT),
            'badge' => 'Veterana',
            'created_at' => time(),
        ],
        [
            'username' => 'marco_juve',
            'email' => 'marco@example.com',
            'password_hash' => password_hash('dybala10', PASSWORD_DEFAULT),
            'badge' => 'Curva Sud',
            'created_at' => time(),
        ],
    ];
}

if (!isset($_SESSION['bh_posts'])) {
    $_SESSION['bh_posts'] = [
        [
            'author' => 'Chiara96',
            'content' => 'Che emozione rivedere in campo il capitano! Prestazione da leader vero, continuiamo così! ⚪⚫',
            'created_at' => strtotime('-5 minutes'),
        ],
        [
            'author' => 'Marco_Juve',
            'content' => 'Secondo voi dovremmo cambiare modulo contro il Bayern? Difesa a tre o restiamo col 4-3-3?',
            'created_at' => strtotime('-20 minutes'),
        ],
        [
            'author' => 'Vale_B',
            'content' => 'Organizziamo una trasferta insieme per Firenze? Scrivetemi in DM! #TrasfertaBianconera',
            'created_at' => strtotime('-1 hour'),
        ],
    ];
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
    return [
        [
            'title' => 'La nuova era bianconera: focus sui giovani',
            'excerpt' => 'Under 23 e Next Gen pronte a conquistare minuti importanti con la prima squadra.',
            'image' => 'assets/img/news1.jpg',
            'tag' => 'Analisi',
        ],
        [
            'title' => 'Allenamento a porte aperte: entusiasmo a Vinovo',
            'excerpt' => 'Più di 5.000 tifosi presenti per abbracciare la squadra prima del big match.',
            'image' => 'assets/img/news2.jpg',
            'tag' => 'Report',
        ],
        [
            'title' => "Analisi tattica: le mosse di mister Allegri",
            'excerpt' => 'Pressing alto e cambi di modulo per controllare il centrocampo.',
            'image' => 'assets/img/news3.jpg',
            'tag' => 'Tattica',
        ],
        [
            'title' => 'Mercato: occhi puntati su un nuovo regista',
            'excerpt' => 'La dirigenza studia l’acquisto di un playmaker di esperienza internazionale.',
            'image' => 'assets/img/news4.jpg',
            'tag' => 'Mercato',
        ],
    ];
}

/**
 * Retrieve upcoming matches for the fixture list.
 */
function getUpcomingMatches(): array
{
    return [
        [
            'competition' => 'Serie A',
            'opponent' => 'Milan',
            'date' => '27 ottobre 2025',
            'time' => '20:45',
            'venue' => 'Allianz Stadium',
            'status' => 'Big match',
        ],
        [
            'competition' => 'Champions League',
            'opponent' => 'Bayern Monaco',
            'date' => '5 novembre 2025',
            'time' => '21:00',
            'venue' => 'Allianz Arena',
            'status' => 'Trasferta impegnativa',
        ],
        [
            'competition' => 'Serie A',
            'opponent' => 'Fiorentina',
            'date' => '10 novembre 2025',
            'time' => '18:00',
            'venue' => 'Artemio Franchi',
            'status' => 'Derby del cuore',
        ],
    ];
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
    return $_SESSION['bh_users'] ?? [];
}

function findUserByUsername(string $username): ?array
{
    $usernameLower = strtolower($username);

    foreach (getRegisteredUsers() as $user) {
        if (strtolower($user['username']) === $usernameLower) {
            return $user;
        }
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

    $user = [
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'badge' => 'Nuovo tifoso',
        'created_at' => time(),
    ];

    $_SESSION['bh_users'][] = $user;

    return ['success' => true, 'user' => $user];
}

function attemptLogin(string $username, string $password): array
{
    $user = findUserByUsername($username);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Credenziali non valide.'];
    }

    $_SESSION['bh_current_user'] = [
        'username' => $user['username'],
        'email' => $user['email'],
        'badge' => $user['badge'] ?? 'Tifoso',
        'login_time' => time(),
    ];

    return ['success' => true, 'user' => $_SESSION['bh_current_user']];
}

function logoutUser(): void
{
    unset($_SESSION['bh_current_user']);
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
    $posts = $_SESSION['bh_posts'] ?? [];

    usort($posts, static function ($a, $b) {
        return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
    });

    return $posts;
}

function addCommunityPost(string $author, string $content): array
{
    $trimmed = trim($content);

    if ($trimmed === '') {
        return ['success' => false, 'message' => 'Il messaggio non può essere vuoto.'];
    }

    $_SESSION['bh_posts'][] = [
        'author' => $author,
        'content' => $trimmed,
        'created_at' => time(),
    ];

    return ['success' => true];
}

function getHumanTimeDiff(int $timestamp): string
{
    $diff = time() - $timestamp;

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
