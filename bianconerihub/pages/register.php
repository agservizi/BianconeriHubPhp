<?php
$loggedUser = getLoggedInUser();
$oldUsername = getOldInput('username');
$oldEmail = getOldInput('email');
?>
<section class="space-y-6 mx-auto max-w-5xl">
    <div class="text-center space-y-2">
        <h1 class="text-2xl font-bold">Crea il tuo account</h1>
        <p class="text-gray-400 text-sm">Unisciti alla community e sblocca badge, eventi esclusivi e molto altro.</p>
    </div>

    <?php if ($loggedUser): ?>
        <div class="bg-gray-900 p-6 rounded-2xl shadow-lg space-y-4">
            <p class="text-sm text-gray-300">Sei già registrato come <strong><?php echo htmlspecialchars($loggedUser['username'], ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
            <p class="text-sm text-gray-500">Visita la <a href="?page=community" class="text-white underline">community</a> per partecipare alle discussioni o <a href="?action=logout" class="text-white underline">esci</a> per registrare un nuovo profilo.</p>
        </div>
    <?php else: ?>
        <form action="" method="post" class="bg-gray-900 p-6 rounded-2xl shadow-lg space-y-5">
            <input type="hidden" name="form_type" value="register">
            <div class="space-y-2">
                <label for="reg-username" class="text-sm font-medium">Username</label>
                <input id="reg-username" name="username" type="text" value="<?php echo htmlspecialchars($oldUsername, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Username" class="w-full bg-black/70 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" required>
            </div>
            <div class="space-y-2">
                <label for="reg-email" class="text-sm font-medium">Email</label>
                <input id="reg-email" name="email" type="email" value="<?php echo htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Email" class="w-full bg-black/70 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" required>
            </div>
            <div class="space-y-2">
                <label for="reg-password" class="text-sm font-medium">Password</label>
                <input id="reg-password" name="password" type="password" placeholder="Password" class="w-full bg-black/70 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" required>
            </div>
            <div class="space-y-2">
                <label for="reg-confirm" class="text-sm font-medium">Conferma Password</label>
                <input id="reg-confirm" name="password_confirmation" type="password" placeholder="Conferma Password" class="w-full bg-black/70 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" required>
            </div>
            <button type="submit" class="w-full py-3 rounded-full bg-white text-black font-semibold transition-all duration-300 hover:bg-juventus-silver">Registrati</button>
            <p class="text-sm text-center text-gray-400">
                Hai già un account?
                <a href="?page=login" class="text-white underline">Accedi</a>
            </p>
        </form>
    <?php endif; ?>
</section>
