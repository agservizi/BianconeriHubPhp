<?php
if (!isUserLoggedIn()) {
    setFlash('auth', 'Effettua il login per gestire le impostazioni del profilo.', 'error');
    header('Location: ?page=login');
    exit;
}

$currentUser = getLoggedInUser();
$userId = (int) ($currentUser['id'] ?? 0);

$baseProfile = [
    'id' => $userId,
    'username' => $currentUser['username'] ?? 'Tifoso',
    'email' => $currentUser['email'] ?? '',
    'badge' => $currentUser['badge'] ?? 'Tifoso',
    'avatar_url' => null,
    'created_at' => time(),
    'updated_at' => null,
    'bio' => '',
    'location' => '',
    'website' => '',
    'favorite_player' => '',
    'favorite_memory' => '',
    'cover_path' => '',
    'followers_count' => 0,
    'following_count' => 0,
];

$profileView = [];
if (!empty($currentUser['username'])) {
    $profileView = getUserProfileView((string) $currentUser['username'], $userId) ?: [];
}

$userDetails = array_merge($baseProfile, $profileView);

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
$coverPath = trim((string) ($userDetails['cover_path'] ?? ''));
$coverUrl = $coverPath !== '' ? $coverPath : '';
$initials = strtoupper(substr($userDetails['username'] ?? 'BH', 0, 2));

$bioValue = (string) getOldInput('bio', $userDetails['bio'] ?? '');
$locationValue = (string) getOldInput('location', $userDetails['location'] ?? '');
$websiteValue = (string) getOldInput('website', $userDetails['website'] ?? '');
$favoritePlayerValue = (string) getOldInput('favorite_player', $userDetails['favorite_player'] ?? '');
$favoriteMemoryValue = (string) getOldInput('favorite_memory', $userDetails['favorite_memory'] ?? '');

