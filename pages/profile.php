<?php
if (!isUserLoggedIn()) {
    setFlash('auth', 'Effettua il login per visualizzare il tuo profilo.', 'error');
    header('Location: ?page=login');
    exit;
}

$currentUser = getLoggedInUser();
$userId = (int) ($currentUser['id'] ?? 0);

$baseProfile = [
    'id' => $userId,
    'username' => $currentUser['username'] ?? 'Tifoso',
    'email' => $currentUser['email'] ?? '',
    'badge' => $currentUser['badge'] ?? 'Tifoso',
    'first_name' => $currentUser['first_name'] ?? null,
    'last_name' => $currentUser['last_name'] ?? null,
    'display_name' => $currentUser['display_name'] ?? null,
    'avatar_url' => null,
    'created_at' => time(),
    'updated_at' => null,
    'bio' => '',
    'location' => '',
    'website' => '',
    'favorite_player' => '',
    'favorite_memory' => '',
    'cover_path' => '',
    'followers_count' => 0,
    'following_count' => 0,
];

$profileView = [];
if (!empty($currentUser['username'])) {
    $profileView = getUserProfileView((string) $currentUser['username'], $userId) ?: [];
}

$userDetails = array_merge($baseProfile, $profileView);

$profileSummary = getUserProfileSummary($userId);
$counts = $profileSummary['counts'];

$timezone = new DateTimeZone(date_default_timezone_get() ?: 'Europe/Rome');
$joinTimestamp = (int) ($userDetails['created_at'] ?? time());
$joinDateTime = DateTime::createFromFormat('U', (string) $joinTimestamp) ?: new DateTime('now', $timezone);
$joinDateTime->setTimezone($timezone);
$joinedLabel = formatItalianDate($joinDateTime);

$lastUpdateLabel = 'Non disponibile';
if (!empty($userDetails['updated_at'])) {
    $lastUpdateLabel = getHumanTimeDiff((int) $userDetails['updated_at']);
}

$displayName = trim((string) ($userDetails['display_name'] ?? buildUserDisplayName($userDetails['first_name'] ?? null, $userDetails['last_name'] ?? null, (string) ($userDetails['username'] ?? 'Tifoso'))));
$handle = trim((string) ($userDetails['username'] ?? ''));
$avatarUrl = trim((string) ($userDetails['avatar_url'] ?? ''));
$initialsSource = $displayName !== '' ? $displayName : ($userDetails['username'] ?? 'BH');
$initials = strtoupper(substr($initialsSource, 0, 2));
$coverPath = trim((string) ($userDetails['cover_path'] ?? ''));
$bio = trim((string) ($userDetails['bio'] ?? ''));
$location = trim((string) ($userDetails['location'] ?? ''));
$website = trim((string) ($userDetails['website'] ?? ''));
$favoritePlayer = trim((string) ($userDetails['favorite_player'] ?? ''));
$favoriteMemory = trim((string) ($userDetails['favorite_memory'] ?? ''));
$followersCount = (int) ($userDetails['followers_count'] ?? 0);
$followingCount = (int) ($userDetails['following_count'] ?? 0);

$truncate = static function (string $text, int $limit = 140): string {
    $clean = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    if ($clean === '') {
        return '—';
    }
    if (mb_strlen($clean) <= $limit) {
        return $clean;
    }

    return rtrim(mb_substr($clean, 0, $limit - 1)) . '…';
};

$formatDateTimeLabel = static function (?int $timestamp, string $fallback = 'Data da definire') use ($timezone): string {
    if (!$timestamp) {
        return $fallback;
    }

    $dateTime = DateTime::createFromFormat('U', (string) $timestamp);
    if (!$dateTime) {
        return $fallback;
    }

    $dateTime->setTimezone($timezone);

    return formatItalianDate($dateTime) . ' · ore ' . $dateTime->format('H:i');
};

$number = static function ($value): string {
    return number_format((int) $value, 0, ',', '.');
};

$statusLabels = [
    'published' => 'Pubblicato',
    'scheduled' => 'Programmato',
    'draft' => 'Bozza',
];

$contentTypeLabels = [
    'text' => 'Testo',
    'photo' => 'Foto',
    'gallery' => 'Galleria',
    'poll' => 'Sondaggio',
    'story' => 'Storia',
];

