<?php
require_once __DIR__ . '/config.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout' && isUserLoggedIn()) {
    logoutUser();
    setFlash('auth', 'Hai effettuato il logout con successo.', 'success');
    header('Location: ?page=home');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'login') {
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
        if (!isUserLoggedIn()) {
            setFlash('community', 'Devi effettuare il login per pubblicare un messaggio.', 'error');
            header('Location: ?page=login');
            exit;
        }

    $user = getLoggedInUser();
    $result = addCommunityPost($user['username'], $_POST['message'] ?? '');

        if ($result['success']) {
            setFlash('community', 'Messaggio pubblicato con successo!', 'success');
        } else {
            setFlash('community', $result['message'], 'error');
            storeOldInput(['message' => $_POST['message'] ?? '']);
        }

        header('Location: ?page=community');
        exit;
    }
}

$pageKey = isset($_GET['page']) ? strtolower(trim($_GET['page'])) : 'home';
$isKnownPage = array_key_exists($pageKey, $availablePages);

$pageFile = $isKnownPage ? $availablePages[$pageKey] : null;
$pageTitle = $isKnownPage
    ? resolvePageTitle($pageKey, $pageTitles, $siteName)
    : 'Pagina non trovata';

$currentPage = $isKnownPage ? $pageKey : '';

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
