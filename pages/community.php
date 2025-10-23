<?php
$posts = getCommunityPosts();
$isLoggedIn = isUserLoggedIn();
$loggedUser = getLoggedInUser();
$oldMessage = getOldInput('message');
$registeredUsers = getRegisteredUsers();
?>
<section class="space-y-6 mx-auto max-w-5xl">
    <div class="text-center space-y-2">
        <h1 class="text-2xl font-bold">Community Bianconera</h1>
        <p class="text-gray-400 text-sm">Partecipa alle discussioni con altri tifosi e condividi la tua passione.</p>
    </div>

    <div class="space-y-4">
        <?php foreach ($posts as $post): ?>
        <article class="bg-gray-900 p-5 rounded-2xl shadow-lg space-y-3 transition-all duration-300 ease-in-out hover:scale-[1.01]">
            <div class="flex items-center justify-between text-sm">
                <span class="font-semibold text-white"><?php echo htmlspecialchars($post['author'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="text-gray-500"><?php echo htmlspecialchars(getHumanTimeDiff($post['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <p class="text-sm text-gray-300 leading-relaxed"><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="flex items-center gap-3 text-xs text-gray-500 uppercase tracking-wide">
                <button class="hover:text-white transition-all" type="button">Like</button>
                <button class="hover:text-white transition-all" type="button">Commenta</button>
                <button class="hover:text-white transition-all" type="button">Condividi</button>
            </div>
        </article>
        <?php endforeach; ?>

        <?php if (empty($posts)): ?>
            <p class="text-center text-gray-500">La community è appena partita: pubblica tu il primo messaggio!</p>
        <?php endif; ?>
    </div>

    <section class="bg-gray-900 p-5 rounded-2xl shadow-lg space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Tifosi iscritti</h2>
            <span class="badge-accent"><?php echo count($registeredUsers); ?> membri</span>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <?php foreach ($registeredUsers as $user): ?>
                <div class="rounded-xl bg-black/60 border border-gray-800 px-4 py-3 text-sm">
                    <p class="font-semibold text-white"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="text-xs text-gray-500 uppercase tracking-wide"><?php echo htmlspecialchars($user['badge'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="text-xs text-gray-600 mt-2">Iscritto da <?php echo htmlspecialchars(getHumanTimeDiff($user['created_at']), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="bg-gray-900 p-5 rounded-2xl shadow-lg space-y-4">
        <h2 class="text-lg font-semibold">Scrivi un messaggio</h2>
        <?php if ($isLoggedIn && $loggedUser): ?>
            <form action="" method="post" class="space-y-4">
                <input type="hidden" name="form_type" value="community_post">
                <textarea name="message" rows="4" class="w-full rounded-xl bg-black/70 border border-gray-800 px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" placeholder="Condividi un pensiero con la community"><?php echo htmlspecialchars($oldMessage, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <button type="submit" class="w-full py-3 rounded-full bg-white text-black font-semibold transition-all duration-300 hover:bg-juventus-silver">Invia</button>
            </form>
        <?php else: ?>
            <p class="text-sm text-gray-400">Per partecipare alle discussioni effettua il <a href="?page=login" class="text-white underline">login</a> o <a href="?page=register" class="text-white underline">registrati</a>.</p>
        <?php endif; ?>
    </section>
</section>
