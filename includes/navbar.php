<?php
$currentPage = $currentPage ?? 'home';
$navItems = getNavigationItems();
$isAuthenticated = isUserLoggedIn();

if (isset($navItems['profile'])) {
    $navItems['profile']['label'] = $isAuthenticated ? 'Profilo' : 'Accedi';
}
?>
<!-- Barra di navigazione mobile disattivata -->
