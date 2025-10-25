<?php
require_once __DIR__ . '/config.php';

$siteName = isset($siteName) && is_string($siteName) && $siteName !== ''
    ? $siteName
    : (is_string($appName) && $appName !== '' ? $appName : 'BianconeriHub');

$siteTagline = isset($siteTagline) && is_string($siteTagline) && $siteTagline !== ''
    ? $siteTagline
    : (is_string($appTagline) && $appTagline !== '' ? $appTagline : 'Passione bianconera ogni giorno');

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

    if ($action === 'community_compose') {
        if (!isUserLoggedIn()) {
            setFlash('community', 'Effettua l\'accesso per usare il composer.', 'error');
            header('Location: ?page=login');
            exit;
        }

        $user = getLoggedInUser();
        $userId = isset($user['id']) ? (int) $user['id'] : 0;
        if ($userId <= 0) {
            setFlash('community', 'Sessione non valida. Effettua nuovamente il login.', 'error');
            header('Location: ?page=login');
            exit;
        }

        $requestedMode = strtolower((string) ($_GET['mode'] ?? ''));
        $allowedModes = ['text', 'photo', 'poll', 'story', 'news'];
        $draftId = isset($_GET['draft']) ? (int) $_GET['draft'] : 0;
        $oldInput = [];

        if ($draftId > 0) {
            $draft = getCommunityPostForEditing($draftId, $userId);

            if (!$draft) {
                setFlash('community', 'Bozza non trovata o non accessibile.', 'error');
                header('Location: ?page=community');
                exit;
            }

            if (!in_array($draft['status'], ['draft', 'scheduled'], true)) {
                setFlash('community', 'Il post è già stato pubblicato e non può essere modificato qui.', 'error');
                header('Location: ?page=community');
                exit;
            }

            $contentType = strtolower((string) ($draft['content_type'] ?? 'text'));
            $modeMap = [
                'text' => 'text',
                'photo' => 'photo',
                'gallery' => 'photo',
                'poll' => 'poll',
                'story' => 'story',
                'news' => 'news',
            ];
            $composerMode = $modeMap[$contentType] ?? 'text';

            $composerAction = $draft['status'] === 'scheduled' ? 'schedule' : 'draft';
            $scheduleAt = '';
            if ($composerAction === 'schedule' && !empty($draft['scheduled_for'])) {
                try {
                    $scheduleDate = new DateTime((string) $draft['scheduled_for']);
                    $scheduleAt = $scheduleDate->format('Y-m-d\TH:i');
                } catch (\Throwable $exception) {
                    $scheduleAt = '';
                }
            }

            $pollOptions = [];
            if (!empty($draft['poll_options']) && is_array($draft['poll_options'])) {
                foreach ($draft['poll_options'] as $option) {
                    $pollOptions[] = (string) $option;
                }
            }

            $existingMediaIds = [];
            if (!empty($draft['media']) && is_array($draft['media'])) {
                foreach ($draft['media'] as $mediaItem) {
                    $mediaId = (int) ($mediaItem['id'] ?? 0);
                    if ($mediaId > 0) {
                        $existingMediaIds[$mediaId] = $mediaId;
                    }
                }
            }

            $oldInput = [
                'message' => (string) ($draft['content'] ?? ''),
                'composer_mode' => $composerMode,
                'composer_action' => $composerAction,
                'schedule_at' => $scheduleAt,
                'poll_question' => (string) ($draft['poll_question'] ?? ''),
                'poll_options' => $pollOptions,
                'story_title' => (string) ($draft['story_title'] ?? ''),
                'story_caption' => (string) ($draft['story_caption'] ?? ''),
                'story_credit' => (string) ($draft['story_credit'] ?? ''),
                'shared_news_id' => (int) ($draft['shared_news_id'] ?? 0),
                'shared_news_slug' => (string) ($draft['shared_news_slug'] ?? ''),
                'draft_id' => (int) ($draft['id'] ?? 0),
            ];

            if (!empty($existingMediaIds)) {
                $oldInput['existing_media'] = array_values($existingMediaIds);
            }

            if ($composerMode !== 'poll') {
                $oldInput['poll_question'] = '';
                $oldInput['poll_options'] = [];
            }

            if ($composerMode !== 'story') {
                $oldInput['story_title'] = '';
                $oldInput['story_caption'] = '';
                $oldInput['story_credit'] = '';
            }

            if ($composerMode !== 'news') {
                $oldInput['shared_news_id'] = 0;
                $oldInput['shared_news_slug'] = '';
            }
        } elseif (in_array($requestedMode, $allowedModes, true)) {
            $oldInput['composer_mode'] = $requestedMode;
        }

        clearOldInput();
        if (!empty($oldInput)) {
            storeOldInput($oldInput);
        }

        header('Location: ?page=community#community-composer');
        exit;
    }
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
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
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
        ]);
        setFlash('auth', $registration['message'], 'error');
        header('Location: ?page=register');
        exit;
    }

    if ($formType === 'forgot_password') {
        try {
            if (!validateCsrfToken($_POST['_token'] ?? '')) {
                setFlash('auth', 'Sessione scaduta. Aggiorna la pagina e riprova.', 'error');
                header('Location: ?page=password_forgot');
                exit;
            }

            $email = trim((string) ($_POST['email'] ?? ''));

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                storeOldInput(['forgot_email' => $email]);
                setFlash('auth', 'Inserisci un indirizzo email valido.', 'error');
                header('Location: ?page=password_forgot');
                exit;
            }

            $user = findUserByEmail($email);

            if ($user) {
                $tokenData = createPasswordResetToken((int) $user['id']);
                if ($tokenData && isset($tokenData['token'], $tokenData['expires_at']) && $tokenData['token'] !== '') {
                    if (!sendPasswordResetEmail($user, $tokenData['token'], $tokenData['expires_at'])) {
                        error_log('[BianconeriHub] Unable to dispatch password reset email for user ID ' . (int) $user['id']);
                    }
                } else {
                    error_log('[BianconeriHub] Token generation failed for user ID ' . (int) $user['id']);
                }
            }

            clearOldInput();
            setFlash('auth', 'Se l\'indirizzo è registrato riceverai tra pochi minuti un\'email con il link per reimpostare la password.', 'success');
            header('Location: ?page=password_forgot');
            exit;
        } catch (Throwable $exception) {
            error_log('[BianconeriHub] Forgot password handler error: ' . $exception->getMessage());
            setFlash('auth', 'Si è verificato un errore inatteso. Riprova tra qualche minuto.', 'error');
            header('Location: ?page=password_forgot');
            exit;
        }
    }

    if ($formType === 'password_reset') {
        $token = trim((string) ($_POST['token'] ?? ''));

        if (!validateCsrfToken($_POST['_token'] ?? '')) {
            setFlash('auth', 'Sessione scaduta. Aggiorna la pagina e riprova.', 'error');
            $redirectUrl = $token !== '' ? '?page=password_reset&token=' . urlencode($token) : '?page=password_forgot';
            header('Location: ' . $redirectUrl);
            exit;
        }

        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
        $result = completePasswordReset($token, $password, $passwordConfirmation);

        if ($result['success']) {
            clearOldInput();
            setFlash('auth', 'Password aggiornata con successo! Ora puoi accedere.', 'success');
            header('Location: ?page=login');
            exit;
        }

        $status = $result['status'] ?? '';
        $message = $result['message'] ?? 'Impossibile reimpostare la password in questo momento.';

        if (in_array($status, ['invalid', 'expired'], true)) {
            setFlash('auth', $message, 'error');
            header('Location: ?page=password_forgot');
            exit;
        }

        storeOldInput(['password_reset_token' => $token]);
        setFlash('auth', $message, 'error');
        $redirectUrl = $token !== '' ? '?page=password_reset&token=' . urlencode($token) : '?page=password_forgot';
        header('Location: ' . $redirectUrl);
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
    $result = addCommunityPost((int) $user['id'], $_POST, $_FILES);

        if ($result['success']) {
            setFlash('community', 'Messaggio pubblicato con successo!', 'success');
            clearOldInput();
            regenerateCsrfToken();
        } else {
            setFlash('community', $result['message'], 'error');
            $pollOptionsInput = $_POST['poll_options'] ?? [];
            if (!is_array($pollOptionsInput)) {
                $pollOptionsInput = [];
            }
            $oldStoryTitle = trim((string) ($_POST['story_title'] ?? ''));
            $oldStoryCaption = trim((string) ($_POST['story_caption'] ?? ''));
            $oldStoryCredit = trim((string) ($_POST['story_credit'] ?? ''));
            $oldComposerAction = strtolower((string) ($_POST['composer_action'] ?? 'publish'));
            $oldScheduleAt = trim((string) ($_POST['schedule_at'] ?? ''));
            $oldDraftId = isset($_POST['draft_id']) ? (int) $_POST['draft_id'] : 0;

            storeOldInput([
                'message' => trim((string) ($_POST['message'] ?? '')),
                'composer_mode' => $_POST['composer_mode'] ?? 'text',
                'poll_question' => trim((string) ($_POST['poll_question'] ?? '')),
                'poll_options' => array_map(static function ($option) {
                    return trim((string) $option);
                }, $pollOptionsInput),
                'story_title' => $oldStoryTitle,
                'story_caption' => $oldStoryCaption,
                'story_credit' => $oldStoryCredit,
                'shared_news_id' => isset($_POST['shared_news_id']) ? (int) $_POST['shared_news_id'] : 0,
                'composer_action' => $oldComposerAction,
                'schedule_at' => $oldScheduleAt,
                'draft_id' => $oldDraftId,
            ]);
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

    if ($formType === 'profile_settings_update') {
        $fields = [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'bio' => trim((string) ($_POST['bio'] ?? '')),
            'location' => trim((string) ($_POST['location'] ?? '')),
            'website' => trim((string) ($_POST['website'] ?? '')),
            'favorite_player' => trim((string) ($_POST['favorite_player'] ?? '')),
            'favorite_memory' => trim((string) ($_POST['favorite_memory'] ?? '')),
        ];

        if (!validateCsrfToken($_POST['_token'] ?? '')) {
            storeOldInput($fields);
            setFlash('profile_settings', 'Sessione scaduta. Aggiorna la pagina e riprova.', 'error');
            header('Location: ?page=profile_settings');
            exit;
        }

        if (!isUserLoggedIn()) {
            storeOldInput($fields);
            setFlash('profile_settings', 'Effettua il login per modificare il profilo.', 'error');
            header('Location: ?page=login');
            exit;
        }

        $user = getLoggedInUser();
        $result = saveUserProfileSettings((int) $user['id'], $fields);

        if ($result['success']) {
            clearOldInput();
            setFlash('profile_settings', 'Profilo aggiornato con successo!', 'success');
        } else {
            storeOldInput($fields);
            $message = $result['message'] ?? 'Impossibile aggiornare il profilo in questo momento.';
            setFlash('profile_settings', $message, 'error');
        }

        header('Location: ?page=profile_settings');
        exit;
    }

    if ($formType === 'profile_avatar') {
        if (!validateCsrfToken($_POST['_token'] ?? '')) {
            setFlash('profile_settings', 'Sessione scaduta. Aggiorna la pagina e riprova.', 'error');
            header('Location: ?page=profile_settings');
            exit;
        }

        if (!isUserLoggedIn()) {
            setFlash('profile_settings', 'Effettua il login per aggiornare l\'avatar.', 'error');
            header('Location: ?page=login');
            exit;
        }

        $user = getLoggedInUser();
        $result = updateUserAvatar((int) $user['id'], $_FILES['avatar'] ?? null);

        if ($result['success']) {
            setFlash('profile_settings', 'Avatar aggiornato con successo!', 'success');
        } else {
            $message = $result['message'] ?? 'Impossibile aggiornare l\'avatar in questo momento.';
            setFlash('profile_settings', $message, 'error');
        }

        header('Location: ?page=profile_settings');
        exit;
    }

    if ($formType === 'profile_cover') {
        if (!validateCsrfToken($_POST['_token'] ?? '')) {
            setFlash('profile_settings', 'Sessione scaduta. Aggiorna la pagina e riprova.', 'error');
            header('Location: ?page=profile_settings');
            exit;
        }

        if (!isUserLoggedIn()) {
            setFlash('profile_settings', 'Effettua il login per aggiornare la copertina.', 'error');
            header('Location: ?page=login');
            exit;
        }

        $user = getLoggedInUser();
        $result = updateUserCover((int) $user['id'], $_FILES['cover'] ?? null);

        if ($result['success']) {
            setFlash('profile_settings', 'Copertina aggiornata con successo!', 'success');
        } else {
            $message = $result['message'] ?? 'Impossibile aggiornare la copertina in questo momento.';
            setFlash('profile_settings', $message, 'error');
        }

        header('Location: ?page=profile_settings');
        exit;
    }

    if ($formType === 'community_reaction') {
        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $reactionType = strtolower(trim((string) ($_POST['reaction_type'] ?? '')));
        $redirectUrl = '?page=community';
        if (isset($_POST['redirect_to'])) {
            $candidate = trim((string) $_POST['redirect_to']);
            if ($candidate !== '' && !preg_match('/^https?:/i', $candidate)) {
                if ($candidate[0] === '?') {
                    $redirectUrl = $candidate;
                } elseif ($candidate[0] === '#') {
                    $redirectUrl .= $candidate;
                }
            }
        }

        if (!validateCsrfToken($_POST['_token'] ?? '')) {
            setFlash('community', 'Sessione scaduta. Aggiorna la pagina e riprova.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        if ($postId <= 0) {
            setFlash('community', 'Post non valido.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        if (!in_array($reactionType, ['like', 'support'], true)) {
            setFlash('community', 'Reazione non riconosciuta.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        if (!isUserLoggedIn()) {
            setFlash('community', 'Accedi per partecipare alle reazioni della community.', 'error');
            header('Location: ?page=login');
            exit;
        }

        $user = getLoggedInUser();
        $result = toggleCommunityReaction($postId, (int) $user['id'], $reactionType);

        if ($result['success']) {
            $labels = ['like' => 'Mi piace', 'support' => 'Supporto'];
            $label = $labels[$reactionType] ?? 'reazione';
            $message = ($result['state'] ?? '') === 'added'
                ? 'Hai aggiunto il tuo ' . strtolower($label) . '.'
                : 'Hai rimosso il tuo ' . strtolower($label) . '.';
            setFlash('community', $message, 'success');
        } else {
            $error = $result['message'] ?? 'Impossibile aggiornare la reazione in questo momento.';
            setFlash('community', $error, 'error');
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($formType === 'community_follow') {
        $redirectUrl = '?page=community';
        if (isset($_POST['redirect_to'])) {
            $candidate = trim((string) $_POST['redirect_to']);
            if ($candidate !== '' && !preg_match('/^https?:/i', $candidate)) {
                if ($candidate[0] === '?') {
                    $redirectUrl = $candidate;
                } elseif ($candidate[0] === '#') {
                    $redirectUrl .= $candidate;
                }
            }
        }

        if (!validateCsrfToken($_POST['_token'] ?? '')) {
            setFlash('community', 'Sessione scaduta. Aggiorna la pagina e riprova.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        if (!isUserLoggedIn()) {
            setFlash('community', 'Effettua il login per gestire i tuoi seguiti.', 'error');
            header('Location: ?page=login');
            exit;
        }

        $targetUserId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $action = strtolower(trim((string) ($_POST['follow_action'] ?? 'follow')));
        $currentUser = getLoggedInUser();
        $currentUserId = (int) ($currentUser['id'] ?? 0);

        if ($targetUserId <= 0 || $currentUserId <= 0) {
            setFlash('community', 'Utente non valido.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        if ($targetUserId === $currentUserId) {
            setFlash('community', 'Non puoi seguire te stesso.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        $targetUser = findUserById($targetUserId);
        if (!$targetUser) {
            setFlash('community', 'Tifoso non trovato.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        $targetName = $targetUser['username'] ?? 'tifoso';

        if ($action !== 'unfollow') {
            $result = followCommunityUser($targetUserId, $currentUserId);

            if ($result['success']) {
                $variant = ($result['state'] ?? '') === 'followed' ? 'success' : 'info';
                $message = ($result['state'] ?? '') === 'followed'
                    ? 'Ora segui ' . $targetName . '.'
                    : 'Segui già ' . $targetName . '.';
                setFlash('community', $message, $variant);
            } else {
                $message = $result['message'] ?? 'Impossibile aggiornare il seguito in questo momento.';
                setFlash('community', $message, 'error');
            }

            header('Location: ' . $redirectUrl);
            exit;
        }

        $result = unfollowCommunityUser($targetUserId, $currentUserId);
        if ($result['success']) {
            $state = $result['state'] ?? '';
            if ($state === 'unfollowed') {
                $message = 'Hai smesso di seguire ' . $targetName . '.';
                setFlash('community', $message, 'success');
            } else {
                $message = 'Non risultavi tra i follower di ' . $targetName . '.';
                setFlash('community', $message, 'info');
            }
        } else {
            $message = $result['message'] ?? 'Impossibile aggiornare il seguito in questo momento.';
            setFlash('community', $message, 'error');
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($formType === 'community_comment') {
        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $parentCommentId = isset($_POST['parent_comment_id']) ? (int) $_POST['parent_comment_id'] : 0;
        $redirectUrl = '?page=community';
        if (isset($_POST['redirect_to'])) {
            $candidate = trim((string) $_POST['redirect_to']);
            if ($candidate !== '' && !preg_match('/^https?:/i', $candidate)) {
                if ($candidate[0] === '?') {
                    $redirectUrl = $candidate;
                } elseif ($candidate[0] === '#') {
                    $redirectUrl .= $candidate;
                }
            }
        }

        $commentBody = trim((string) ($_POST['message'] ?? ''));

        if (!validateCsrfToken($_POST['_token'] ?? '')) {
            storeOldInput([
                'community_comment' => $commentBody,
                'community_comment_post_id' => $postId,
                'community_comment_parent_id' => $parentCommentId,
            ]);
            setFlash('community', 'Sessione scaduta. Aggiorna la pagina e riprova.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        if ($postId <= 0) {
            setFlash('community', 'Post non valido.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        if (!isUserLoggedIn()) {
            storeOldInput([
                'community_comment' => $commentBody,
                'community_comment_post_id' => $postId,
                'community_comment_parent_id' => $parentCommentId,
            ]);
            setFlash('community', 'Effettua il login per commentare.', 'error');
            header('Location: ?page=login');
            exit;
        }

        $user = getLoggedInUser();
        $result = addCommunityComment($postId, (int) $user['id'], $commentBody, $parentCommentId);

        if ($result['success']) {
            forgetOldInput(['community_comment', 'community_comment_post_id', 'community_comment_parent_id']);
            $flashMessage = $parentCommentId > 0
                ? 'Risposta pubblicata con successo!'
                : 'Commento pubblicato con successo!';
            setFlash('community', $flashMessage, 'success');
        } else {
            storeOldInput([
                'community_comment' => $commentBody,
                'community_comment_post_id' => $postId,
                'community_comment_parent_id' => $parentCommentId,
            ]);
            $error = $result['message'] ?? 'Impossibile pubblicare il commento.';
            setFlash('community', $error, 'error');
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($formType === 'community_comment_reaction') {
        $commentId = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;
        $redirectUrl = '?page=community';
        if (isset($_POST['redirect_to'])) {
            $candidate = trim((string) $_POST['redirect_to']);
            if ($candidate !== '' && !preg_match('/^https?:/i', $candidate)) {
                if ($candidate[0] === '?') {
                    $redirectUrl = $candidate;
                } elseif ($candidate[0] === '#') {
                    $redirectUrl .= $candidate;
                }
            }
        }

        if (!validateCsrfToken($_POST['_token'] ?? '')) {
            setFlash('community', 'Sessione scaduta. Aggiorna la pagina e riprova.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        if ($commentId <= 0) {
            setFlash('community', 'Commento non valido.', 'error');
            header('Location: ' . $redirectUrl);
            exit;
        }

        if (!isUserLoggedIn()) {
            setFlash('community', 'Effettua il login per mettere Mi piace ai commenti.', 'error');
            header('Location: ?page=login');
            exit;
        }

        $user = getLoggedInUser();
        $result = toggleCommunityCommentReaction($commentId, (int) $user['id']);

        if ($result['success']) {
            $state = $result['state'] ?? 'added';
            $message = $state === 'removed'
                ? 'Hai rimosso il tuo Mi piace dal commento.'
                : 'Hai messo Mi piace al commento.';
            setFlash('community', $message, 'success');
        } else {
            $error = $result['message'] ?? 'Impossibile aggiornare il Mi piace.';
            setFlash('community', $error, 'error');
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
$isAjaxLayoutRequest = isset($_GET['ajax']) && $_GET['ajax'] === '1';
$isKnownPage = array_key_exists($pageKey, $availablePages);

$pageFile = $isKnownPage ? $availablePages[$pageKey] : null;
$pageTitle = $isKnownPage
    ? resolvePageTitle($pageKey, $pageTitles, $siteName)
    : 'Pagina non trovata';

$activeNewsArticle = null;
$activeUserProfile = null;
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

if ($isKnownPage && $pageKey === 'user_profile') {
    $requestedUsername = trim((string) ($_GET['username'] ?? ''));
    $requestedUserId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($requestedUsername === '' && $requestedUserId > 0) {
        $userFromId = findUserById($requestedUserId);
        if ($userFromId) {
            $requestedUsername = (string) ($userFromId['username'] ?? '');
        }
    }

    if ($requestedUsername !== '') {
        $viewer = getLoggedInUser();
        $viewerId = isset($viewer['id']) ? (int) $viewer['id'] : 0;
        $activeUserProfile = getUserProfileView($requestedUsername, $viewerId > 0 ? $viewerId : null);
    }

    if ($activeUserProfile) {
        $profileDisplay = trim((string) ($activeUserProfile['display_name'] ?? ''));
        $profileHandle = trim((string) ($activeUserProfile['username'] ?? ''));
        if ($profileDisplay === '' && $profileHandle !== '') {
            $profileDisplay = $profileHandle;
        }
        $pageTitle = $profileDisplay !== ''
            ? $profileDisplay . ' | Profilo tifoso'
            : 'Profilo tifoso';
    } else {
        $pageTitle = 'Profilo non trovato';
    }
}

$currentPage = $isKnownPage ? $pageKey : '';
if ($currentPage === 'news_article') {
    $currentPage = 'news';
}
if ($currentPage === 'user_profile') {
    $currentPage = 'profile';
}

if (!$isAjaxLayoutRequest) {
    require __DIR__ . '/includes/header.php';
}

if ($pageFile && file_exists($pageFile)) {
    include $pageFile;
} else {
    if ($isAjaxLayoutRequest) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'Pagina non trovata',
        ]);
        exit;
    }
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

if (!$isAjaxLayoutRequest) {
    require __DIR__ . '/includes/navbar.php';
    require __DIR__ . '/includes/footer.php';
}
