<?php
$newsItems = getNewsItems();
$latestNews = $newsItems[0] ?? null;
$matches = getUpcomingMatches();
$nextMatch = $matches[0] ?? null;
$communityPosts = array_slice(getCommunityPosts(), 0, 2);
$loggedUser = getLoggedInUser();
?>
<section class="space-y-6 mx-auto max-w-5xl">
    <h1 class="text-2xl font-bold text-center">Benvenuto su BianconeriHub ⚪⚫</h1>
    <p class="text-center text-gray-400">Tieniti aggiornato con le ultime notizie, risultati e discussioni della community juventina.</p>

    <?php if ($latestNews): ?>
    <div class="bg-gray-900 p-5 rounded-2xl shadow-lg transition-all duration-300 ease-in-out hover:scale-[1.01]">
        <h2 class="text-lg font-semibold mb-1">Ultima Notizia</h2>
        <p class="text-sm text-gray-400"><?php echo htmlspecialchars($latestNews['title'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($latestNews['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="?page=news" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-white hover:text-juventus-silver transition-all">Approfondisci
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
                    <p class="font-semibold text-white"><?php echo htmlspecialchars($post['author'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="text-gray-400 mt-1"><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="text-xs text-gray-600 mt-2 uppercase tracking-wide"><?php echo htmlspecialchars(getHumanTimeDiff($post['created_at']), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endforeach; ?>
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
            <p class="text-sm text-gray-400">Ultimi risultati, top scorer e trend della stagione sono in arrivo.</p>
            <a href="?page=partite" class="mt-4 inline-block text-sm font-medium text-white hover:text-juventus-silver transition-all">Scopri di più</a>
        </div>
        <div class="bg-gray-900 p-5 rounded-2xl shadow-lg transition-all duration-300 ease-in-out hover:scale-[1.01]">
            <h3 class="text-base font-semibold">Galleria tifosi</h3>
            <p class="text-sm text-gray-400">Carica i tuoi scatti allo stadio e vinci badge esclusivi. Funzionalità in arrivo.</p>
            <span class="mt-4 inline-block text-xs uppercase tracking-wider text-gray-500">Coming soon</span>
        </div>
    </div>

    <?php if ($loggedUser): ?>
        <div class="bg-gradient-to-r from-white/10 via-juventus-silver/10 to-white/10 rounded-2xl border border-gray-800 px-5 py-6 text-center">
            <p class="text-sm text-gray-200">Ciao <?php echo htmlspecialchars($loggedUser['username'], ENT_QUOTES, 'UTF-8'); ?>, hai pubblicato qualcosa oggi?</p>
            <a class="mt-3 inline-flex items-center gap-2 rounded-full bg-white text-black px-4 py-2 text-sm font-semibold transition-all hover:bg-juventus-silver" href="?page=community">Scrivi un post ora</a>
        </div>
    <?php endif; ?>
</section>
