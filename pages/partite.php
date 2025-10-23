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

        <?php foreach ($matches as $match):
            $kickoff = $match['kickoff_at'] ?? null;
            $timeUntil = $kickoff instanceof DateTimeInterface ? getTimeUntil($kickoff) : null;
            $broadcastChannels = !empty($match['broadcast']) ? array_map('trim', explode(';', $match['broadcast'])) : [];
        ?>
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
                    <?php if (!empty($broadcastChannels)): ?>
                        <p><?php echo htmlspecialchars(implode(', ', $broadcastChannels), ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php else: ?>
                        <p>Da annunciare</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                <p class="text-gray-400">
                    <?php echo htmlspecialchars($timeUntil ?? 'Dettagli disponibili a breve', ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <a href="?action=download_match_ics&amp;id=<?php echo (int) $match['id']; ?>" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white text-black font-medium transition-all duration-300 hover:bg-juventus-silver">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v1.5h1.5a2.25 2.25 0 0 1 2.25 2.25v6a2.25 2.25 0 0 1-2.25 2.25h-9A2.25 2.25 0 0 1 4.5 18v-6A2.25 2.25 0 0 1 6.75 9.75h1.5V8.25Zm1.5 0V6.75A2.25 2.25 0 0 1 12.75 4.5h0a2.25 2.25 0 0 1 2.25 2.25V8.25" />
                    </svg>
                    Aggiungi al calendario
                </a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
