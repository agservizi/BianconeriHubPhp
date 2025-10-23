<?php
$article = $activeNewsArticle ?? null;
$publishedLabel = null;
$shareUrl = null;
$loggedUser = getLoggedInUser();
$oldComment = getOldInput('news_comment');
$engagement = ['likes' => 0, 'comments' => 0, 'liked' => false];
$comments = [];

if ($article && !empty($article['published_at'])) {
    try {
        $publishedDate = new DateTime($article['published_at']);
        $publishedLabel = formatItalianDate($publishedDate) . ' • ' . $publishedDate->format('H:i');
    } catch (Exception $exception) {
        $publishedLabel = null;
    }
}

if ($article) {
    $slug = $article['slug'] ?? '';
    $relative = '?page=news_article&slug=' . urlencode($slug);

    if (!empty($baseUrl)) {
        $shareUrl = rtrim($baseUrl, '/') . '/' . ltrim($relative, '/');
    } elseif (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $shareUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($relative, '/');
    } else {
        $shareUrl = $relative;
    }

    $metrics = getNewsEngagementSummary([$article['id']], $loggedUser['id'] ?? null);
    if (isset($metrics[$article['id']])) {
        $engagement = $metrics[$article['id']];
    }

    $comments = getNewsComments($article['id']);
}
?>
<section class="space-y-6 mx-auto max-w-3xl">
    <?php if ($article): ?>
        <div class="space-y-3 text-center">
            <span class="inline-flex items-center justify-center gap-2 rounded-full bg-white text-black px-3 py-1 text-xs font-semibold uppercase tracking-wide">
                <?php echo htmlspecialchars($article['tag'] ?? 'Juventus', ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <h1 class="text-3xl font-bold leading-tight"><?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <?php if ($publishedLabel): ?>
                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($publishedLabel, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($article['image'])):
            $rawImage = $article['image'];
            if (filter_var($rawImage, FILTER_VALIDATE_URL)) {
                $imageSrc = $rawImage;
            } else {
                $imagePath = __DIR__ . '/../' . $rawImage;
                $imageSrc = file_exists($imagePath)
                    ? $rawImage
                    : 'https://via.placeholder.com/1080x540/0f0f0f/ffffff?text=BianconeriHub';
            }
        ?>
        <figure class="overflow-hidden rounded-3xl border border-gray-800">
            <img src="<?php echo htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-72 object-cover">
        </figure>
        <?php endif; ?>

        <article class="prose prose-invert prose-neutral max-w-none text-gray-100">
            <?php if (!empty($article['body'])): ?>
                <?php echo nl2br(htmlspecialchars($article['body'], ENT_QUOTES, 'UTF-8')); ?>
            <?php else: ?>
                <p>Stiamo completando questo approfondimento. Torna a trovarci tra poco per leggere il contenuto completo.</p>
            <?php endif; ?>
        </article>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-800 bg-black/60 px-4 py-4 text-sm">
            <?php if ($loggedUser): ?>
                <form action="" method="post" class="inline-flex">
                    <input type="hidden" name="form_type" value="news_like_toggle">
                    <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="news_id" value="<?php echo (int) $article['id']; ?>">
                    <input type="hidden" name="news_slug" value="<?php echo htmlspecialchars($article['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php
                    $liked = !empty($engagement['liked']);
                    $buttonClasses = $liked
                        ? 'inline-flex items-center gap-2 rounded-full bg-white text-black px-4 py-2 font-semibold transition-all duration-300 hover:bg-juventus-silver'
                        : 'inline-flex items-center gap-2 rounded-full border border-gray-700 px-4 py-2 font-semibold text-white transition-all duration-300 hover:bg-white hover:text-black';
                    $buttonLabel = $liked ? 'Hai messo Mi piace' : 'Metti Mi piace';
                    ?>
                    <button type="submit" class="<?php echo $buttonClasses; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="<?php echo $liked ? 'currentColor' : 'none'; ?>" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97-3.04 7.5-6.367 7.5-9.067 0-2.486-2.014-4.5-4.5-4.5-1.51 0-2.842.745-3.75 1.879-.908-1.134-2.24-1.879-3.75-1.879-2.486 0-4.5 2.014-4.5 4.5 0 2.7 2.53 6.027 7.5 9.067Z" />
                        </svg>
                        <?php echo htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                </form>
            <?php else: ?>
                <a href="?page=login" class="inline-flex items-center gap-2 rounded-full border border-gray-700 px-4 py-2 font-semibold text-white transition-all duration-300 hover:bg-white hover:text-black">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12" />
                    </svg>
                    Accedi per mettere Mi piace
                </a>
            <?php endif; ?>
            <div class="flex flex-wrap items-center gap-4 text-xs uppercase tracking-wide text-gray-400">
                <span><?php echo (int) ($engagement['likes'] ?? 0); ?> Mi piace</span>
                <span><?php echo (int) ($engagement['comments'] ?? 0); ?> Commenti</span>
            </div>
            <?php if (!empty($article['source_url'])): ?>
                <a href="<?php echo htmlspecialchars($article['source_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full border border-white/40 px-4 py-2 font-semibold text-white transition-all duration-300 hover:bg-white hover:text-black">
                    Leggi su TuttoJuve
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h9m0 0v9m0-9L6 18" />
                    </svg>
                </a>
            <?php endif; ?>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-800 bg-black/60 px-4 py-3 text-sm">
            <a href="?page=news" class="inline-flex items-center gap-2 font-semibold text-white hover:text-juventus-silver transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12l7.5-7.5" />
                </svg>
                Torna alle notizie
            </a>
            <a href="mailto:?subject=<?php echo rawurlencode('Consiglio lettura: ' . ($article['title'] ?? '')); ?>&body=<?php echo rawurlencode(($article['title'] ?? '') . "\n\nLeggi l'articolo su BianconeriHub: " . ($shareUrl ?? '')); ?>" class="inline-flex items-center gap-2 text-gray-400 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.5a2.25 2.25 0 0 1-2.26 0l-7.5-4.5A2.25 2.25 0 0 1 2.25 6.993V6.75" />
                </svg>
                Condividi via email
            </a>
        </div>

        <section class="space-y-4 rounded-2xl border border-gray-800 bg-black/60 px-5 py-6">
            <div class="flex flex-col gap-1">
                <h2 class="text-lg font-semibold">Commenti della community</h2>
                <p class="text-sm text-gray-400">Condividi un pensiero sulla notizia o rispondi agli altri tifosi.</p>
            </div>
            <?php if ($loggedUser): ?>
                <form action="" method="post" class="space-y-3">
                    <input type="hidden" name="form_type" value="news_comment">
                    <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="news_id" value="<?php echo (int) $article['id']; ?>">
                    <input type="hidden" name="news_slug" value="<?php echo htmlspecialchars($article['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                    <textarea name="message" rows="4" class="w-full rounded-xl bg-black/70 border border-gray-800 px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" placeholder="Scrivi un commento..."><?php echo htmlspecialchars($oldComment, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-white px-4 py-3 text-sm font-semibold text-black transition-all duration-300 hover:bg-juventus-silver">Pubblica commento</button>
                </form>
            <?php else: ?>
                <p class="text-sm text-gray-400">Per partecipare alla discussione effettua il <a href="?page=login" class="text-white underline">login</a> o <a href="?page=register" class="text-white underline">registrati</a>.</p>
            <?php endif; ?>

            <div class="space-y-4">
                <?php foreach ($comments as $comment): ?>
                    <article class="rounded-2xl border border-gray-800 bg-black/50 px-4 py-3 text-sm">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-white"><?php echo htmlspecialchars($comment['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if (!empty($comment['badge'])): ?>
                                    <span class="badge-accent"><?php echo htmlspecialchars($comment['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs text-gray-500 uppercase tracking-wide"><?php echo htmlspecialchars(getHumanTimeDiff($comment['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <p class="mt-2 text-gray-300 leading-relaxed"><?php echo nl2br(htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8')); ?></p>
                    </article>
                <?php endforeach; ?>

                <?php if (empty($comments)): ?>
                    <p class="rounded-2xl border border-dashed border-gray-700 px-4 py-5 text-center text-sm text-gray-400">Nessun commento ancora. Sii il primo a dire la tua!</p>
                <?php endif; ?>
            </div>
        </section>
    <?php else: ?>
        <div class="space-y-4 text-center">
            <h1 class="text-2xl font-bold">Notizia non trovata</h1>
            <p class="text-gray-400">L'articolo che stai cercando potrebbe essere stato rimosso o spostato.</p>
            <a href="?page=news" class="inline-flex items-center gap-2 rounded-full bg-white text-black px-4 py-2 text-sm font-semibold transition-all hover:bg-juventus-silver">
                Vai alle notizie
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </a>
        </div>
    <?php endif; ?>
</section>
