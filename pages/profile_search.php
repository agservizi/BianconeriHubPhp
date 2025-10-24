<?php
$loggedUser = getLoggedInUser();
$currentUserId = isset($loggedUser['id']) ? (int) $loggedUser['id'] : 0;
$query = trim((string) ($_GET['q'] ?? ''));
$pageNumber = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$perPage = 12;
$offset = ($pageNumber - 1) * $perPage;

$searchData = searchCommunityUsers($query, $perPage, $offset);
$results = $searchData['results'];
$totalResults = (int) ($searchData['total'] ?? 0);
$hasMore = !empty($searchData['has_more']);
$tooShort = !empty($searchData['too_short']);
$minimumLength = (int) ($searchData['minimum_length'] ?? 2);
$hasQuery = $query !== '';
$csrfToken = getCsrfToken();
$baseQueryParams = ['page' => 'profile_search'];
if ($query !== '') {
    $baseQueryParams['q'] = $query;
}

$prevPage = $pageNumber > 1 ? $pageNumber - 1 : null;
$nextPage = $hasMore ? $pageNumber + 1 : null;

$redirectBase = '?page=profile_search';
if ($query !== '') {
    $redirectBase .= '&q=' . urlencode($query);
}
if ($pageNumber > 1) {
    $redirectBase .= '&p=' . $pageNumber;
}

$isAjaxRequest = (isset($_GET['ajax']) && $_GET['ajax'] === '1')
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

ob_start();
?>
<?php if (!$hasQuery): ?>
    <div class="rounded-3xl border border-white/10 bg-black/40 px-4 py-6 text-sm text-gray-300">
        <p>Inizia digitando un nome utente per vedere i profili bianconeri disponibili.</p>
    </div>
