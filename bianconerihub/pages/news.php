<?php $newsItems = getNewsItems(); ?>
<section class="space-y-6 mx-auto max-w-5xl">
    <div class="text-center space-y-2">
        <h1 class="text-2xl font-bold">Ultime Notizie</h1>
        <p class="text-gray-400 text-sm">Approfondimenti, interviste e aggiornamenti dal mondo Juventus.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <?php foreach ($newsItems as $item):
            $imagePath = __DIR__ . '/../' . ($item['image'] ?? '');
            $image = (!empty($item['image']) && file_exists($imagePath))
                ? $item['image']
                : 'https://via.placeholder.com/640x360/0f0f0f/ffffff?text=BianconeriHub';
        ?>
        <article class="bg-gray-900 rounded-2xl overflow-hidden shadow-lg transition-all duration-300 ease-in-out hover:scale-105">
            <div class="relative">
                <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-44 object-cover">
                <span class="absolute top-3 right-3 bg-black/70 text-xs uppercase tracking-wide px-2 py-1 rounded-full"><?php echo htmlspecialchars($item['tag'] ?? 'Juventus', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="p-5 space-y-3">
                <h2 class="text-lg font-semibold"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="text-sm text-gray-400 leading-relaxed"><?php echo htmlspecialchars($item['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                <a href="#" class="inline-flex items-center gap-2 text-sm font-medium text-white hover:text-juventus-silver transition-all" title="Leggi di più">
                    Leggi di più
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