$reactionsReceivedLike = (int) ($counts['reactions_received_breakdown']['like'] ?? 0);
$reactionsReceivedSupport = (int) ($counts['reactions_received_breakdown']['support'] ?? 0);
$reactionsReceivedTotal = max(0, (int) ($counts['reactions_received'] ?? 0));
$receivedLikePercent = $reactionsReceivedTotal > 0 ? min(100, (int) round(($reactionsReceivedLike / $reactionsReceivedTotal) * 100)) : 0;
$receivedSupportPercent = $reactionsReceivedTotal > 0 ? min(100, (int) round(($reactionsReceivedSupport / $reactionsReceivedTotal) * 100)) : 0;

$reactionsLeftLike = (int) ($counts['reactions_left_breakdown']['like'] ?? 0);
$reactionsLeftSupport = (int) ($counts['reactions_left_breakdown']['support'] ?? 0);
$reactionsLeftTotal = max(0, (int) ($counts['reactions_left'] ?? 0));
$leftLikePercent = $reactionsLeftTotal > 0 ? min(100, (int) round(($reactionsLeftLike / $reactionsLeftTotal) * 100)) : 0;
$leftSupportPercent = $reactionsLeftTotal > 0 ? min(100, (int) round(($reactionsLeftSupport / $reactionsLeftTotal) * 100)) : 0;

$primaryStats = [
    ['label' => 'Post pubblicati', 'value' => $counts['posts_published'] ?? 0],
    ['label' => 'Post programmati', 'value' => $counts['posts_scheduled'] ?? 0],
    ['label' => 'Bozze attive', 'value' => $counts['posts_draft'] ?? 0],
    ['label' => 'Sondaggi creati', 'value' => $counts['polls_created'] ?? 0],
];

$secondaryStats = [
    ['label' => 'Commenti community', 'value' => $counts['comments_written'] ?? 0],
    ['label' => 'Commenti news', 'value' => $counts['news_comments_written'] ?? 0],
    ['label' => 'Reazioni ricevute', 'value' => $counts['reactions_received'] ?? 0],
    ['label' => 'Reazioni lasciate', 'value' => $counts['reactions_left'] ?? 0],
    ['label' => 'News preferite', 'value' => $counts['news_likes'] ?? 0],
];

