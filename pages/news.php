<?php
$newsItems = getNewsItems();
$loggedUser = getLoggedInUser();
$newsIds = array_column($newsItems, 'id');
$engagementSummary = getNewsEngagementSummary($newsIds, $loggedUser['id'] ?? null);
?>
<section class="space-y-6 mx-auto max-w-5xl">
    <div class="text-center space-y-2">
        <h1 class="text-2xl font-bold">Ultime Notizie</h1>
        <p class="text-gray-400 text-sm">Approfondimenti, interviste e aggiornamenti dal mondo Juventus.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <?php foreach ($newsItems as $item):
            $rawImage = $item['image'] ?? '';
            if ($rawImage !== '' && filter_var($rawImage, FILTER_VALIDATE_URL)) {
                $image = $rawImage;
            } else {
                $imagePath = __DIR__ . '/../' . $rawImage;
                $image = ($rawImage !== '' && file_exists($imagePath))
                    ? $rawImage
                    : 'https://via.placeholder.com/640x360/0f0f0f/ffffff?text=BianconeriHub';
            }
            $slug = $item['slug'] ?? '';
            $metrics = $engagementSummary[$item['id']] ?? ['likes' => 0, 'comments' => 0, 'liked' => false];
            $publishedLabel = '';
            if (!empty($item['published_at'])) {
                try {
                    $publishedDate = new DateTime($item['published_at']);
                    $publishedLabel = formatItalianDate($publishedDate) . ' • ' . $publishedDate->format('H:i');
                } catch (Exception $exception) {
                    $publishedLabel = '';
                }
            }
        ?>
        <article class="bg-gray-900 rounded-2xl overflow-hidden shadow-lg transition-all duration-300 ease-in-out hover:scale-105">
            <div class="relative">
                <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-44 object-cover">
                <span class="absolute top-3 right-3 bg-black/70 text-xs uppercase tracking-wide px-2 py-1 rounded-full"><?php echo htmlspecialchars($item['tag'] ?? 'Juventus', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="p-5 space-y-3">
                <h2 class="text-lg font-semibold"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <?php if ($publishedLabel !== ''): ?>
                    <p class="text-xs text-gray-500 uppercase tracking-wide"><?php echo htmlspecialchars($publishedLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <p class="text-sm text-gray-400 leading-relaxed"><?php echo htmlspecialchars($item['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="?page=news_article&amp;slug=<?php echo urlencode($slug); ?>" class="inline-flex items-center gap-2 text-sm font-medium text-white hover:text-juventus-silver transition-all" title="Leggi di più">
                        Leggi di più
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                    <?php if ($loggedUser): ?>
                        <a href="?page=community&amp;share_news=<?php echo (int) ($item['id'] ?? 0); ?>#community-composer" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white transition-all hover:bg-white hover:text-black" title="Condividi con la community">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 9a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 9a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 21a3.75 3.75 0 0 0-7.5 0v0" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21v-.75A4.5 4.5 0 0 1 6.75 15h.5" />
                            </svg>
                            Condividi con la community
                        </a>
                    <?php else: ?>
                        <a href="?page=login" class="inline-flex items-center gap-2 rounded-full border border-white/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white/70 transition-all hover:text-white" title="Accedi per condividere">
                            Accedi per condividere
                        </a>
                    <?php endif; ?>
                </div>
                <div class="flex items-center justify-between text-xs text-gray-500 uppercase tracking-wide">
                    <span class="inline-flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97-3.04 7.5-6.367 7.5-9.067 0-2.486-2.014-4.5-4.5-4.5-1.51 0-2.842.745-3.75 1.879-.908-1.134-2.24-1.879-3.75-1.879-2.486 0-4.5 2.014-4.5 4.5 0 2.7 2.53 6.027 7.5 9.067Z" />
                        </svg>
                        <?php echo (int) ($metrics['likes'] ?? 0); ?> Mi piace
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3.75h6m-9.337 6.712a4.5 4.5 0 0 1-.913-.331 2.25 2.25 0 0 1-1.025-2.861 13.07 13.07 0 0 1 1.154-2.435c.456-.792.726-1.693.776-2.611V9a6.75 6.75 0 0 1 10.92-5.19 6.75 6.75 0 0 1 2.58 5.19v.5c.05.918.32 1.819.776 2.611.52.907.914 1.874 1.154 2.435a2.25 2.25 0 0 1-1.025 2.861c-.295.156-.599.288-.913.395a11.33 11.33 0 0 1-1.101.285 11.423 11.423 0 0 1-1.674.198q-.402.015-.807.016c-1.682 0-2.77-.318-3.523-.608-.31-.12-.63-.182-.96-.182-.33 0-.65.062-.959.183-.753.29-1.84.607-3.524.607q-.404 0-.807-.015a11.44 11.44 0 0 1-1.674-.199 11.354 11.354 0 0 1-1.101-.285Z" />
                        </svg>
                        <?php echo (int) ($metrics['comments'] ?? 0); ?> Commenti
                    </span>
                </div>
                <?php if (!empty($item['source_url'])): ?>
                    <a href="<?php echo htmlspecialchars($item['source_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="text-xs text-gray-500 hover:text-white transition-all">Fonte: TuttoJuve</a>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
        <?php if (empty($newsItems)): ?>
            <p class="col-span-full rounded-2xl border border-gray-800 bg-black/40 px-4 py-6 text-center text-sm text-gray-400">Nessuna notizia disponibile al momento. Torna più tardi per nuovi aggiornamenti.</p>
        <?php endif; ?>
    </div>
</section>
