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
<div class="min-h-screen bg-black text-white flex flex-col">
    <header class="px-4 pt-6 pb-4">
        <div class="mx-auto max-w-5xl flex flex-col gap-6 md:gap-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <a href="?page=home" class="flex flex-col gap-1">
                    <span class="text-2xl font-bold tracking-wide">BianconeriHub ⚪⚫</span>
                    <span class="text-sm text-gray-400"><?php echo htmlspecialchars($siteTagline, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <div class="flex items-center gap-3 md:gap-4">
                    <?php if ($loggedUser): ?>
                        <div class="text-right">
                            <p class="text-sm font-semibold">Ciao, <?php echo htmlspecialchars($loggedUser['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="text-xs text-gray-500 uppercase tracking-wide"><?php echo htmlspecialchars($loggedUser['badge'] ?? 'Tifoso', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <a href="?action=logout" class="inline-flex items-center gap-2 rounded-full bg-white text-black px-4 py-2 text-sm font-semibold transition-all duration-300 hover:bg-juventus-silver">
                            Logout
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12" />
                            </svg>
                        </a>
                    <?php else: ?>
                        <a href="?page=login" class="inline-flex items-center gap-2 rounded-full bg-white text-black px-4 py-2 text-sm font-semibold transition-all duration-300 hover:bg-juventus-silver">
                            Accedi
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12" />
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <nav class="hidden md:block">
                <ul class="flex items-center gap-6 text-sm">
                    <?php foreach ($topNavItems as $pageKey => $item):
                        $isActive = isset($currentPage) && $currentPage === $pageKey;
                        $baseClasses = 'inline-flex items-center gap-2 transition-all duration-300';
                        $stateClasses = $isActive ? 'text-white font-semibold' : 'text-gray-400 hover:text-white';
                    ?>
                    <li>
                        <a href="?page=<?php echo $pageKey; ?>" class="<?php echo $baseClasses . ' ' . $stateClasses; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
                                <path d="<?php echo $item['icon']; ?>" />
                            </svg>
                            <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main id="main-content" class="flex-1 px-4 pb-24 space-y-6">
        <?php if (!empty($flashMessages)): ?>
            <div class="mx-auto max-w-5xl">
                <div class="space-y-3">
                    <?php foreach ($flashMessages as $flash):
                        $variant = $flash['variant'] ?? 'info';
                        $color = 'bg-gray-900/80 border-gray-800 text-gray-200';
                        if ($variant === 'success') {
                            $color = 'bg-emerald-500/10 border-emerald-500/40 text-emerald-200';
                        } elseif ($variant === 'error') {
                            $color = 'bg-red-500/10 border-red-500/40 text-red-200';
                        }
                    ?>
                    <div class="rounded-2xl border px-4 py-3 text-sm <?php echo $color; ?> transition-all duration-300">
                        <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
