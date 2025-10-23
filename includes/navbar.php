<?php
$currentPage = $currentPage ?? 'home';
$navItems = getNavigationItems();
$isAuthenticated = isUserLoggedIn();

if ($isAuthenticated) {
    $navItems['login']['label'] = 'Profilo';
} else {
    $navItems['login']['label'] = 'Login';
}
?>
<nav class="fixed bottom-0 left-0 w-full bg-gray-950/95 border-t border-gray-800 backdrop-blur-md md:hidden">
    <div class="mx-auto max-w-3xl px-8">
        <ul class="flex items-center justify-between py-3">
            <?php foreach ($navItems as $pageKey => $item):
                $isActive = $currentPage === $pageKey;
                $baseClasses = 'flex flex-col items-center gap-1 text-xs font-medium transition-all duration-300 ease-in-out';
                $colorClasses = $isActive ? 'text-white' : 'text-gray-400 hover:text-white';
            ?>
            <li>
                <a href="?page=<?php echo $pageKey; ?>" class="<?php echo $baseClasses . ' ' . $colorClasses; ?>" aria-label="Vai a <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>" data-nav-target="<?php echo $pageKey; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
                        <path d="<?php echo $item['icon']; ?>" />
                    </svg>
                    <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>
