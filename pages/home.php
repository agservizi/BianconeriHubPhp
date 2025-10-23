<?php
$newsItems = getNewsItems();
$latestNews = $newsItems[0] ?? null;
$matches = getUpcomingMatches();
$nextMatch = $matches[0] ?? null;
$communityPosts = array_slice(getCommunityPosts(), 0, 2);
$stats = getCommunityStats();
$fanSpotlight = getFanSpotlight();
$loggedUser = getLoggedInUser();
$latestNewsPublished = '';
if ($latestNews && !empty($latestNews['published_at'])) {
    try {
        $latestDate = new DateTime($latestNews['published_at']);
        $latestNewsPublished = formatItalianDate($latestDate) . ' • ' . $latestDate->format('H:i');
    } catch (Exception $exception) {
        $latestNewsPublished = '';
    }
}
?>
<section class="space-y-6 mx-auto max-w-5xl">
    <h1 class="text-2xl font-bold text-center">Benvenuto su BianconeriHub ⚪⚫</h1>
    <p class="text-center text-gray-400">Tieniti aggiornato con le ultime notizie, risultati e discussioni della community juventina.</p>

    <?php if ($latestNews): ?>
    <div class="bg-gray-900 p-5 rounded-2xl shadow-lg transition-all duration-300 ease-in-out hover:scale-[1.01]">
        <h2 class="text-lg font-semibold mb-1">Ultima Notizia</h2>
        <?php if ($latestNewsPublished !== ''): ?>
            <p class="text-xs uppercase tracking-wide text-gray-500"><?php echo htmlspecialchars($latestNewsPublished, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <p class="text-sm text-gray-400"><?php echo htmlspecialchars($latestNews['title'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($latestNews['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="?page=news_article&amp;slug=<?php echo urlencode($latestNews['slug']); ?>" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-white hover:text-juventus-silver transition-all">Approfondisci
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
        </a>
    </div>
    <?php endif; ?>

    <?php if ($nextMatch): ?>
    <div class="bg-gray-900 p-5 rounded-2xl shadow-lg transition-all duration-300 ease-in-out hover:scale-[1.01]">
        <h2 class="text-lg font-semibold">Prossima Partita</h2>
        <p class="text-sm text-gray-400 mt-1">Juventus vs. <?php echo htmlspecialchars($nextMatch['opponent'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="text-sm text-gray-400"><?php echo htmlspecialchars($nextMatch['venue'], ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($nextMatch['date'] . ', ore ' . $nextMatch['time'], ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="?page=partite" class="mt-4 inline-block text-sm font-medium text-white hover:text-juventus-silver transition-all">Visualizza calendario</a>
    </div>
    <?php endif; ?>

    <div class="bg-gray-900 p-5 rounded-2xl shadow-lg transition-all duration-300 ease-in-out hover:scale-[1.01]">
        <h2 class="text-lg font-semibold">Community</h2>
        <p class="text-sm text-gray-400">Unisciti ad altri tifosi, condividi opinioni e vivi la passione bianconera 24/7.</p>
        <div class="mt-4 space-y-3">
            <?php foreach ($communityPosts as $post): ?>
                <div class="rounded-xl bg-black/60 border border-gray-800 px-4 py-3 text-sm">
                    <div class="flex items-center gap-2">
                        <p class="font-semibold text-white"><?php echo htmlspecialchars($post['author'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if (!empty($post['badge'])): ?>
                            <span class="badge-accent"><?php echo htmlspecialchars($post['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-400 mt-1"><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="text-xs text-gray-600 mt-2 uppercase tracking-wide"><?php echo htmlspecialchars(getHumanTimeDiff($post['created_at']), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endforeach; ?>
            <?php if (empty($communityPosts)): ?>
                <p class="rounded-xl border border-gray-800 bg-black/40 px-4 py-3 text-sm text-gray-400 text-center">La community è pronta ad accogliere il tuo primo messaggio.</p>
            <?php endif; ?>
        </div>
        <a href="?page=community" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-white hover:text-juventus-silver transition-all">Vai alla community
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="bg-gray-900 p-5 rounded-2xl shadow-lg transition-all duration-300 ease-in-out hover:scale-[1.01]">
            <h3 class="text-base font-semibold">Statistiche a colpo d'occhio</h3>
            <ul class="mt-3 space-y-2 text-sm text-gray-300">
                <li class="flex items-center justify-between">
                    <span class="text-gray-500">Tifosi registrati</span>
                    <span class="font-semibold text-white"><?php echo number_format($stats['members'] ?? 0, 0, ',', '.'); ?></span>
                </li>
                <li class="flex items-center justify-between">
                    <span class="text-gray-500">Messaggi pubblicati</span>
                    <span class="font-semibold text-white"><?php echo number_format($stats['posts'] ?? 0, 0, ',', '.'); ?></span>
                </li>
                <li class="flex items-center justify-between">
                    <span class="text-gray-500">Partite imminenti</span>
                    <span class="font-semibold text-white"><?php echo number_format($stats['upcoming_matches'] ?? 0, 0, ',', '.'); ?></span>
                </li>
            </ul>
            <a href="?page=community" class="mt-4 inline-block text-sm font-medium text-white hover:text-juventus-silver transition-all">Partecipa anche tu</a>
        </div>
        <div class="bg-gray-900 p-5 rounded-2xl shadow-lg transition-all duration-300 ease-in-out hover:scale-[1.01]">
            <h3 class="text-base font-semibold">Galleria tifosi</h3>
            <div class="mt-3 space-y-3">
                <?php foreach ($fanSpotlight as $item): ?>
                    <article class="rounded-xl overflow-hidden border border-gray-800 bg-black/50">
                        <img src="<?php echo htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>" class="h-32 w-full object-cover">
                        <div class="px-4 py-3 space-y-1">
                            <h4 class="text-sm font-semibold text-white"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                            <p class="text-xs text-gray-400 leading-relaxed"><?php echo htmlspecialchars($item['caption'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="text-xs text-gray-500">Credits: <?php echo htmlspecialchars($item['credit'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <a href="?page=community" class="mt-4 inline-block text-xs uppercase tracking-wider font-semibold text-white hover:text-juventus-silver transition-all">Condividi la tua foto</a>
        </div>
    </div>

    <?php if ($loggedUser): ?>
        <div class="bg-gradient-to-r from-white/10 via-juventus-silver/10 to-white/10 rounded-2xl border border-gray-800 px-5 py-6 text-center">
            <p class="text-sm text-gray-200">Ciao <?php echo htmlspecialchars($loggedUser['username'], ENT_QUOTES, 'UTF-8'); ?>, hai pubblicato qualcosa oggi?</p>
            <a class="mt-3 inline-flex items-center gap-2 rounded-full bg-white text-black px-4 py-2 text-sm font-semibold transition-all hover:bg-juventus-silver" href="?page=community">Scrivi un post ora</a>
        </div>
    <?php endif; ?>
</section>