<?php elseif ($tooShort): ?>
    <div class="rounded-3xl border border-yellow-400/40 bg-yellow-500/10 px-4 py-6 text-sm text-yellow-200">
        <p>Inserisci almeno <?php echo $minimumLength; ?> caratteri per avviare la ricerca.</p>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-400">
            <p>
                Trovati <span class="font-semibold text-white"><?php echo number_format($totalResults, 0, ',', '.'); ?></span> tifosi con "<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>".
                <?php if ($totalResults > $perPage): ?>
                    <span class="text-xs uppercase tracking-wide text-gray-500">Pagina <?php echo $pageNumber; ?></span>
                <?php endif; ?>
            </p>
            <?php if ($currentUserId <= 0): ?>
                <p class="text-xs text-gray-500">Accedi per seguire i profili trovati.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($results)): ?>
            <ul class="space-y-3">
                <?php foreach ($results as $user):
                    $userId = (int) ($user['id'] ?? 0);
                    $username = $user['username'] ?? '';
                    $badge = $user['badge'] ?? 'Tifoso';
                    $avatarUrl = trim((string) ($user['avatar_url'] ?? ''));
                    $createdAt = (int) ($user['created_at'] ?? time());
                    $joinedLabel = getHumanTimeDiff($createdAt) . ' fa';
                    $initials = strtoupper(substr($username !== '' ? $username : 'BH', 0, 2));
                    $viewerCanFollow = !empty($user['viewer_can_follow']);
                    $isFollowing = !empty($user['is_following']);
                    $isCurrentUser = !empty($user['is_current_user']);
                    $followAction = $isFollowing ? 'unfollow' : 'follow';
                    $followLabel = $isFollowing ? 'Smetti di seguire' : 'Segui';
                    $buttonClasses = $isFollowing
                        ? 'inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black'
                        : 'inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver';
                ?>
                    <li class="rounded-3xl border border-white/10 bg-black/35 px-4 py-4">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-lg font-semibold text-white">
                                    <?php if ($avatarUrl !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar di <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" class="h-full w-full rounded-full object-cover">
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-base font-semibold text-white">
                                        <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($isCurrentUser): ?>
                                            <span class="ml-2 rounded-full bg-white/10 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-gray-300">Sei tu</span>
                                        <?php elseif ($isFollowing): ?>
                                            <span class="ml-2 rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-emerald-200">Seguito</span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="text-sm text-gray-300">Badge: <span class="font-semibold text-white"><?php echo htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'); ?></span></p>
                                    <p class="text-xs uppercase tracking-wide text-gray-500">Iscritto <?php echo htmlspecialchars($joinedLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if ($isCurrentUser): ?>
                                    <span class="rounded-full border border-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Profilo attuale</span>
                                <?php elseif ($viewerCanFollow): ?>
                                    <form method="post" class="inline-flex">
                                        <input type="hidden" name="form_type" value="community_follow">
                                        <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                        <input type="hidden" name="follow_action" value="<?php echo htmlspecialchars($followAction, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirectBase, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="<?php echo $buttonClasses; ?>">
                                            <?php echo htmlspecialchars($followLabel, ENT_QUOTES, 'UTF-8'); ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="?page=login" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                                        Accedi per seguire
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($prevPage || $nextPage): ?>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4 text-sm text-gray-400">
                    <div>
                        <?php if ($prevPage):
                            $prevParams = $baseQueryParams;
                            $prevParams['p'] = $prevPage;
                            $prevUrl = '?' . http_build_query($prevParams);
                        ?>
                            <a
                                href="<?php echo $prevUrl; ?>"
                                class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black"
                                data-profile-search-page-link
                                data-profile-search-page="<?php echo $prevPage; ?>"
                                data-profile-search-query="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 19.5-7.5-7.5 7.5-7.5" />
                                </svg>
                                Pagina precedente
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs uppercase tracking-wide text-gray-500">
                        Pagina <?php echo $pageNumber; ?>
                    </div>
                    <div>
                        <?php if ($nextPage):
                            $nextParams = $baseQueryParams;
                            $nextParams['p'] = $nextPage;
                            $nextUrl = '?' . http_build_query($nextParams);
                        ?>
                            <a
                                href="<?php echo $nextUrl; ?>"
                                class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black"
                                data-profile-search-page-link
                                data-profile-search-page="<?php echo $nextPage; ?>"
                                data-profile-search-query="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                Pagina successiva
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="rounded-3xl border border-white/10 bg-black/40 px-4 py-6 text-sm text-gray-300">
                <?php if ($totalResults > 0 && $pageNumber > 1): ?>
                    <p>Non ci sono risultati in questa pagina. Torna a quella precedente per visualizzare altri tifosi.</p>
                <?php else: ?>
                    <p>Nessun profilo trovato con la chiave inserita. Prova con un altro nickname o verifica la grafia.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php
$resultsHtml = ob_get_clean();

if ($isAjaxRequest) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'query' => $query,
        'page' => $pageNumber,
        'html' => $resultsHtml,
        'total' => $totalResults,
        'has_more' => (bool) $hasMore,
        'too_short' => (bool) $tooShort,
        'minimum_length' => $minimumLength,
        'results_count' => count($results),
        'per_page' => $perPage,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<section
    class="mx-auto max-w-6xl space-y-6 px-2 sm:px-4 lg:px-0"
    data-profile-search-root
    data-profile-search-per-page="<?php echo $perPage; ?>"
    data-profile-search-min-length="<?php echo $minimumLength; ?>"
    data-profile-search-initial-query="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"
    data-profile-search-initial-page="<?php echo $pageNumber; ?>"
>
    <div class="fan-card px-5 py-6 space-y-6">
        <div class="space-y-3">
            <h1 class="text-2xl font-bold text-white">Cerca tifosi</h1>
            <p class="text-sm text-gray-400">Trova profili da seguire per personalizzare il tuo feed e ricevere notifiche sul tuo gruppo di fiducia.</p>
        </div>
        <form
            action="?page=profile_search"
            method="get"
            class="flex flex-col gap-3 rounded-3xl border border-white/10 bg-black/40 p-4 sm:flex-row sm:items-center sm:gap-4"
            data-profile-search-form
        >
            <input type="hidden" name="page" value="profile_search">
            <div class="flex flex-1 items-center gap-3 rounded-2xl border border-white/10 bg-black/40 px-4 py-2 focus-within:border-white/40">
                <label for="profile-search-input" class="sr-only">Cerca username</label>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M18 10.5a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                </svg>
                <input
                    id="profile-search-input"
                    type="search"
                    name="q"
                    value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"
                    class="flex-1 bg-transparent text-base text-white placeholder:text-gray-500 focus:outline-none"
                    placeholder="Inserisci username (min. <?php echo $minimumLength; ?> caratteri)"
                    autocomplete="off"
                    data-profile-search-input
                >
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">
                    Cerca
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8.25 4.5 15.75 12 8.25 19.5" />
                    </svg>
                </button>
                <a
                    href="?page=profile_search"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black<?php echo $hasQuery ? '' : ' hidden'; ?>"
                    data-profile-search-reset
                >
                    Azzera
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>
        </form>
        <div
            class="hidden rounded-3xl border border-white/10 bg-black/40 px-4 py-3 text-sm text-gray-300"
            data-profile-search-status
        ></div>
        <div data-profile-search-results>
            <?php echo $resultsHtml; ?>
        </div>
    </div>
</section>
