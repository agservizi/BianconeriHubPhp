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
<!-- Barra di navigazione mobile disattivata -->