$followersCount = (int) ($userDetails['followers_count'] ?? 0);
$followingCount = (int) ($userDetails['following_count'] ?? 0);
$number = static function ($value): string {
    return number_format((int) $value, 0, ',', '.');
};
?>
<section class="mx-auto max-w-6xl space-y-10 px-2 sm:px-4 lg:px-0">
    <div class="overflow-hidden rounded-3xl border border-white/10 bg-black/60 shadow-xl">
        <div class="relative min-h-[18rem] sm:min-h-[20rem]">
            <?php if ($coverUrl !== ''): ?>
                <img src="<?php echo htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Cover profilo" class="absolute inset-0 h-full w-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/45 to-black/20"></div>
            <?php else: ?>
                <div class="absolute inset-0 bg-gradient-to-br from-black via-gray-900 to-gray-700"></div>
            <?php endif; ?>

            <div class="relative z-10 flex flex-col gap-6 px-4 pb-6 pt-24 sm:px-6 sm:pt-28 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-end sm:gap-5 sm:text-left">
                    <div class="relative mb-4 sm:-mb-14 rounded-full border-4 border-black/70 bg-white/10 shadow-xl mx-auto sm:mx-0">
                        <div class="h-24 w-24 sm:h-28 sm:w-28 lg:h-28 lg:w-28 overflow-hidden rounded-full bg-white/5 text-3xl font-semibold text-white">
                            <?php if ($avatarUrl !== ''): ?>
                                <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar di <?php echo htmlspecialchars($userDetails['username'], ENT_QUOTES, 'UTF-8'); ?>" class="h-full w-full object-cover">
                            <?php else: ?>
                                <div class="flex h-full w-full items-center justify-center">
                                    <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="pb-4 text-center sm:text-left">
                        <div class="flex flex-wrap items-center justify-center gap-3 sm:justify-start">
                            <h1 class="text-3xl font-semibold text-white md:text-4xl"><?php echo htmlspecialchars($userDetails['username'], ENT_QUOTES, 'UTF-8'); ?></h1>
                            <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white/80"><?php echo htmlspecialchars($userDetails['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center justify-center gap-4 text-xs text-white/70 sm:justify-start">
                            <span><?php echo $number($followersCount); ?> follower</span>
                            <span>Segue <?php echo $number($followingCount); ?></span>
                            <span>Iscritto dal <?php echo htmlspecialchars($joinedLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!empty($userDetails['email'])): ?>
                                <span><?php echo htmlspecialchars($userDetails['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-2 pb-4 sm:justify-end">
                    <a href="?page=profile" class="inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                        Visualizza profilo
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </a>
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white/80">Ultimo aggiornamento <?php echo htmlspecialchars($lastUpdateLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </div>
    <nav class="border-t border-white/10 bg-black/70 px-4 sm:px-6">
            <ul class="flex flex-wrap gap-3 py-4 text-sm font-medium text-white/70">
                <li><a class="inline-flex items-center gap-2 rounded-full border border-transparent px-3 py-1.5 transition-all hover:border-white/40 hover:text-white" href="?page=profile">Panoramica</a></li>
                <li><a class="inline-flex items-center gap-2 rounded-full border border-transparent px-3 py-1.5 transition-all hover:border-white/40 hover:text-white" href="?page=community">Community</a></li>
                <li><a class="inline-flex items-center gap-2 rounded-full border border-transparent px-3 py-1.5 transition-all hover:border-white/40 hover:text-white" href="?page=news">News</a></li>
                <li><span class="inline-flex items-center gap-2 rounded-full border border-white/20 px-3 py-1.5 text-white">Impostazioni</span></li>
            </ul>
        </nav>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr),minmax(0,1fr)]">
        <form action="" method="post" class="fan-card px-5 py-6 space-y-6" autocomplete="off">
            <input type="hidden" name="form_type" value="profile_settings_update">
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="space-y-2">
                <h2 class="text-lg font-semibold text-white">Profilo pubblico</h2>
                <p class="text-sm text-white/60">Aggiorna bio, dettagli personali e il racconto del tuo tifo per far conoscere meglio chi sei.</p>
            </div>
            <div class="grid gap-5">
                <div class="space-y-2">
                    <label for="profile_bio" class="text-xs uppercase tracking-wide text-white/60">Bio (max 500 caratteri)</label>
                    <textarea id="profile_bio" name="bio" rows="5" maxlength="500" class="w-full rounded-2xl border border-white/15 bg-black/50 px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" placeholder="Racconta in breve il tuo amore per la Juve..."><?php echo htmlspecialchars($bioValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label for="profile_location" class="text-xs uppercase tracking-wide text-white/60">Città</label>
                        <input id="profile_location" type="text" name="location" maxlength="120" value="<?php echo htmlspecialchars($locationValue, ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-2xl border border-white/15 bg-black/50 px-4 py-2.5 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" placeholder="Torino, Italia">
                    </div>
                    <div class="space-y-2">
                        <label for="profile_website" class="text-xs uppercase tracking-wide text-white/60">Sito o profilo social</label>
                        <input id="profile_website" type="url" name="website" maxlength="255" value="<?php echo htmlspecialchars($websiteValue, ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-2xl border border-white/15 bg-black/50 px-4 py-2.5 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" placeholder="https://">
                        <p class="text-xs text-white/40">Inserisci un URL completo, incluso https://</p>
                    </div>
                </div>
                <div class="space-y-2">
                    <label for="profile_favorite_player" class="text-xs uppercase tracking-wide text-white/60">Giocatore del cuore</label>
                    <input id="profile_favorite_player" type="text" name="favorite_player" maxlength="120" value="<?php echo htmlspecialchars($favoritePlayerValue, ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-2xl border border-white/15 bg-black/50 px-4 py-2.5 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" placeholder="Del Piero, Baggio, Chiellini...">
                </div>
                <div class="space-y-2">
                    <label for="profile_favorite_memory" class="text-xs uppercase tracking-wide text-white/60">Momento bianconero indimenticabile</label>
                    <textarea id="profile_favorite_memory" name="favorite_memory" rows="3" maxlength="255" class="w-full rounded-2xl border border-white/15 bg-black/50 px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" placeholder="Descrivi un ricordo speciale dalla curva o dallo stadio."><?php echo htmlspecialchars($favoriteMemoryValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
            <div class="flex flex-wrap justify-end gap-3">
                <a href="?page=profile" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                    Annulla
                </a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">
                    Salva modifiche
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75 9 17.25 19.5 6.75" />
                    </svg>
                </button>
            </div>
        </form>

        <div class="space-y-6">
            <form action="" method="post" enctype="multipart/form-data" class="fan-card px-5 py-6 space-y-5" autocomplete="off">
                <input type="hidden" name="form_type" value="profile_avatar">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="space-y-2">
                    <h2 class="text-lg font-semibold text-white">Avatar</h2>
                    <p class="text-sm text-white/60">Carica un’immagine quadrata (max 7MB). Verrà mostrata in tutta la community come tua foto profilo.</p>
                </div>
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-center">
                    <div class="h-20 w-20 overflow-hidden rounded-full border border-white/20 bg-white/5 text-xl font-semibold text-white">
                        <?php if ($avatarUrl !== ''): ?>
                            <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Anteprima avatar" class="h-full w-full object-cover">
                        <?php else: ?>
                            <div class="flex h-full w-full items-center justify-center">
                                <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-2 text-center text-xs text-white/60 sm:text-left">
                        <p>Consigliato: 512x512px o superiore, formato JPG, PNG o WEBP.</p>
                        <p>Il ritaglio viene effettuato automaticamente in un cerchio.</p>
                    </div>
                </div>
                <div>
                    <label for="profile_avatar_file" class="text-xs uppercase tracking-wide text-white/60">Seleziona immagine</label>
                    <input id="profile_avatar_file" type="file" name="avatar" accept="image/*" class="mt-2 block w-full text-sm text-white file:mr-4 file:rounded-full file:border-0 file:bg-white file:px-4 file:py-2 file:text-sm file:font-semibold file:text-black hover:file:bg-juventus-silver">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">
                        Aggiorna avatar
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75 9 17.25 19.5 6.75" />
                        </svg>
                    </button>
                </div>
            </form>

            <form action="" method="post" enctype="multipart/form-data" class="fan-card px-5 py-6 space-y-5" autocomplete="off">
                <input type="hidden" name="form_type" value="profile_cover">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="space-y-2">
                    <h2 class="text-lg font-semibold text-white">Cover</h2>
                    <p class="text-sm text-white/60">Scegli un’immagine panoramica per il tuo profilo. Perfetta per foto allo stadio o cori da condividere.</p>
                </div>
                <div class="overflow-hidden rounded-2xl border border-white/15 bg-black/40">
                    <?php if ($coverUrl !== ''): ?>
                        <img src="<?php echo htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Anteprima cover" class="h-32 w-full object-cover">
                    <?php else: ?>
                        <div class="flex h-32 w-full items-center justify-center text-sm text-white/60">Nessuna cover impostata</div>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="profile_cover_file" class="text-xs uppercase tracking-wide text-white/60">Seleziona immagine</label>
                    <input id="profile_cover_file" type="file" name="cover" accept="image/*" class="mt-2 block w-full text-sm text-white file:mr-4 file:rounded-full file:border-0 file:bg-white file:px-4 file:py-2 file:text-sm file:font-semibold file:text-black hover:file:bg-juventus-silver">
                    <p class="mt-2 text-xs text-white/40">Formato consigliato 1440x480px o superiore. Dimensione massima 7MB.</p>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">
                        Aggiorna cover
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75 9 17.25 19.5 6.75" />
                        </svg>
                    </button>
                </div>
            </form>

            <div class="fan-card px-5 py-6 space-y-4 text-sm text-white/70">
                <h2 class="text-lg font-semibold text-white">Suggerimenti rapidi</h2>
                <ul class="space-y-2">
                    <li class="flex items-start gap-3">
                        <span class="mt-1 inline-flex h-2 w-2 rounded-full bg-white/70"></span>
                        <span>Mantieni la bio aggiornata con le ultime trasferte o iniziative a cui partecipi.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-1 inline-flex h-2 w-2 rounded-full bg-white/70"></span>
                        <span>Usa la cover per immortalare cori, striscioni o momenti epici della curva.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-1 inline-flex h-2 w-2 rounded-full bg-white/70"></span>
                        <span>Inserisci link utili (podcast, profili social, blog) per farti seguire dagli altri tifosi.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
