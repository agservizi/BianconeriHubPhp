<?php
$profile = $activeUserProfile ?? null;

if (!$profile) {
    ?>
    <section class="mx-auto max-w-3xl space-y-4 px-4 py-12 text-center sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-white/10 bg-black/50 px-6 py-10 shadow-xl">
            <h1 class="text-3xl font-semibold text-white">Profilo non disponibile</h1>
            <p class="mt-3 text-sm text-white/60">Il tifoso che stai cercando potrebbe aver cambiato nickname oppure il link non è corretto.</p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <a href="?page=profile_search" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                    Cerca un altro profilo
                </a>
                <a href="?page=community" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">
                    Torna alla community
                </a>
            </div>
        </div>
    </section>
    <?php
    return;
}

$profileId = (int) ($profile['id'] ?? 0);
$displayName = trim((string) ($profile['display_name'] ?? ''));
$handle = trim((string) ($profile['username'] ?? ''));
if ($displayName === '' && $handle !== '') {
    $displayName = $handle;
}
$badge = trim((string) ($profile['badge'] ?? 'Tifoso'));
$avatarUrl = trim((string) ($profile['avatar_url'] ?? ''));
$coverPath = trim((string) ($profile['cover_path'] ?? ''));
$bio = trim((string) ($profile['bio'] ?? ''));
$location = trim((string) ($profile['location'] ?? ''));
$website = trim((string) ($profile['website'] ?? ''));
$favoritePlayer = trim((string) ($profile['favorite_player'] ?? ''));
$favoriteMemory = trim((string) ($profile['favorite_memory'] ?? ''));
$followersCount = (int) ($profile['followers_count'] ?? 0);
$followingCount = (int) ($profile['following_count'] ?? 0);
$viewerCanFollow = !empty($profile['viewer_can_follow']);
$isFollowing = !empty($profile['is_following']);
$isCurrentUser = !empty($profile['is_current_user']);
$followAction = $isFollowing ? 'unfollow' : 'follow';
$followLabel = $isFollowing ? 'Smetti di seguire' : 'Segui';
$followButtonClasses = $isFollowing
    ? 'inline-flex items-center gap-2 rounded-full border border-white/15 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black'
    : 'inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver';
$profileUrl = '?page=user_profile';
if ($handle !== '') {
    $profileUrl .= '&username=' . urlencode($handle);
} elseif ($profileId > 0) {
    $profileUrl .= '&id=' . $profileId;
}
$csrfToken = getCsrfToken();

$timezone = new DateTimeZone(date_default_timezone_get() ?: 'Europe/Rome');
$joinTimestamp = (int) ($profile['created_at'] ?? time());
$joinDateTime = DateTime::createFromFormat('U', (string) $joinTimestamp) ?: new DateTime('now', $timezone);
$joinDateTime->setTimezone($timezone);
$joinedLabel = formatItalianDate($joinDateTime);

$lastUpdateLabel = 'Non disponibile';
if (!empty($profile['updated_at'])) {
    $lastUpdateLabel = getHumanTimeDiff((int) $profile['updated_at']);
}

$initialsSource = $displayName !== '' ? $displayName : ($handle !== '' ? $handle : 'BH');
$initials = strtoupper(substr($initialsSource, 0, 2));

$summary = $profileId > 0 ? getUserProfileSummary($profileId) : [];
$counts = $summary['counts'] ?? [];
$recentPosts = array_slice($summary['recent_posts'] ?? [], 0, 3);
$recentComments = array_slice($summary['recent_comments'] ?? [], 0, 3);
$recentNewsComments = array_slice($summary['recent_news_comments'] ?? [], 0, 3);

$statCards = [
    ['label' => 'Post pubblicati', 'value' => $counts['posts_published'] ?? 0],
    ['label' => 'Commenti community', 'value' => $counts['comments_written'] ?? 0],
    ['label' => 'Reazioni ricevute', 'value' => $counts['reactions_received'] ?? 0],
    ['label' => 'News preferite', 'value' => $counts['news_likes'] ?? 0],
];

$number = static function ($value): string {
    return number_format((int) $value, 0, ',', '.');
};

$truncate = static function (string $text, int $limit = 140): string {
    $clean = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    if ($clean === '') {
        return 'Nessun contenuto disponibile';
    }
    if (mb_strlen($clean) <= $limit) {
        return $clean;
    }

    $sliceLength = max(0, $limit - 3);

    return rtrim(mb_substr($clean, 0, $sliceLength)) . '...';
};

