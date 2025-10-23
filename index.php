<?php
require_once __DIR__ . '/config.php';

if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'logout' && isUserLoggedIn()) {
        logoutUser();
        setFlash('auth', 'Hai effettuato il logout con successo.', 'success');
        header('Location: ?page=home');
        exit;
    }

    if ($action === 'download_match_ics') {
        $matchId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $match = $matchId > 0 ? findMatchById($matchId) : null;

        if (!$match) {
            setFlash('partite', 'Partita non trovata.', 'error');
            header('Location: ?page=partite');
            exit;
        }

        $kickoffValue = $match['kickoff_at'] ?? null;
        if ($kickoffValue instanceof DateTimeInterface) {
            $kickoff = new DateTime($kickoffValue->format('Y-m-d H:i:s'));
        } elseif (is_string($kickoffValue) && $kickoffValue !== '') {
            $kickoff = new DateTime($kickoffValue);
        } else {
            $kickoff = new DateTime();
        }

        $endTime = (clone $kickoff)->modify('+2 hours');
        $startUtc = (clone $kickoff)->setTimezone(new DateTimeZone('UTC'));
        $endUtc = (clone $endTime)->setTimezone(new DateTimeZone('UTC'));

    $summary = preg_replace('/\s+/', ' ', trim('Juventus vs ' . ($match['opponent'] ?? '')));
        $description = trim(implode(' ', array_filter([
            $match['competition'] ?? '',
            $match['status'] ?? '',
            $match['broadcast'] ? 'Diretta: ' . $match['broadcast'] : '',
        ])));
        $location = preg_replace('/\s+/', ' ', trim($match['venue'] ?? 'Allianz Stadium'));

        $icsLines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//BianconeriHub//Match Calendar//IT',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:match-' . $match['id'] . '@bianconerihub.local',
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'SUMMARY:' . $summary,
            'DTSTART:' . $startUtc->format('Ymd\THis\Z'),
            'DTEND:' . $endUtc->format('Ymd\THis\Z'),
            'LOCATION:' . $location,
            'DESCRIPTION:' . $description,
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="juventus-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($match['opponent'] ?? 'match')) . '.ics"');
        echo implode("\r\n", $icsLines);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'login') {
        if (!validateCsrfToken($_POST['_token'] ?? '')) {
            setFlash('auth', 'Sessione scaduta. Aggiorna la pagina e riprova.', 'error');
            header('Location: ?page=login');
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = attemptLogin($username, $password);

        if ($result['success']) {
            clearOldInput();
            setFlash('auth', 'Bentornato/a ' . htmlspecialchars($result['user']['username'], ENT_QUOTES, 'UTF-8') . '!', 'success');
            header('Location: ?page=community');
            exit;
        }

        storeOldInput(['username' => $username]);
        setFlash('auth', $result['message'], 'error');
        header('Location: ?page=login');
        exit;
    }

    if ($formType === 'register') {
        if (!validateCsrfToken($_POST['_token'] ?? '')) {
            setFlash('auth', 'Sessione scaduta. Aggiorna la pagina e riprova.', 'error');
            header('Location: ?page=register');
            exit;
        }

        $registration = registerUser($_POST);

        if ($registration['success']) {
            $user = $registration['user'];
            attemptLogin($user['username'], $_POST['password']);
            clearOldInput();
            setFlash('auth', 'Registrazione completata! Benvenuto/a ' . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . '.', 'success');
            header('Location: ?page=community');
            exit;
        }

        storeOldInput([
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
        ]);
        setFlash('auth', $registration['message'], 'error');
        header('Location: ?page=register');
        exit;
    }

    if ($formType === 'community_post') {
        if (!validateCsrfToken($_POST['_token'] ?? '')) {
            setFlash('community', 'Sessione scaduta. Riprova a pubblicare il tuo messaggio.', 'error');
            header('Location: ?page=community');
            exit;
        }

        if (!isUserLoggedIn()) {
            setFlash('community', 'Devi effettuare il login per pubblicare un messaggio.', 'error');
            header('Location: ?page=login');
            exit;
        }

        $user = getLoggedInUser();
        $result = addCommunityPost((int) $user['id'], $_POST['message'] ?? '');

        if ($result['success']) {
            setFlash('community', 'Messaggio pubblicato con successo!', 'success');
            clearOldInput();
            regenerateCsrfToken();
        } else {
            setFlash('community', $result['message'], 'error');
            storeOldInput(['message' => $_POST['message'] ?? '']);
        }

        header('Location: ?page=community');
        exit;
    }

    if ($formType === 'news_comment') {
        $slug = trim($_POST['news_slug'] ?? '');
        $newsId = isset($_POST['news_id']) ? (int) $_POST['news_id'] : 0;
        if ($slug === '' && $newsId > 0) {
            $newsItem = findNewsItemById($newsId);
            if ($newsItem) {
                $slug = $newsItem['slug'] ?? '';
            }
        }
        $redirectUrl = $slug !== '' ? '?page=news_article&slug=' . urlencode($slug) : '?page=news';

        if (!validateCsrfToken($_POST['_token'] ?? '')) {
            storeOldInput(['news_comment' => trim($_POST['message'] ?? '')]);
            setFlash('news', 'Sessione scaduta. Aggiorna la pagina e riprova.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        if ($newsId <= 0) {
            setFlash('news', 'Notizia non valida.', 'error');
            header('Location: ?page=news');
            exit;
        }

        if (!isUserLoggedIn()) {
            storeOldInput(['news_comment' => trim($_POST['message'] ?? '')]);
            setFlash('news', 'Effettua il login per commentare le notizie.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        $user = getLoggedInUser();
        $result = addNewsComment($newsId, (int) $user['id'], $_POST['message'] ?? '');

        if ($result['success']) {
            forgetOldInput(['news_comment']);
            setFlash('news', 'Commento pubblicato con successo!', 'success');
        } else {
            setFlash('news', $result['message'] ?? 'Impossibile pubblicare il commento.', 'error');
            storeOldInput(['news_comment' => $_POST['message'] ?? '']);
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($formType === 'news_like_toggle') {
        $slug = trim($_POST['news_slug'] ?? '');
        $newsId = isset($_POST['news_id']) ? (int) $_POST['news_id'] : 0;
        if ($slug === '' && $newsId > 0) {
            $newsItem = findNewsItemById($newsId);
            if ($newsItem) {
                $slug = $newsItem['slug'] ?? '';
            }
        }
        $redirectUrl = $slug !== '' ? '?page=news_article&slug=' . urlencode($slug) : '?page=news';

        if (!validateCsrfToken($_POST['_token'] ?? '')) {
            setFlash('news', 'Sessione scaduta. Aggiorna la pagina e riprova.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        if ($newsId <= 0) {
            setFlash('news', 'Notizia non valida.', 'error');
            header('Location: ?page=news');
            exit;
        }

        if (!isUserLoggedIn()) {
            setFlash('news', 'Accedi per mettere Mi piace alle notizie.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        $user = getLoggedInUser();
        $result = toggleNewsLike($newsId, (int) $user['id']);

        if ($result['success']) {
            $message = $result['liked']
                ? 'Hai messo Mi piace alla notizia.'
                : 'Hai rimosso il tuo Mi piace.';
            setFlash('news', $message, 'success');
        } else {
            $error = $result['message'] ?? 'Impossibile aggiornare il like.';
            setFlash('news', $error, 'error');
        }

        header('Location: ' . $redirectUrl);
        exit;
    }
}

$pageKey = isset($_GET['page']) ? strtolower(trim($_GET['page'])) : 'home';
$isKnownPage = array_key_exists($pageKey, $availablePages);

$pageFile = $isKnownPage ? $availablePages[$pageKey] : null;
$pageTitle = $isKnownPage
    ? resolvePageTitle($pageKey, $pageTitles, $siteName)
    : 'Pagina non trovata';

$activeNewsArticle = null;
if ($isKnownPage && $pageKey === 'news_article') {
    $slug = trim($_GET['slug'] ?? '');
    if ($slug !== '') {
        $activeNewsArticle = findNewsItemBySlug($slug);
    }

    if ($activeNewsArticle) {
        $pageTitle = $activeNewsArticle['title'];
    } else {
        $pageTitle = 'Notizia non trovata';
    }
}

$currentPage = $isKnownPage ? $pageKey : '';
if ($currentPage === 'news_article') {
    $currentPage = 'news';
}

require __DIR__ . '/includes/header.php';

if ($pageFile && file_exists($pageFile)) {
    include $pageFile;
} else {
    ?>
    <section class="space-y-4 text-center">
        <h1 class="text-2xl font-bold">Pagina non trovata</h1>
        <p class="text-gray-400">La pagina che stai cercando non esiste o è stata spostata.</p>
        <a href="?page=home" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white text-black font-semibold transition-all duration-300 hover:bg-juventus-silver">
            Torna alla home
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12l7.5-7.5M3 12h18" />
            </svg>
        </a>
    </section>
    <?php
}

require __DIR__ . '/includes/navbar.php';
require __DIR__ . '/includes/footer.php';
