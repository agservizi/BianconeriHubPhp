<?php
if (!isUserLoggedIn()) {
    setFlash('auth', 'Effettua il login per visualizzare il tuo profilo.', 'error');
    header('Location: ?page=login');
    exit;
}

$currentUser = getLoggedInUser();
$userId = (int) ($currentUser['id'] ?? 0);
$userDetails = findUserById($userId) ?? [
    'id' => $userId,
    'username' => $currentUser['username'] ?? 'Tifoso',
    'email' => $currentUser['email'] ?? '',
    'badge' => $currentUser['badge'] ?? 'Tifoso',
    'avatar_url' => null,
    'created_at' => time(),
    'updated_at' => null,
];

$profileSummary = getUserProfileSummary($userId);
$counts = $profileSummary['counts'];

$timezone = new DateTimeZone(date_default_timezone_get() ?: 'Europe/Rome');
$joinTimestamp = (int) ($userDetails['created_at'] ?? time());
$joinDateTime = DateTime::createFromFormat('U', (string) $joinTimestamp) ?: new DateTime('now', $timezone);
$joinDateTime->setTimezone($timezone);
$joinedLabel = formatItalianDate($joinDateTime);

$lastUpdateLabel = 'Non disponibile';
if (!empty($userDetails['updated_at'])) {
    $lastUpdateLabel = getHumanTimeDiff((int) $userDetails['updated_at']) . ' fa';
}

