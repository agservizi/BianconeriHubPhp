<?php $matches = getUpcomingMatches(); ?>
<section class="space-y-6 mx-auto max-w-5xl">
    <div class="text-center space-y-2">
        <h1 class="text-2xl font-bold">Calendario Partite</h1>
        <p class="text-gray-400 text-sm">Tutte le prossime sfide della Vecchia Signora, sempre aggiornate.</p>
    </div>

    <div class="space-y-4">
        <?php if (empty($matches)): ?>
            <p class="text-center text-gray-500">Il calendario verrà aggiornato a breve. Resta connesso!</p>
        <?php endif; ?>

        <?php foreach ($matches as $match): ?>
        <article class="bg-gray-900 p-5 rounded-2xl shadow-lg transition-all duration-300 ease-in-out hover:scale-[1.01]">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold"><?php echo htmlspecialchars($match['opponent'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="text-xs uppercase tracking-wide text-gray-500"><?php echo htmlspecialchars($match['competition'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <span class="text-xs px-3 py-1 rounded-full bg-gray-800 text-gray-300"><?php echo htmlspecialchars($match['status'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-3 text-sm text-gray-400">
                <div>
                    <p class="text-gray-500">Data</p>
                    <p><?php echo htmlspecialchars($match['date'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div>
                    <p class="text-gray-500">Calcio d’inizio</p>
                    <p><?php echo htmlspecialchars($match['time'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div>
                    <p class="text-gray-500">Stadio</p>
                    <p><?php echo htmlspecialchars($match['venue'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div>
                    <p class="text-gray-500">Canali</p>
                    <p>DAZN, Sky Sport</p>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                <a href="#" class="text-white hover:text-juventus-silver transition-all">Statistiche pre-match</a>
                <button class="px-3 py-1.5 rounded-full bg-white text-black font-medium transition-all duration-300 hover:bg-juventus-silver hover:text-black">Aggiungi al calendario</button>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
