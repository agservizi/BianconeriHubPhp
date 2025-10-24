<?php
if (!isset($siteName)) {
    require_once __DIR__ . '/../config.php';
}

if (!isset($pageTitle) || $pageTitle === '') {
    $pageTitle = $siteName;
}
$loggedUser = getLoggedInUser();
$flashMessages = pullFlashMessages();
$topNavItems = getNavigationItems();
$matches = getUpcomingMatches();
$nextMatch = $matches[0] ?? null;
$matchOpponent = $nextMatch['opponent'] ?? '';
$matchCompetition = $nextMatch['competition'] ?? '';
$matchVenue = $nextMatch['venue'] ?? '';
$matchDate = $nextMatch['date'] ?? '';
$matchTime = $nextMatch['time'] ?? '';
$matchCountdown = $nextMatch && isset($nextMatch['kickoff_at']) ? getTimeUntil($nextMatch['kickoff_at']) : null;
$communityStats = getCommunityStats();
$membersCount = number_format($communityStats['members'] ?? 0, 0, ',', '.');
$postsCount = number_format($communityStats['posts'] ?? 0, 0, ',', '.');
$matchesCount = number_format($communityStats['upcoming_matches'] ?? 0, 0, ',', '.');
$newMembersRaw = (int)($communityStats['new_members_week'] ?? 0);
$dailyInteractionsRaw = (int)($communityStats['daily_interactions'] ?? 0);
$eventsOrganizedRaw = (int)($communityStats['events_hosted'] ?? 0);
$newMembersDisplay = ($newMembersRaw > 0 ? '+' : '') . number_format($newMembersRaw, 0, ',', '.');
$dailyInteractionsDisplay = number_format($dailyInteractionsRaw, 0, ',', '.');
$eventsOrganizedDisplay = number_format($eventsOrganizedRaw, 0, ',', '.');
$ctaHref = $loggedUser ? '?page=community' : '?page=register';
$ctaLabel = $loggedUser ? 'Vai alla community' : 'Diventa socio';
$isHomeRoute = isset($activeRoute) ? $activeRoute === 'home' : (($currentPage ?? 'home') === 'home');
$routeKey = $activeRoute ?? ($currentPage ?? 'home');
$pageDescriptions = [
    'home' => 'Notizie ufficiali, approfondimenti esclusivi e community sempre connessa: vivi la Juventus da protagonista.',
    'news' => 'Restiamo sul pezzo: breaking news, analisi post-partita e voci di mercato curate ogni giorno.',
    'news_article' => 'Rivivi l’articolo con commenti della community e approfondimenti dedicati ai bianconeri.',
    'partite' => 'Calendario sempre aggiornato con orari, stadio, dirette TV e reminder per non perdere nemmeno un fischio.',
    'community' => 'La curva digitale dove la voce dei tifosi diventa racconto, idee e cori a sostegno della Vecchia Signora.',
    'login' => 'Accedi alla tua area riservata per gestire profilo, badge e partecipare alle discussioni in diretta.',
    'register' => 'Iscriviti per ottenere badge esclusivi, contenuti extra e vivere l’esperienza BianconeriHub completa.',
];
$introCopy = $pageDescriptions[$routeKey] ?? 'Vivi il fan club digitale dedicato alla Juventus con cronache, dibattiti e iniziative esclusive.';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="BianconeriHub - Hub per tifosi della Juventus con notizie, risultati e community." />
    <title><?php echo htmlspecialchars($pageTitle . ' | ' . $siteName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        juventus: {
                            black: '#000000',
                            white: '#ffffff',
                            silver: '#c0c0c0',
                        },
                    },
                },
            },
        };
    </script>
    <link rel="stylesheet" href="assets/css/tailwind.css">