$avatarUrl = trim((string) ($userDetails['avatar_url'] ?? ''));
$initials = strtoupper(substr($userDetails['username'] ?? 'BH', 0, 2));

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
?>
<section class="mx-auto max-w-6xl space-y-6 px-2 sm:px-4 lg:px-0">
    <div class="fan-card px-5 py-6 space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-white/10 text-2xl font-bold text-white">
                    <?php if ($avatarUrl !== ''): ?>
                        <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar di <?php echo htmlspecialchars($userDetails['username'], ENT_QUOTES, 'UTF-8'); ?>" class="h-full w-full rounded-full object-cover">
                    <?php else: ?>
                        <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </div>
                <div class="space-y-1">
                    <h1 class="text-xl font-semibold text-white"><?php echo htmlspecialchars($userDetails['username'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="text-sm text-gray-300">Badge attuale: <span class="font-semibold text-white"><?php echo htmlspecialchars($userDetails['badge'], ENT_QUOTES, 'UTF-8'); ?></span></p>
                    <?php if (!empty($userDetails['email'])): ?>
                        <p class="text-xs text-gray-400">Email: <?php echo htmlspecialchars($userDetails['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="?page=community" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">
                    Apri il composer
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                    </svg>
                </a>
                <a href="?page=partite" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                    Calendario match
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v3m10.5-3v3M4.5 9h15M5.25 7.5h13.5A1.5 1.5 0 0 1 20.25 9v11.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V9A1.5 1.5 0 0 1 5.25 7.5Z" />
                    </svg>
                </a>
            </div>
        </div>
        <div class="grid gap-3 text-sm text-gray-300 sm:grid-cols-3">
            <div class="rounded-2xl border border-white/10 bg-black/40 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-gray-500">Iscritto dal</p>
                <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($joinedLabel, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-black/40 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-gray-500">Totale contenuti</p>
                <p class="text-sm font-semibold text-white"><?php echo number_format((int) ($counts['posts_total'] ?? 0), 0, ',', '.'); ?> post</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-black/40 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-gray-500">Ultimo aggiornamento profilo</p>
                <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($lastUpdateLabel, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    </div>

    <div class="fan-card px-5 py-6 space-y-5">
        <div class="space-y-1">
            <h2 class="text-lg font-semibold text-white">Statistiche personali</h2>
            <p class="text-sm text-gray-400">Uno sguardo rapido ai tuoi contributi più recenti nella curva digitale.</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($primaryStats as $stat): ?>
                <div class="rounded-2xl border border-white/10 bg-black/40 px-4 py-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500"><?php echo htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="mt-2 text-2xl font-semibold text-white"><?php echo number_format((int) $stat['value'], 0, ',', '.'); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <?php foreach ($secondaryStats as $stat): ?>
                <div class="rounded-2xl border border-white/10 bg-black/30 px-4 py-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500"><?php echo htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="mt-2 text-xl font-semibold text-white"><?php echo number_format((int) $stat['value'], 0, ',', '.'); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="grid gap-3 lg:grid-cols-2">
            <div class="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs uppercase tracking-wide text-emerald-200">Reazioni ricevute</p>
                    <span class="text-xs text-emerald-100"><?php echo number_format($reactionsReceivedTotal, 0, ',', '.'); ?> totali</span>
                </div>
                <div class="mt-3 space-y-2">
                    <div>
                        <div class="flex items-center justify-between text-xs text-emerald-100">
                            <span>Mi piace</span>
                            <span><?php echo number_format($reactionsReceivedLike, 0, ',', '.'); ?></span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full bg-emerald-400" style="width: <?php echo $receivedLikePercent; ?>%;"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-xs text-emerald-100">
                            <span>Supporti</span>
                            <span><?php echo number_format($reactionsReceivedSupport, 0, ',', '.'); ?></span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full bg-emerald-300" style="width: <?php echo $receivedSupportPercent; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-sky-400/30 bg-sky-500/10 px-4 py-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs uppercase tracking-wide text-sky-200">Reazioni lasciate</p>
                    <span class="text-xs text-sky-100"><?php echo number_format($reactionsLeftTotal, 0, ',', '.'); ?> totali</span>
                </div>
                <div class="mt-3 space-y-2">
                    <div>
                        <div class="flex items-center justify-between text-xs text-sky-100">
                            <span>Mi piace</span>
                            <span><?php echo number_format($reactionsLeftLike, 0, ',', '.'); ?></span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full bg-sky-400" style="width: <?php echo $leftLikePercent; ?>%;"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-xs text-sky-100">
                            <span>Supporti</span>
                            <span><?php echo number_format($reactionsLeftSupport, 0, ',', '.'); ?></span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full bg-sky-300" style="width: <?php echo $leftSupportPercent; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,3fr),minmax(0,2fr)]">
        <div class="space-y-6">
            <div class="fan-card px-5 py-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-white">Ultimi post pubblicati</h2>
                    <span class="text-xs uppercase tracking-wide text-gray-500">Ultimi 5</span>
                </div>
                <?php if (!empty($profileSummary['recent_posts'])): ?>
                    <ul class="space-y-3 text-sm text-gray-200">
                        <?php foreach ($profileSummary['recent_posts'] as $post):
                            $postId = (int) ($post['id'] ?? 0);
                            $status = $statusLabels[$post['status']] ?? ucfirst($post['status']);
                            $contentType = $contentTypeLabels[$post['content_type']] ?? ucfirst($post['content_type']);
                            $publishedTimestamp = (int) ($post['published_at'] ?? $post['created_at']);
                            $publishedLabel = getHumanTimeDiff($publishedTimestamp) . ' fa';
                            $preview = $truncate($post['content'] !== '' ? $post['content'] : ($post['poll_question'] ?? ''), 160);
                        ?>
                            <li class="rounded-2xl border border-white/10 bg-black/40 px-4 py-3">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-[0.65rem] uppercase tracking-wide text-gray-500">
                                    <span class="inline-flex items-center gap-2">
                                        <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs font-semibold text-white/80"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs font-semibold text-white/60"><?php echo htmlspecialchars($contentType, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                    <span><?php echo htmlspecialchars($publishedLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <p class="mt-2 text-sm text-gray-100"><?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                                    <a href="?page=community#post-<?php echo $postId; ?>" class="inline-flex items-center gap-1 text-white/80 transition-all hover:text-white">
                                        Apri nel feed
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                                        </svg>
                                    </a>
                                    <span class="text-gray-500">ID #<?php echo $postId; ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-sm text-gray-400">Non hai ancora pubblicato post. Inizia a scrivere nella community!</p>
                <?php endif; ?>
            </div>

            <div class="fan-card px-5 py-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-white">Bozze e contenuti programmati</h2>
                    <span class="text-xs uppercase tracking-wide text-gray-500">Prossimi step</span>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-3">
                        <h3 class="text-xs uppercase tracking-wide text-gray-500">Programmato</h3>
                        <?php if (!empty($profileSummary['scheduled_posts'])): ?>
                            <ul class="space-y-3 text-sm text-gray-200">
                                <?php foreach ($profileSummary['scheduled_posts'] as $post):
                                    $postId = (int) ($post['id'] ?? 0);
                                    $scheduledFor = $formatDateTimeLabel($post['scheduled_for']);
                                    $preview = $truncate($post['content'], 120);
                                ?>
                                    <li class="rounded-2xl border border-white/10 bg-black/30 px-4 py-3">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">#<?php echo $postId; ?> · <?php echo htmlspecialchars($scheduledFor, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <p class="mt-2 text-sm text-gray-100"><?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-sm text-gray-400">Nessun post programmato. Programma un lancio per la prossima partita!</p>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-3">
                        <h3 class="text-xs uppercase tracking-wide text-gray-500">Bozze</h3>
                        <?php if (!empty($profileSummary['draft_posts'])): ?>
                            <ul class="space-y-3 text-sm text-gray-200">
                                <?php foreach ($profileSummary['draft_posts'] as $post):
                                    $postId = (int) ($post['id'] ?? 0);
                                    $updatedLabel = !empty($post['updated_at']) ? getHumanTimeDiff((int) $post['updated_at']) . ' fa' : getHumanTimeDiff((int) $post['created_at']) . ' fa';
                                    $preview = $truncate($post['content'], 120);
                                ?>
                                    <li class="rounded-2xl border border-white/10 bg-black/30 px-4 py-3">
                                        <p class="text-xs uppercase tracking-wide text-gray-500">#<?php echo $postId; ?> · aggiornato <?php echo htmlspecialchars($updatedLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <p class="mt-2 text-sm text-gray-100"><?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-sm text-gray-400">Non ci sono bozze salvate. Usa il composer per preparare contenuti in anticipo.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="fan-card px-5 py-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-white">Commenti recenti</h2>
                    <span class="text-xs uppercase tracking-wide text-gray-500">Ultimi 5</span>
                </div>
                <?php if (!empty($profileSummary['recent_comments'])): ?>
                    <ul class="space-y-3 text-sm text-gray-200">
                        <?php foreach ($profileSummary['recent_comments'] as $comment):
                            $postId = (int) ($comment['post_id'] ?? 0);
                            $commentPreview = $truncate($comment['content'], 120);
                            $targetPreview = $truncate($comment['post_content'], 80);
                            $timeLabel = getHumanTimeDiff((int) ($comment['created_at'] ?? time())) . ' fa';
                        ?>
                            <li class="rounded-2xl border border-white/10 bg-black/35 px-4 py-3">
                                <div class="flex items-center justify-between text-xs uppercase tracking-wide text-gray-500">
                                    <span><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span>#<?php echo $postId; ?></span>
                                </div>
                                <p class="mt-2 text-sm text-white/90"><?php echo htmlspecialchars($commentPreview, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="mt-2 text-xs text-gray-400">Su: <?php echo htmlspecialchars($targetPreview, ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="mt-3 text-xs">
                                    <a href="?page=community#post-<?php echo $postId; ?>" class="inline-flex items-center gap-1 text-white/80 transition-all hover:text-white">
                                        Vai alla discussione
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                                        </svg>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-sm text-gray-400">Ancora nessun commento nella community. Rispondi a un post per comparire qui.</p>
                <?php endif; ?>
            </div>

            <div class="fan-card px-5 py-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-white">Attività sulle news</h2>
                    <span class="text-xs uppercase tracking-wide text-gray-500">Ultimi aggiornamenti</span>
                </div>
                <div class="space-y-5">
                    <div>
                        <h3 class="text-xs uppercase tracking-wide text-gray-500">Commenti</h3>
                        <?php if (!empty($profileSummary['recent_news_comments'])): ?>
                            <ul class="mt-2 space-y-3 text-sm text-gray-200">
                                <?php foreach ($profileSummary['recent_news_comments'] as $comment):
                                    $slug = trim((string) ($comment['news_slug'] ?? ''));
                                    $link = $slug !== '' ? '?page=news_article&slug=' . urlencode($slug) : '?page=news';
                                    $commentPreview = $truncate($comment['content'], 110);
                                    $timeLabel = getHumanTimeDiff((int) ($comment['created_at'] ?? time())) . ' fa';
                                ?>
                                    <li class="rounded-2xl border border-white/10 bg-black/35 px-4 py-3">
                                        <div class="flex items-center justify-between text-xs uppercase tracking-wide text-gray-500">
                                            <span><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <p class="mt-2 text-sm text-white/90"><?php echo htmlspecialchars($commentPreview, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <a href="<?php echo $link; ?>" class="mt-2 inline-flex items-center gap-1 text-xs text-white/80 transition-all hover:text-white">
                                            Apri articolo
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                                            </svg>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="mt-2 text-sm text-gray-400">Nessun commento sulle news nelle ultime settimane.</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="text-xs uppercase tracking-wide text-gray-500">News preferite</h3>
                        <?php if (!empty($profileSummary['recent_news_likes'])): ?>
                            <ul class="mt-2 space-y-3 text-sm text-gray-200">
                                <?php foreach ($profileSummary['recent_news_likes'] as $liked):
                                    $slug = trim((string) ($liked['news_slug'] ?? ''));
                                    $link = $slug !== '' ? '?page=news_article&slug=' . urlencode($slug) : '?page=news';
                                    $timeLabel = getHumanTimeDiff((int) ($liked['created_at'] ?? time())) . ' fa';
                                ?>
                                    <li class="rounded-2xl border border-white/10 bg-black/30 px-4 py-3">
                                        <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($liked['news_title'] ?? 'Articolo', ENT_QUOTES, 'UTF-8'); ?></p>
                                        <div class="mt-2 flex items-center justify-between text-xs text-gray-500">
                                            <span><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <a href="<?php echo $link; ?>" class="inline-flex items-center gap-1 text-white/80 transition-all hover:text-white">
                                                Leggi
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                                                </svg>
                                            </a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="mt-2 text-sm text-gray-400">Non hai ancora messo Mi piace alle news. Scopri le ultime storie dal club.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
