<?php
$loggedUser = getLoggedInUser();
$oldUsername = getOldInput('username');
?>
<section class="space-y-6 mx-auto max-w-5xl">
    <div class="text-center space-y-2">
        <h1 class="text-2xl font-bold">Accedi al tuo profilo</h1>
        <p class="text-gray-400 text-sm">Gestisci la tua esperienza su BianconeriHub e partecipa alla community.</p>
    </div>

    <?php if ($loggedUser): ?>
        <div class="bg-gray-900 p-6 rounded-2xl shadow-lg space-y-4">
            <p class="text-sm text-gray-300">Sei già autenticato come <strong><?php echo htmlspecialchars($loggedUser['username'], ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
            <p class="text-sm text-gray-500">Badge attuale: <?php echo htmlspecialchars($loggedUser['badge'] ?? 'Tifoso', ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm">
                <a href="?page=community" class="inline-flex items-center justify-center gap-2 rounded-full bg-white text-black px-4 py-2 font-semibold transition-all hover:bg-juventus-silver">Vai alla community</a>
                <a href="?action=logout" class="inline-flex items-center justify-center gap-2 rounded-full border border-red-400 px-4 py-2 font-semibold text-red-200 transition-all hover:bg-red-500/10">Logout</a>
            </div>
        </div>
    <?php else: ?>
        <form action="" method="post" class="bg-gray-900 p-6 rounded-2xl shadow-lg space-y-5">
            <input type="hidden" name="form_type" value="login">
            <div class="space-y-2">
                <label for="username" class="text-sm font-medium">Username</label>
                <input id="username" name="username" type="text" value="<?php echo htmlspecialchars($oldUsername, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Username" class="w-full bg-black/70 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" required autofocus>
            </div>
            <div class="space-y-2">
                <label for="password" class="text-sm font-medium">Password</label>
                <input id="password" name="password" type="password" placeholder="Password" class="w-full bg-black/70 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" required>
            </div>
            <button type="submit" class="w-full py-3 rounded-full bg-white text-black font-semibold transition-all duration-300 hover:bg-juventus-silver">Entra</button>
            <p class="text-sm text-center text-gray-400">
                Non hai un account?
                <a href="?page=register" class="text-white underline">Registrati</a>
            </p>
        </form>
    <?php endif; ?>
</section>
