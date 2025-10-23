<?php
$newsItems = getNewsItems();
$matches = getUpcomingMatches();
$upcomingMatches = array_slice($matches, 0, 3);
$communityStream = getCommunityPosts();
$communityPosts = array_slice($communityStream, 0, 4);
$stats = getCommunityStats();
$fanSpotlight = array_slice(getFanSpotlight(), 0, 4);
$loggedUser = getLoggedInUser();
$registeredUsers = array_slice(getRegisteredUsers(), 0, 6);

$trendingTags = [];
foreach ($newsItems as $item) {
    $tag = trim((string) ($item['tag'] ?? ''));
    if ($tag !== '' && !in_array($tag, $trendingTags, true)) {
        $trendingTags[] = $tag;
    }
    if (count($trendingTags) >= 6) {
        break;
    }
}

$newsHighlights = array_slice($newsItems, 0, 4);
$timelineItems = [];
$maxItems = max(count($newsHighlights), count($communityPosts));
for ($index = 0; $index < $maxItems; $index++) {
    if (isset($newsHighlights[$index])) {
        $timelineItems[] = ['type' => 'news', 'data' => $newsHighlights[$index]];
    }
    if (isset($communityPosts[$index])) {
        $timelineItems[] = ['type' => 'community', 'data' => $communityPosts[$index]];
    }
}
?>
<section class="mx-auto max-w-6xl px-2 sm:px-4 lg:px-0">
    <div class="grid gap-6 lg:grid-cols-[18rem,minmax(0,1fr),20rem]">
        <aside class="space-y-6">
            <div class="fan-card px-5 py-6 space-y-4">
                <?php if ($loggedUser): ?>
                    <div class="space-y-1">
                        <p class="text-sm text-gray-400">Bentornato</p>
                        <p class="text-xl font-semibold text-white"><?php echo htmlspecialchars($loggedUser['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <span class="timeline-pill"><?php echo htmlspecialchars($loggedUser['badge'] ?? 'Tifoso', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <a href="?page=community" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">
                        Crea un post
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </a>
                <?php else: ?>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-300">Entra nel fan club digitale per sbloccare badge, feed personalizzato e chat live.</p>
                        <div class="flex gap-3">
                            <a href="?page=login" class="timeline-pill">Accedi</a>
                            <a href="?page=register" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">Registrati</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($trendingTags)): ?>
            <div class="fan-card px-5 py-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Trend</h2>
                    <span class="text-xs text-gray-500">News &amp; community</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($trendingTags as $tag): ?>
                        <a class="timeline-pill" href="?page=news&amp;tag=<?php echo urlencode(strtolower($tag)); ?>">#<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($registeredUsers)): ?>
            <div class="fan-card px-5 py-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Volti noti</h2>
                    <span class="text-xs text-gray-500"><?php echo number_format($stats['members'] ?? count($registeredUsers)); ?> tifosi</span>
                </div>
                <ul class="space-y-3 text-sm text-gray-200">
                    <?php foreach ($registeredUsers as $user): ?>
                        <li class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-white"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-xs uppercase tracking-wide text-gray-500"><?php echo htmlspecialchars($user['badge'] ?? 'Tifoso', ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <span class="text-xs text-gray-500"><?php echo htmlspecialchars(getHumanTimeDiff($user['created_at'] ?? time()), ENT_QUOTES, 'UTF-8'); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </aside>

        <div class="space-y-6">
            <div class="fan-card px-5 py-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-lg font-semibold text-white">Raccolta Storie Bianconere</h1>
                    <a href="?page=community" class="text-xs uppercase tracking-wide text-gray-400 hover:text-white">Vedi tutto</a>
                </div>
                <div class="flex gap-3 overflow-x-auto scrollbar-hide pb-1">
                    <?php foreach ($fanSpotlight as $item): ?>
                        <article class="relative w-32 shrink-0 overflow-hidden rounded-2xl border border-white/10 bg-black/40">
                            <img src="<?php echo htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>" class="h-36 w-full object-cover">
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent px-3 py-2">
                                <p class="text-xs font-semibold text-white line-clamp-2"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php foreach ($upcomingMatches as $match): ?>
                        <article class="relative w-32 shrink-0 overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-white/15 to-white/0 p-4">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-gray-300">Matchday</p>
                            <p class="mt-1 text-sm font-bold text-white">Juventus vs <?php echo htmlspecialchars($match['opponent'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mt-3 text-[0.65rem] text-gray-400"><?php echo htmlspecialchars($match['date'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="text-[0.65rem] text-gray-500"><?php echo htmlspecialchars($match['time'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($loggedUser): ?>
            <div class="fan-card px-5 py-5">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/20 text-sm font-semibold text-white">
                        <?php echo strtoupper(substr($loggedUser['username'], 0, 2)); ?>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-400">Condividi un pensiero con la curva digitale</p>
                        <a href="?page=community" class="mt-3 inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">Apri composer</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php foreach ($timelineItems as $item): ?>
                <?php if ($item['type'] === 'news'):
                    $news = $item['data'];
                    $publishedLabel = '';
                    if (!empty($news['published_at'])) {
                        try {
                            $publishedDate = new DateTime($news['published_at']);
                            $publishedLabel = formatItalianDate($publishedDate) . ' • ' . $publishedDate->format('H:i');
                        } catch (Exception $exception) {
                            $publishedLabel = '';
                        }
                    }
                ?>
                    <article class="fan-card px-5 py-6 space-y-3">
                        <div class="flex items-center gap-3 text-xs uppercase tracking-wide text-gray-500">
                            <span class="timeline-pill">Newsroom</span>
                            <?php if ($publishedLabel !== ''): ?>
                                <span><?php echo htmlspecialchars($publishedLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                        <h2 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($news['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="text-sm text-gray-400 leading-relaxed"><?php echo htmlspecialchars($news['excerpt'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <a href="?page=news_article&amp;slug=<?php echo urlencode($news['slug'] ?? ''); ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-white hover:text-juventus-silver">
                            Leggi tutto
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    </article>
                <?php else:
                    $post = $item['data'];
                    $likeCount = number_format($post['likes_count'] ?? 0, 0, ',', '.');
                    $commentCount = number_format($post['comments_count'] ?? 0, 0, ',', '.');
                    $supportCount = number_format($post['supports_count'] ?? 0, 0, ',', '.');
                ?>
                    <article class="fan-card px-5 py-6 space-y-3">
                        <div class="flex items-center justify-between text-xs uppercase tracking-wide text-gray-500">
                            <div class="flex items-center gap-2">
                                <span class="timeline-pill">Community</span>
                                <?php if (!empty($post['badge'])): ?>
                                    <span class="text-gray-300"><?php echo htmlspecialchars($post['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                            <span><?php echo htmlspecialchars(getHumanTimeDiff($post['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <h3 class="text-sm font-semibold text-white"><?php echo htmlspecialchars($post['author'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="text-sm text-gray-300 leading-relaxed"><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="flex flex-wrap items-center gap-4 text-xs uppercase tracking-wide text-gray-500">
                            <a href="?page=community#post-<?php echo (int) $post['id']; ?>" class="inline-flex items-center gap-1 hover:text-white transition-all">
                                <span class="font-semibold text-white/80"><?php echo $likeCount; ?></span>
                                <span>Mi piace</span>
                            </a>
                            <a href="?page=community#post-<?php echo (int) $post['id']; ?>" class="inline-flex items-center gap-1 hover:text-white transition-all">
                                <span class="font-semibold text-white/80"><?php echo $commentCount; ?></span>
                                <span>Commenti</span>
                            </a>
                            <a href="?page=community#post-<?php echo (int) $post['id']; ?>" class="inline-flex items-center gap-1 hover:text-white transition-all">
                                <span class="font-semibold text-white/80"><?php echo $supportCount; ?></span>
                                <span>Supporta</span>
                            </a>
                        </div>
                    </article>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <aside class="space-y-6">
            <?php if (!empty($upcomingMatches)): ?>
            <div class="fan-card px-5 py-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Match center</h2>
                    <a href="?page=partite" class="text-xs uppercase tracking-wide text-gray-500 hover:text-white">Calendario</a>
                </div>
                <ul class="space-y-3 text-sm text-gray-200">
                    <?php foreach ($upcomingMatches as $match): ?>
                        <li class="rounded-2xl border border-white/10 bg-black/40 px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-gray-500"><?php echo htmlspecialchars($match['competition'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mt-1 text-sm font-semibold text-white">Juventus vs <?php echo htmlspecialchars($match['opponent'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($match['venue'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mt-2 text-xs text-gray-500"><?php echo htmlspecialchars($match['date'] . ' • ' . $match['time'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="fan-card px-5 py-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Statistiche</h2>
                    <span class="text-xs text-gray-500">Live hub</span>
                </div>
                <ul class="space-y-3 text-sm text-gray-300">
                    <li class="flex items-center justify-between">
                        <span>Tifosi registrati</span>
                        <span class="text-white font-semibold"><?php echo number_format($stats['members'] ?? 0, 0, ',', '.'); ?></span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span>Messaggi</span>
                        <span class="text-white font-semibold"><?php echo number_format($stats['posts'] ?? 0, 0, ',', '.'); ?></span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span>Partite imminenti</span>
                        <span class="text-white font-semibold"><?php echo number_format($stats['upcoming_matches'] ?? 0, 0, ',', '.'); ?></span>
                    </li>
                </ul>
            </div>

            <div class="fan-card px-5 py-6 space-y-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Sala trofei</h2>
                <p class="text-sm text-gray-300 leading-relaxed">Condividi foto, grafiche e ricordi bianconeri: la tua storia può diventare la prossima spotlight della community.</p>
                <a href="?page=community" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                    Carica contenuto
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 10.5 12 6m0 0 4.5 4.5M12 6v12" />
                    </svg>
                </a>
            </div>
        </aside>
    </div>
</section>