$recentPosts = $profileSummary['recent_posts'] ?? [];
$scheduledPosts = $profileSummary['scheduled_posts'] ?? [];
$draftPosts = $profileSummary['draft_posts'] ?? [];
$recentComments = $profileSummary['recent_comments'] ?? [];
$recentNewsComments = $profileSummary['recent_news_comments'] ?? [];
$recentNewsLikes = $profileSummary['recent_news_likes'] ?? [];
?>
<section class="mx-auto max-w-6xl space-y-10 px-2 sm:px-4 lg:px-0">
    <div class="overflow-hidden rounded-3xl border border-white/10 bg-black/60 shadow-xl">
        <div class="relative min-h-[18rem] sm:min-h-[20rem]">
            <?php if ($coverPath !== ''): ?>
                <img src="<?php echo htmlspecialchars($coverPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Cover profilo" class="absolute inset-0 h-full w-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/40 to-transparent"></div>
            <?php else: ?>
                <div class="absolute inset-0 bg-gradient-to-br from-black via-gray-900 to-gray-700"></div>
            <?php endif; ?>

            <div class="relative z-10 flex flex-col gap-6 px-4 pb-6 pt-24 sm:px-6 sm:pt-28 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-end sm:gap-5 sm:text-left">
                    <div class="mb-4 sm:-mb-16 rounded-full border-4 border-black/70 bg-white/10 shadow-xl mx-auto sm:mx-0">
                        <div class="h-28 w-28 sm:h-32 sm:w-32 overflow-hidden rounded-full bg-white/5 text-3xl font-semibold text-white">
                            <?php if ($avatarUrl !== ''): ?>
                                <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar di <?php echo htmlspecialchars($displayName !== '' ? $displayName : $handle, ENT_QUOTES, 'UTF-8'); ?>" class="h-full w-full object-cover">
                            <?php else: ?>
                                <div class="flex h-full w-full items-center justify-center">
                                    <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="pb-4 text-center sm:text-left">
                        <div class="flex flex-wrap items-center justify-center gap-3 sm:justify-start">
                            <h1 class="text-3xl font-semibold text-white md:text-4xl"><?php echo htmlspecialchars($displayName !== '' ? $displayName : $handle, ENT_QUOTES, 'UTF-8'); ?></h1>
                            <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white/80"><?php echo htmlspecialchars($userDetails['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($handle !== ''): ?>
                                <span class="text-sm text-white/60">@<?php echo htmlspecialchars($handle, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center justify-center gap-4 text-xs text-white/70 sm:justify-start">
                            <span><?php echo $number($followersCount); ?> follower</span>
                            <span>Segue <?php echo $number($followingCount); ?></span>
                            <span>Iscritto dal <?php echo htmlspecialchars($joinedLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!empty($userDetails['email'])): ?>
                                <span><?php echo htmlspecialchars($userDetails['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-2 pb-4 sm:justify-end">
                    <a href="?page=community" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">
                        Crea un post
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                        </svg>
                    </a>
                    <a href="?page=partite" class="inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                        Calendario match
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v3m10.5-3v3M4.5 9h15M5.25 7.5h13.5A1.5 1.5 0 0 1 20.25 9v11.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V9z" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        <nav class="border-t border-white/10 bg-black/70 px-6">
            <ul class="flex flex-wrap gap-3 py-4 text-sm font-medium text-white/70">
                <li><a class="inline-flex items-center gap-2 rounded-full border border-transparent px-3 py-1.5 transition-all hover:border-white/40 hover:text-white" href="#panoramica">Panoramica</a></li>
                <li><a class="inline-flex items-center gap-2 rounded-full border border-transparent px-3 py-1.5 transition-all hover:border-white/40 hover:text-white" href="#community">Community</a></li>
                <li><a class="inline-flex items-center gap-2 rounded-full border border-transparent px-3 py-1.5 transition-all hover:border-white/40 hover:text-white" href="#news">News</a></li>
                <li><a class="inline-flex items-center gap-2 rounded-full border border-white/20 px-3 py-1.5 text-white transition-all hover:border-white/40" href="?page=profile_settings">Impostazioni</a></li>
            </ul>
        </nav>
    </div>

    <div id="panoramica" class="space-y-8">
        <div class="fan-card px-5 py-6">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-white">Il tuo impatto</h2>
                    <p class="text-sm text-white/60">Uno sguardo rapido alle tue attività più importanti nella community.</p>
                </div>
                <p class="text-xs uppercase tracking-wide text-white/50">Ultimo aggiornamento profilo: <span class="text-white/80"><?php echo htmlspecialchars($lastUpdateLabel, ENT_QUOTES, 'UTF-8'); ?></span></p>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <?php foreach ($primaryStats as $stat): ?>
                    <div class="rounded-2xl border border-white/10 bg-black/40 px-4 py-4">
                        <p class="text-xs uppercase tracking-wide text-white/50"><?php echo htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="mt-3 text-3xl font-semibold text-white"><?php echo $number($stat['value']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <?php foreach ($secondaryStats as $stat): ?>
                    <div class="rounded-2xl border border-white/10 bg-black/30 px-4 py-4">
                        <p class="text-xs uppercase tracking-wide text-white/50"><?php echo htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="mt-3 text-2xl font-semibold text-white"><?php echo $number($stat['value']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr),minmax(0,1fr)]">
            <div class="space-y-6">
                <div class="fan-card px-5 py-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Attività in evidenza</h3>
                        <span class="text-xs uppercase tracking-wide text-white/50">Ultimi 5 post</span>
                    </div>
                    <?php if ($recentPosts): ?>
                        <ul class="mt-5 space-y-4 text-sm text-white/80">
                            <?php foreach ($recentPosts as $post):
                                $postId = (int) ($post['id'] ?? 0);
                                $status = $statusLabels[$post['status']] ?? ucfirst((string) ($post['status'] ?? '')); 
                                $contentType = $contentTypeLabels[$post['content_type']] ?? ucfirst((string) ($post['content_type'] ?? ''));
                                $publishedTimestamp = (int) ($post['published_at'] ?? $post['created_at'] ?? time());
                                $publishedLabel = getHumanTimeDiff($publishedTimestamp);
                                $previewSource = $post['content'] !== '' ? $post['content'] : ($post['poll_question'] ?? '');
                                $preview = $truncate($previewSource, 160);
                            ?>
                                <li class="rounded-2xl border border-white/10 bg-black/40 px-4 py-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-[0.65rem] uppercase tracking-wide text-white/50">
                                        <span class="inline-flex items-center gap-2">
                                            <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs font-semibold text-white/80"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs font-semibold text-white/60"><?php echo htmlspecialchars($contentType, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </span>
                                        <span><?php echo htmlspecialchars($publishedLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <p class="mt-3 text-sm text-white/90"><?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div class="mt-3 flex items-center justify-between text-xs text-white/50">
                                        <a href="?page=community#post-<?php echo $postId; ?>" class="inline-flex items-center gap-1 text-white/80 transition-all hover:text-white">
                                            Apri nel feed
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="h-3 w-3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                                            </svg>
                                        </a>
                                        <span>ID #<?php echo $postId; ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="mt-5 text-sm text-white/60">Non hai ancora pubblicato post. Scrivi qualcosa nella community per iniziare la tua bacheca!</p>
                    <?php endif; ?>
                </div>

                <div class="fan-card px-5 py-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Reazioni della curva</h3>
                        <span class="text-xs uppercase tracking-wide text-white/50">Interazioni recenti</span>
                    </div>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-4">
                            <div class="flex items-center justify-between text-xs text-emerald-100">
                                <span class="uppercase tracking-wide">Ricevute</span>
                                <span><?php echo $number($reactionsReceivedTotal); ?> totali</span>
                            </div>
                            <div class="mt-3 space-y-3 text-xs text-emerald-100">
                                <div>
                                    <div class="flex items-center justify-between"><span>Mi piace</span><span><?php echo $number($reactionsReceivedLike); ?></span></div>
                                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-white/10">
                                        <div class="h-full rounded-full bg-emerald-400" style="width: <?php echo $receivedLikePercent; ?>%;"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between"><span>Supporti</span><span><?php echo $number($reactionsReceivedSupport); ?></span></div>
                                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-white/10">
                                        <div class="h-full rounded-full bg-emerald-300" style="width: <?php echo $receivedSupportPercent; ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-sky-400/30 bg-sky-500/10 px-4 py-4">
                            <div class="flex items-center justify-between text-xs text-sky-100">
                                <span class="uppercase tracking-wide">Lasciate</span>
                                <span><?php echo $number($reactionsLeftTotal); ?> totali</span>
                            </div>
                            <div class="mt-3 space-y-3 text-xs text-sky-100">
                                <div>
                                    <div class="flex items-center justify-between"><span>Mi piace</span><span><?php echo $number($reactionsLeftLike); ?></span></div>
                                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-white/10">
                                        <div class="h-full rounded-full bg-sky-400" style="width: <?php echo $leftLikePercent; ?>%;"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between"><span>Supporti</span><span><?php echo $number($reactionsLeftSupport); ?></span></div>
                                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-white/10">
                                        <div class="h-full rounded-full bg-sky-300" style="width: <?php echo $leftSupportPercent; ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <aside class="space-y-6">
                <div class="fan-card px-5 py-6">
                    <h3 class="text-lg font-semibold text-white">Informazioni principali</h3>
                    <div class="mt-4 space-y-4 text-sm text-white/80">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-white/50">Bio</p>
                            <p class="mt-1"><?php echo htmlspecialchars($bio !== '' ? $bio : 'Non hai ancora aggiunto una bio.', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-white/50">Città</p>
                            <p class="mt-1"><?php echo htmlspecialchars($location !== '' ? $location : 'Località non indicata.', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-white/50">Sito web</p>
                            <?php if ($website !== ''): ?>
                                <a class="mt-1 inline-flex items-center gap-1 text-juventus-yellow transition-colors hover:text-white" href="<?php echo htmlspecialchars($website, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                    <?php echo htmlspecialchars($website, ENT_QUOTES, 'UTF-8'); ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5 21 3m0 0h-5.25M21 3v5.25M10.5 13.5 3 21m0 0h5.25M3 21v-5.25" />
                                    </svg>
                                </a>
                            <?php else: ?>
                                <p class="mt-1">Nessun link disponibile.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="fan-card px-5 py-6">
                    <h3 class="text-lg font-semibold text-white">Preferiti bianconeri</h3>
                    <div class="mt-4 space-y-4 text-sm text-white/80">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-white/50">Giocatore del cuore</p>
                            <p class="mt-1"><?php echo htmlspecialchars($favoritePlayer !== '' ? $favoritePlayer : 'Seleziona il tuo giocatore preferito nelle impostazioni.', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-white/50">Momento indelebile</p>
                            <p class="mt-1"><?php echo htmlspecialchars($favoriteMemory !== '' ? $favoriteMemory : 'Condividi il tuo ricordo juventino nelle impostazioni.', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <div id="community" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-white">Community</h2>
                <p class="text-sm text-white/60">Pianifica i prossimi contenuti e tieni d'occhio le ultime conversazioni.</p>
            </div>
            <a href="?page=community" class="text-sm font-semibold text-juventus-yellow transition-colors hover:text-white">Vai al feed</a>
        </div>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr),minmax(0,1fr)]">
            <div class="fan-card px-5 py-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-white">Commenti recenti</h3>
                    <span class="text-xs uppercase tracking-wide text-white/50">Ultimi 5</span>
                </div>
                <?php if ($recentComments): ?>
                    <ul class="mt-5 space-y-4 text-sm text-white/80">
                        <?php foreach ($recentComments as $comment):
                            $postId = (int) ($comment['post_id'] ?? 0);
                            $commentPreview = $truncate($comment['content'] ?? '', 140);
                            $targetPreview = $truncate($comment['post_content'] ?? '', 80);
                            $timeLabel = getHumanTimeDiff((int) ($comment['created_at'] ?? time()));
                        ?>
                            <li class="rounded-2xl border border-white/10 bg-black/35 px-4 py-4">
                                <div class="flex items-center justify-between text-xs uppercase tracking-wide text-white/50">
                                    <span><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span>#<?php echo $postId; ?></span>
                                </div>
                                <p class="mt-3 text-sm text-white/90"><?php echo htmlspecialchars($commentPreview, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="mt-2 text-xs text-white/50">Su: <?php echo htmlspecialchars($targetPreview, ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="mt-3 text-xs">
                                    <a href="?page=community#post-<?php echo $postId; ?>" class="inline-flex items-center gap-1 text-white/80 transition-all hover:text-white">
                                        Vai alla discussione
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="h-3 w-3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                                        </svg>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="mt-5 text-sm text-white/60">Non ci sono ancora commenti. Rispondi a un post per avviare una conversazione.</p>
                <?php endif; ?>
            </div>
            <aside class="space-y-6">
                <div class="fan-card px-5 py-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Programmato</h3>
                        <span class="text-xs uppercase tracking-wide text-white/50">Prossimi lanci</span>
                    </div>
                    <?php if ($scheduledPosts): ?>
                        <ul class="mt-5 space-y-4 text-sm text-white/80">
                            <?php foreach ($scheduledPosts as $post):
                                $postId = (int) ($post['id'] ?? 0);
                                $scheduledFor = $formatDateTimeLabel($post['scheduled_for'] ?? null);
                                $preview = $truncate($post['content'] ?? '', 120);
                            ?>
                                <li class="rounded-2xl border border-white/10 bg-black/35 px-4 py-4">
                                    <p class="text-xs uppercase tracking-wide text-white/50">#<?php echo $postId; ?> · <?php echo htmlspecialchars($scheduledFor, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="mt-2"><?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs uppercase tracking-wide text-white/60">
                                        <a href="?action=community_compose&amp;draft=<?php echo $postId; ?>" class="inline-flex items-center gap-1 rounded-full border border-white/20 px-3 py-1 font-semibold text-white transition-all hover:border-white/40">Modifica programma</a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="mt-5 text-sm text-white/60">Nessun post programmato. Usa il composer per impostare un orario.</p>
                    <?php endif; ?>
                </div>
                <div class="fan-card px-5 py-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Bozze salvate</h3>
                        <span class="text-xs uppercase tracking-wide text-white/50">Da perfezionare</span>
                    </div>
                    <?php if ($draftPosts): ?>
                        <ul class="mt-5 space-y-4 text-sm text-white/80">
                            <?php foreach ($draftPosts as $post):
                                $postId = (int) ($post['id'] ?? 0);
                                $updatedLabel = !empty($post['updated_at']) ? getHumanTimeDiff((int) $post['updated_at']) : getHumanTimeDiff((int) ($post['created_at'] ?? time()));
                                $preview = $truncate($post['content'] ?? '', 120);
                            ?>
                                <li class="rounded-2xl border border-white/10 bg-black/35 px-4 py-4">
                                    <p class="text-xs uppercase tracking-wide text-white/50">#<?php echo $postId; ?> · aggiornato <?php echo htmlspecialchars($updatedLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="mt-2"><?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs uppercase tracking-wide text-white/60">
                                        <a href="?action=community_compose&amp;draft=<?php echo $postId; ?>" class="inline-flex items-center gap-1 rounded-full border border-white/20 px-3 py-1 font-semibold text-white transition-all hover:border-white/40">Riprendi bozza</a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="mt-5 text-sm text-white/60">Al momento non hai bozze. Preparane una per la prossima partita.</p>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>

    <div id="news" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-white">News</h2>
                <p class="text-sm text-white/60">Tieni traccia della tua voce sugli articoli del club.</p>
            </div>
            <a href="?page=news" class="text-sm font-semibold text-juventus-yellow transition-colors hover:text-white">Vai alle news</a>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="fan-card px-5 py-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-white">Commenti sugli articoli</h3>
                    <span class="text-xs uppercase tracking-wide text-white/50">Attività recente</span>
                </div>
                <?php if ($recentNewsComments): ?>
                    <ul class="mt-5 space-y-4 text-sm text-white/80">
                        <?php foreach ($recentNewsComments as $comment):
                            $slug = trim((string) ($comment['news_slug'] ?? ''));
                            $link = $slug !== '' ? '?page=news_article&slug=' . urlencode($slug) : '?page=news';
                            $preview = $truncate($comment['content'] ?? '', 120);
                            $timeLabel = getHumanTimeDiff((int) ($comment['created_at'] ?? time()));
                        ?>
                            <li class="rounded-2xl border border-white/10 bg-black/35 px-4 py-4">
                                <div class="flex items-center justify-between text-xs uppercase tracking-wide text-white/50">
                                    <span><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <p class="mt-3 text-sm text-white/90"><?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?></p>
                                <a href="<?php echo $link; ?>" class="mt-3 inline-flex items-center gap-1 text-xs text-white/80 transition-all hover:text-white">
                                    Apri articolo
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="h-3 w-3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                                    </svg>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="mt-5 text-sm text-white/60">Ancora nessun commento sulle news. Commenta un articolo per comparire qui.</p>
                <?php endif; ?>
            </div>
            <div class="fan-card px-5 py-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-white">Articoli preferiti</h3>
                    <span class="text-xs uppercase tracking-wide text-white/50">Mi piace lasciati</span>
                </div>
                <?php if ($recentNewsLikes): ?>
                    <ul class="mt-5 space-y-4 text-sm text-white/80">
                        <?php foreach ($recentNewsLikes as $like):
                            $slug = trim((string) ($like['news_slug'] ?? ''));
                            $link = $slug !== '' ? '?page=news_article&slug=' . urlencode($slug) : '?page=news';
                            $title = $like['news_title'] ?? 'Articolo';
                            $timeLabel = getHumanTimeDiff((int) ($like['created_at'] ?? time()));
                        ?>
                            <li class="rounded-2xl border border-white/10 bg-black/35 px-4 py-4">
                                <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="mt-3 flex items-center justify-between text-xs text-white/50">
                                    <span><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <a href="<?php echo $link; ?>" class="inline-flex items-center gap-1 text-white/80 transition-all hover:text-white">
                                        Leggi
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="h-3 w-3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                                        </svg>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="mt-5 text-sm text-white/60">Non hai ancora messo Mi piace alle news. Appena ne aggiungi uno, apparirà qui.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