</head>
<body class="bg-black text-white font-['Inter',sans-serif]" data-current-page="<?php echo htmlspecialchars($currentPage ?? 'home', ENT_QUOTES, 'UTF-8'); ?>">
<div class="relative flex min-h-screen flex-col">
    <div class="fixed inset-x-0 top-0 z-50 px-4 pt-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl">
            <div class="fan-card flex flex-col gap-3 px-4 py-4 sm:gap-4 sm:px-6 sm:py-5">
                <div class="flex items-center justify-between gap-3">
                    <a href="?page=home" class="flex items-center">
                        <div class="space-y-1">
                            <span class="text-lg font-semibold tracking-wide uppercase">BianconeriHub</span>
                            <span class="text-xs uppercase tracking-[0.3em] text-gray-400"><?php echo htmlspecialchars($siteTagline, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </a>
                    <div class="hidden items-center gap-3 md:flex">
                        <?php if ($loggedUser): ?>
                            <div class="text-right">
                                <p class="text-sm font-semibold">Ciao, <?php echo htmlspecialchars($loggedUser['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-xs uppercase tracking-wide text-gray-400"><?php echo htmlspecialchars($loggedUser['badge'] ?? 'Tifoso', ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <a href="?action=logout" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                                Logout
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12" />
                                </svg>
                            </a>
                        <?php else: ?>
                            <a href="?page=login" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                                Accedi
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12" />
                                </svg>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo $ctaHref; ?>" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">
                            <?php echo htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8'); ?>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                            </svg>
                        </a>
                    </div>
                    <div class="flex items-center gap-2 md:hidden">
                        <?php if ($loggedUser): ?>
                            <span class="text-xs font-semibold uppercase tracking-wide text-white/70"><?php echo htmlspecialchars($loggedUser['badge'] ?? 'Tifoso', ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/30 text-white transition-all hover:border-white/60 hover:bg-white/10" data-mobile-nav-toggle aria-expanded="false">
                            <span class="sr-only">Apri menu di navigazione</span>
                            <svg data-menu-icon="open" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <svg data-menu-icon="close" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 6l12 12M6 18 18 6" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="hidden md:block">
                    <nav class="w-full md:w-auto">
                        <ul class="flex flex-wrap items-center gap-3 text-sm font-medium">
                            <?php foreach ($topNavItems as $pageKey => $item) {
                                $isActive = isset($currentPage) && $currentPage === $pageKey;
                                $linkClasses = $isActive
                                    ? 'inline-flex items-center gap-2 rounded-full bg-white text-black px-4 py-2 shadow-lg'
                                    : 'inline-flex items-center gap-2 rounded-full border border-white/10 px-4 py-2 text-gray-300 hover:border-white/40 hover:text-white transition-all';
                            ?>
                                <li>
                                    <a href="?page=<?php echo $pageKey; ?>" class="<?php echo $linkClasses; ?>" data-nav-target="<?php echo $pageKey; ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="<?php echo $item['icon']; ?>" />
                                        </svg>
                                        <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </nav>
                </div>
                <div class="hidden flex flex-col gap-3 border-t border-white/10 pt-3 md:hidden" data-mobile-nav-panel>
                    <nav>
                        <ul class="flex flex-col gap-2 text-sm font-medium">
                            <?php foreach ($topNavItems as $pageKey => $item) {
                                $isActive = isset($currentPage) && $currentPage === $pageKey;
                                $linkClasses = $isActive
                                    ? 'flex items-center gap-3 rounded-xl bg-white/15 px-4 py-2 text-white shadow-inner'
                                    : 'flex items-center gap-3 rounded-xl px-4 py-2 text-gray-300 transition-all hover:bg-white/10 hover:text-white';
                            ?>
                                <li>
                                    <a href="?page=<?php echo $pageKey; ?>" class="<?php echo $linkClasses; ?>" data-nav-target="<?php echo $pageKey; ?>">
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/15 bg-black/40">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="<?php echo $item['icon']; ?>" />
                                            </svg>
                                        </span>
                                        <span class="font-semibold"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </nav>
                    <div class="flex flex-col gap-2">
                        <?php if ($loggedUser): ?>
                            <a href="?action=logout" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                                Logout
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12" />
                                </svg>
                            </a>
                        <?php else: ?>
                            <a href="?page=login" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">
                                Accedi
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12" />
                                </svg>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo $ctaHref; ?>" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">
                            <?php echo htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8'); ?>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12 8.25 19.5" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <header class="relative z-20 px-4 safe-top-spacing pb-12 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="fan-card fan-gradient border-white/20 px-4 py-4 sm:px-6 sm:py-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="space-y-2">
                        <span class="hero-chip">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5l8.25-6 8.25 6-8.25 6L3 10.5Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 17.25l8.25 6 8.25-6" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v7.5" />
                            </svg>
                            Matchday Center
                        </span>
                        <?php if ($nextMatch): ?>
                            <p class="text-sm font-semibold tracking-wide text-white/90">
                                Juventus vs <?php echo htmlspecialchars($matchOpponent, ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($matchCompetition, ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <p class="text-xs uppercase tracking-wide text-gray-400">
                                <?php echo htmlspecialchars($matchVenue, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($matchDate, ENT_QUOTES, 'UTF-8'); ?> · ore <?php echo htmlspecialchars($matchTime, ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($matchCountdown): ?>
                                    <span class="ml-2 rounded-full bg-white/10 px-2 py-0.5 text-[0.65rem] font-semibold text-white/80"><?php echo htmlspecialchars($matchCountdown, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <p class="text-sm font-semibold tracking-wide text-white/90">Calendario in aggiornamento.</p>
                            <p class="text-xs uppercase tracking-wide text-gray-400">Riceverai qui il prossimo appuntamento bianconero.</p>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-[0.7rem] font-semibold uppercase tracking-widest text-gray-400">
                        <span class="rounded-full bg-white/5 px-3 py-1 text-white/80">Soci <?php echo $membersCount; ?></span>
                        <span class="rounded-full bg-white/5 px-3 py-1 text-white/80">Post <?php echo $postsCount; ?></span>
                        <span class="rounded-full bg-white/5 px-3 py-1 text-white/80">Match <?php echo $matchesCount; ?></span>
                    </div>
                </div>
            </div>
            <?php if ($isHomeRoute): ?>
                <section class="grid gap-6 lg:grid-cols-5">
                    <div class="fan-card px-6 py-8 lg:col-span-3">
                        <div class="space-y-4">
                            <span class="section-eyebrow">Fan club ufficiale digitale</span>
                            <h1 class="section-title leading-tight">Il punto d’incontro per chi vive la Juventus ogni giorno.</h1>
                            <p class="text-sm text-gray-300">Notizie verificate, dibattiti live e iniziative esclusive pensate per chi canta &quot;Fino alla Fine&quot;. Entra, personalizza il tuo profilo e racconta la tua fede bianconera.</p>
                            <div class="flex flex-wrap gap-3">
                                <a href="?page=news" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">Leggi le news</a>
                                <a href="?page=community" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black">Apri la curva digitale</a>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4 lg:col-span-2">
                        <article class="fan-card px-5 py-5">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-white/70">Stato del club</h2>
                            <ul class="mt-3 space-y-2 text-sm text-gray-300">
                                <li class="flex justify-between"><span>Nuovi iscritti (7gg)</span><span class="font-semibold text-white"><?php echo $newMembersDisplay; ?></span></li>
                                <li class="flex justify-between"><span>Interazioni giornaliere</span><span class="font-semibold text-white"><?php echo $dailyInteractionsDisplay; ?></span></li>
                                <li class="flex justify-between"><span>Eventi organizzati</span><span class="font-semibold text-white"><?php echo $eventsOrganizedDisplay; ?></span></li>
                            </ul>
                        </article>
                        <article class="fan-card px-5 py-5">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-white/70">Club perks</h2>
                            <ul class="mt-3 space-y-2 text-sm text-gray-300">
                                <li class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-white/80"></span>
                                    Calendari sincronizzati, notifiche e promemoria matchday.
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-white/80"></span>
                                    Badge personalizzati e statistiche sul tuo percorso da tifoso.
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-white/80"></span>
                                    Accesso anticipato a contest, live audio e meet digitali.
                                </li>
                            </ul>
                        </article>
                    </div>
                </section>
            <?php else: ?>
                <section class="fan-card px-6 py-7">
                    <div class="space-y-3">
                        <span class="section-eyebrow">Zona tifosi</span>
                        <h1 class="text-3xl font-bold leading-tight"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p class="text-sm text-gray-300 max-w-2xl"><?php echo htmlspecialchars($introCopy, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if (!$loggedUser): ?>
                            <div class="pt-2">
                                <a href="?page=register" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">Unisciti al club</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </header>
    <main id="main-content" class="flex-1 px-4 safe-bottom-offset space-y-8 sm:px-6 lg:px-8">
        <?php if (!empty($flashMessages)): ?>
            <div class="mx-auto max-w-6xl">
                <div class="space-y-3">
                    <?php foreach ($flashMessages as $flash) {
                        $variant = $flash['variant'] ?? 'info';
                        $color = 'bg-white/5 border-white/20 text-gray-100';
                        if ($variant === 'success') {
                            $color = 'bg-emerald-500/15 border-emerald-400/40 text-emerald-100';
                        } elseif ($variant === 'error') {
                            $color = 'bg-red-500/15 border-red-400/40 text-red-100';
                        }
                    ?>
                        <div class="fan-card px-4 py-3 text-sm <?php echo $color; ?> transition-all duration-300">
                            <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php endif; ?>