$formatTimeline = static function (?int $timestamp) use ($timezone): string {
    if (!$timestamp) {
        return 'Data non disponibile';
    }

    $dateTime = DateTime::createFromFormat('U', (string) $timestamp);
    if (!$dateTime) {
        return 'Data non disponibile';
    }

    $dateTime->setTimezone($timezone);

    return formatItalianDate($dateTime) . ' - ore ' . $dateTime->format('H:i');
};
?>
<section class="mx-auto max-w-5xl space-y-10 px-2 pb-16 pt-6 sm:px-4 lg:px-0">
    <div class="overflow-hidden rounded-3xl border border-white/10 bg-black/60 shadow-2xl">
        <div class="relative min-h-[16rem] sm:min-h-[18rem]">
            <?php if ($coverPath !== ''): ?>
                <img src="<?php echo htmlspecialchars($coverPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Cover profilo" class="absolute inset-0 h-full w-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/50 to-transparent"></div>
            <?php else: ?>
                <div class="absolute inset-0 bg-gradient-to-br from-black via-gray-900 to-gray-700"></div>
            <?php endif; ?>

            <div class="relative z-10 flex flex-col gap-6 px-5 pb-8 pt-24 sm:px-7 sm:pt-28 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-end sm:gap-5 sm:text-left">
                    <div class="sm:-mb-14">
                        <div class="flex h-28 w-28 items-center justify-center overflow-hidden rounded-full border-4 border-black/70 bg-white/10 text-3xl font-semibold text-white sm:h-32 sm:w-32">
                            <?php if ($avatarUrl !== ''): ?>
                                <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar di <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>" class="h-full w-full object-cover">
                            <?php else: ?>
                                <span><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="pb-4">
                        <div class="flex flex-wrap items-center justify-center gap-3 sm:justify-start">
                            <h1 class="text-3xl font-semibold text-white md:text-4xl"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h1>
                            <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white/80"><?php echo htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($handle !== ''): ?>
                                <span class="text-sm text-white/60">@<?php echo htmlspecialchars($handle, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-center gap-4 text-xs text-white/70 sm:justify-start">
                            <span><?php echo $number($followersCount); ?> follower</span>
                            <span>Segue <?php echo $number($followingCount); ?></span>
                            <span>Iscritto dal <?php echo htmlspecialchars($joinedLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span>Aggiornato <?php echo htmlspecialchars($lastUpdateLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-3 pb-4 sm:justify-end">
                    <?php if ($viewerCanFollow): ?>
                        <form method="post" class="inline-flex">
                            <input type="hidden" name="form_type" value="community_follow">
                            <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="user_id" value="<?php echo $profileId; ?>">
                            <input type="hidden" name="follow_action" value="<?php echo htmlspecialchars($followAction, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="<?php echo $followButtonClasses; ?>">
                                <?php echo htmlspecialchars($followLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        </form>
                    <?php elseif (!$isCurrentUser && !isUserLoggedIn()): ?>
                        <a href="?page=login" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                            Accedi per seguire
                        </a>
                    <?php elseif ($isCurrentUser): ?>
                        <a href="?page=profile" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                            Gestisci il tuo profilo
                        </a>
                    <?php endif; ?>
                    <a href="?page=community" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                        Torna al feed
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr),minmax(0,1fr)]">
        <div class="space-y-6">
            <div class="fan-card px-5 py-6">
                <h2 class="text-lg font-semibold text-white">Numeri dalla community</h2>
                <p class="mt-1 text-sm text-white/60">Una panoramica delle attività pubbliche condivise da questo tifoso.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <?php foreach ($statCards as $stat): ?>
                        <div class="rounded-2xl border border-white/10 bg-black/35 px-4 py-4">
                            <p class="text-xs uppercase tracking-wide text-white/50"><?php echo htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mt-3 text-2xl font-semibold text-white"><?php echo $number($stat['value']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="fan-card px-5 py-6">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Ultimi post condivisi</h2>
                        <p class="text-sm text-white/60">Estratti dalle ultime attività pubblicate nella community.</p>
                    </div>
                    <a href="?page=community" class="text-sm font-semibold text-juventus-yellow transition-colors hover:text-white">Apri la community</a>
                </div>
                <?php if ($recentPosts): ?>
                    <ul class="mt-5 space-y-4 text-sm text-white/80">
                        <?php foreach ($recentPosts as $post):
                            $postId = (int) ($post['id'] ?? 0);
                            $publishedLabel = getHumanTimeDiff((int) ($post['published_at'] ?? $post['created_at'] ?? time()));
                            $previewSource = $post['content'] !== '' ? $post['content'] : ($post['poll_question'] ?? '');
                            $preview = $truncate($previewSource, 160);
                        ?>
                            <li class="rounded-2xl border border-white/10 bg-black/35 px-4 py-4">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs uppercase tracking-wide text-white/50">
                                    <span>Pubblicato <?php echo htmlspecialchars($publishedLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($postId > 0): ?>
                                        <a href="?page=community#post-<?php echo $postId; ?>" class="inline-flex items-center gap-1 text-juventus-yellow transition-colors hover:text-white">
                                            Apri post
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="h-3 w-3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-3 text-sm text-white/90"><?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="mt-5 text-sm text-white/60">Non ci sono post pubblicati di recente da questo tifoso.</p>
                <?php endif; ?>
            </div>

            <div class="fan-card px-5 py-6">
                <h2 class="text-lg font-semibold text-white">Discussioni recenti</h2>
                <p class="text-sm text-white/60">Ultimi interventi nei commenti della community.</p>
                <?php if ($recentComments): ?>
                    <ul class="mt-5 space-y-4 text-sm text-white/80">
                        <?php foreach ($recentComments as $comment):
                            $commentPreview = $truncate($comment['content'] ?? '', 140);
                            $targetPreview = $truncate($comment['post_content'] ?? '', 100);
                            $timeLabel = getHumanTimeDiff((int) ($comment['created_at'] ?? time()));
                            $targetPostId = (int) ($comment['post_id'] ?? 0);
                        ?>
                            <li class="rounded-2xl border border-white/10 bg-black/35 px-4 py-4">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs uppercase tracking-wide text-white/50">
                                    <span>Commentato <?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($targetPostId > 0): ?>
                                        <a href="?page=community#post-<?php echo $targetPostId; ?>" class="inline-flex items-center gap-1 text-juventus-yellow transition-colors hover:text-white">
                                            Vai al post
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="h-3 w-3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-3 text-sm text-white/90"><?php echo htmlspecialchars($commentPreview, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if ($targetPreview !== ''): ?>
                                    <p class="mt-3 text-xs text-white/50">Sul post: <?php echo htmlspecialchars($targetPreview, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="mt-5 text-sm text-white/60">Nessun commento recente nella community.</p>
                <?php endif; ?>
            </div>

            <div class="fan-card px-5 py-6">
                <h2 class="text-lg font-semibold text-white">Commenti alle news</h2>
                <p class="text-sm text-white/60">Le ultime opinioni condivise negli articoli della redazione.</p>
                <?php if ($recentNewsComments): ?>
                    <ul class="mt-5 space-y-4 text-sm text-white/80">
                        <?php foreach ($recentNewsComments as $comment):
                            $newsTitle = trim((string) ($comment['news_title'] ?? ''));
                            $newsSlug = trim((string) ($comment['news_slug'] ?? ''));
                            $timeLabel = getHumanTimeDiff((int) ($comment['created_at'] ?? time()));
                            $commentPreview = $truncate($comment['content'] ?? '', 140);
                            $newsLink = $newsSlug !== '' ? '?page=news_article&slug=' . urlencode($newsSlug) : '#';
                        ?>
                            <li class="rounded-2xl border border-white/10 bg-black/35 px-4 py-4">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs uppercase tracking-wide text-white/50">
                                    <span>Pubblicato <?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($newsSlug !== ''): ?>
                                        <a href="<?php echo htmlspecialchars($newsLink, ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center gap-1 text-juventus-yellow transition-colors hover:text-white">
                                            Leggi la news
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="h-3 w-3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-3 text-sm text-white/90"><?php echo htmlspecialchars($commentPreview, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if ($newsTitle !== ''): ?>
                                    <p class="mt-3 text-xs text-white/50">Articolo: <?php echo htmlspecialchars($newsTitle, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="mt-5 text-sm text-white/60">Non sono presenti commenti recenti alle news.</p>
                <?php endif; ?>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="fan-card px-5 py-6">
                <h2 class="text-lg font-semibold text-white">Informazioni principali</h2>
                <div class="mt-4 space-y-4 text-sm text-white/80">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-white/50">Bio</p>
                        <p class="mt-1 leading-relaxed"><?php echo $bio !== '' ? nl2br(htmlspecialchars($bio, ENT_QUOTES, 'UTF-8')) : 'Questo tifoso non ha ancora condiviso una bio.'; ?></p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-white/50">Città</p>
                        <p class="mt-1"><?php echo $location !== '' ? htmlspecialchars($location, ENT_QUOTES, 'UTF-8') : 'Località non indicata.'; ?></p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-white/50">Sito web</p>
                        <?php if ($website !== ''): ?>
                            <a href="<?php echo htmlspecialchars($website, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1 text-juventus-yellow transition-colors hover:text-white">
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
                <h2 class="text-lg font-semibold text-white">Preferiti bianconeri</h2>
                <div class="mt-4 space-y-4 text-sm text-white/80">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-white/50">Giocatore del cuore</p>
                        <p class="mt-1"><?php echo $favoritePlayer !== '' ? htmlspecialchars($favoritePlayer, ENT_QUOTES, 'UTF-8') : 'Nessun giocatore indicato.'; ?></p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-white/50">Momento indelebile</p>
                        <p class="mt-1"><?php echo $favoriteMemory !== '' ? htmlspecialchars($favoriteMemory, ENT_QUOTES, 'UTF-8') : 'Il ricordo juventino non è stato ancora condiviso.'; ?></p>
                    </div>
                </div>
            </div>

            <div class="fan-card px-5 py-6">
                <h2 class="text-lg font-semibold text-white">Orario attività</h2>
                <div class="mt-4 space-y-3 text-sm text-white/80">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-white/50">Ingresso in curva</p>
                        <p class="mt-1"><?php echo htmlspecialchars($formatTimeline($joinTimestamp), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-white/50">Ultima attività</p>
                        <p class="mt-1"><?php echo htmlspecialchars($lastUpdateLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</section>
