<?php
$posts = getCommunityPosts();
$isLoggedIn = isUserLoggedIn();
$loggedUser = getLoggedInUser();
$oldMessage = getOldInput('message');
$registeredUsers = getRegisteredUsers();
$stats = getCommunityStats();
$newsItems = array_slice(getNewsItems(), 0, 5);
$matches = array_slice(getUpcomingMatches(), 0, 4);

$trendingTags = [];
foreach ($newsItems as $item) {
    $tag = trim((string) ($item['tag'] ?? ''));
    if ($tag !== '' && !in_array($tag, $trendingTags, true)) {
        $trendingTags[] = $tag;
    }
}
$trendingTags = array_slice($trendingTags, 0, 6);

$recentMembers = array_slice($registeredUsers, 0, 8);
$oldCommentBody = (string) getOldInput('community_comment', '');
$oldCommentPostId = (int) getOldInput('community_comment_post_id', 0);
$oldComposerMode = strtolower((string) getOldInput('composer_mode', 'text'));
if (!in_array($oldComposerMode, ['text', 'photo', 'poll'], true)) {
    $oldComposerMode = 'text';
}
$oldPollQuestion = (string) getOldInput('poll_question', '');
$oldPollOptionsInput = getOldInput('poll_options', []);
if (!is_array($oldPollOptionsInput)) {
    $oldPollOptionsInput = [];
}
$oldPollOptions = [];
foreach ($oldPollOptionsInput as $optionValue) {
    $oldPollOptions[] = (string) $optionValue;
}
$oldPollOptions = array_slice($oldPollOptions, 0, 4);
$oldPollOptions = array_pad($oldPollOptions, 4, '');
?>
<section class="mx-auto max-w-6xl px-2 sm:px-4 lg:px-0">
    <div class="grid gap-6 lg:grid-cols-[18rem,minmax(0,1fr),20rem]">
        <aside class="space-y-6">
            <div class="fan-card px-5 py-6 space-y-4">
                <div class="space-y-1">
                    <p class="text-sm font-semibold uppercase tracking-wide text-gray-400">Community hub</p>
                    <h1 class="text-xl font-bold text-white">Curva digitale Juventus</h1>
                    <p class="text-sm text-gray-400">Partecipa ai thread live, proponi sondaggi e rispondi ai cori virtuali.</p>
                </div>
                <?php if (!$isLoggedIn): ?>
                    <div class="flex gap-3">
                        <a href="?page=login" class="timeline-pill">Accedi</a>
                        <a href="?page=register" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">Registrati</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($trendingTags)): ?>
            <div class="fan-card px-5 py-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Discussioni calde</h2>
                    <span class="text-xs text-gray-500">Ultime 24h</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($trendingTags as $tag): ?>
                        <a class="timeline-pill" href="?page=news&amp;tag=<?php echo urlencode(strtolower($tag)); ?>">#<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($recentMembers)): ?>
            <div class="fan-card px-5 py-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Nuovi arrivi</h2>
                    <span class="text-xs text-gray-500"><?php echo number_format($stats['members'] ?? count($registeredUsers)); ?> totali</span>
                </div>
                <ul class="space-y-3 text-sm text-gray-200">
                    <?php foreach ($recentMembers as $user): ?>
                        <li class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-white"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-xs uppercase tracking-wide text-gray-500"><?php echo htmlspecialchars($user['badge'] ?? 'Tifoso', ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <span class="text-xs text-gray-500"><?php echo htmlspecialchars(getHumanTimeDiff($user['created_at'] ?? time()), ENT_QUOTES, 'UTF-8'); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </aside>

        <div class="space-y-6">
            <div class="fan-card px-5 py-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/20 text-sm font-semibold text-white">
                        <?php echo strtoupper(substr(($loggedUser['username'] ?? 'BH'), 0, 2)); ?>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($loggedUser['username'] ?? 'BianconeriHub', ENT_QUOTES, 'UTF-8'); ?></p>
                            <span class="text-xs uppercase tracking-wide text-gray-500"><?php echo htmlspecialchars($stats['posts'] > 0 ? 'Thread attivi ' . number_format($stats['posts'], 0, ',', '.') : 'Thread in partenza', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <?php if ($isLoggedIn && $loggedUser): ?>
                            <form action="" method="post" enctype="multipart/form-data" class="mt-3 space-y-3" data-community-composer>
                                <input type="hidden" name="form_type" value="community_post">
                                <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="composer_mode" value="<?php echo htmlspecialchars($oldComposerMode, ENT_QUOTES, 'UTF-8'); ?>" data-composer-mode-input>
                                <textarea
                                    name="message"
                                    rows="4"
                                    class="w-full rounded-2xl bg-black/60 border border-white/10 px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white"
                                    placeholder="Lancia una nuova discussione o racconta un momento da stadio..."
                                    data-composer-textarea
                                    data-placeholder-base="Lancia una nuova discussione o racconta un momento da stadio..."
                                    data-placeholder-photo="Aggiungi una descrizione alla foto bianconera che vuoi condividere..."
                                    data-placeholder-poll="Spiega il contesto del sondaggio o aggiungi un commento iniziale..."
                                ><?php echo htmlspecialchars($oldMessage, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400">
                                        <?php
                                        $composerModes = [
                                            'text' => 'Testo',
                                            'photo' => 'Foto',
                                            'poll' => 'Sondaggio',
                                        ];
                                        foreach ($composerModes as $modeKey => $modeLabel):
                                            $activeClass = $oldComposerMode === $modeKey ? ' composer-mode-active' : '';
                                        ?>
                                            <button type="button" class="timeline-pill composer-mode-button<?php echo $activeClass; ?>" data-composer-mode="<?php echo htmlspecialchars($modeKey, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo $modeLabel; ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">
                                        Pubblica
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12h12m-6-6v12" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="space-y-3 <?php echo $oldComposerMode === 'photo' ? '' : 'hidden'; ?>" data-composer-photo-section>
                                    <div class="space-y-2">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400">Carica immagine</label>
                                        <input
                                            type="file"
                                            name="media_file"
                                            accept="image/png,image/jpeg,image/webp,image/gif"
                                            class="hidden"
                                            data-composer-photo-file
                                        >
                                        <input type="hidden" name="media_clipboard" value="" data-composer-photo-clipboard>
                                        <input type="hidden" name="media_clipboard_name" value="" data-composer-photo-clipboard-name>
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <button type="button" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black" data-composer-photo-trigger>
                                                Scegli dal dispositivo
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5v-9m0 0-3 3m3-3 3 3M6 19.5h12a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 18 4.5H6A2.25 2.25 0 0 0 3.75 6.75V17.25A2.25 2.25 0 0 0 6 19.5Z" />
                                                </svg>
                                            </button>
                                            <p class="text-xs text-gray-500" data-composer-photo-hint>Puoi anche incollare un’immagine direttamente dagli appunti.</p>
                                        </div>
                                    </div>
                                    <div class="hidden rounded-2xl border border-white/10 bg-black/40 p-3" data-composer-photo-preview-wrapper>
                                        <div class="overflow-hidden rounded-xl border border-white/10">
                                            <img src="" alt="Anteprima immagine caricata" class="max-h-64 w-full object-cover" data-composer-photo-preview>
                                        </div>
                                        <div class="mt-3 flex flex-col gap-2 text-xs text-gray-400 sm:flex-row sm:items-center sm:justify-between">
                                            <span class="truncate" data-composer-photo-name></span>
                                            <button type="button" class="inline-flex items-center gap-1 rounded-full border border-white/20 px-3 py-1 font-semibold text-white transition-all hover:bg-white hover:text-black" data-composer-photo-clear>
                                                Rimuovi immagine
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 w-3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6m0 12L6 6" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-3 <?php echo $oldComposerMode === 'poll' ? '' : 'hidden'; ?>" data-composer-poll-section>
                                    <div class="space-y-2">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400" for="poll-question">Domanda del sondaggio</label>
                                        <input
                                            type="text"
                                            id="poll-question"
                                            name="poll_question"
                                            value="<?php echo htmlspecialchars($oldPollQuestion, ENT_QUOTES, 'UTF-8'); ?>"
                                            class="w-full rounded-2xl bg-black/60 border border-white/10 px-4 py-2 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white"
                                            placeholder="Qual è la tua formazione titolare?"
                                            data-composer-poll-question
                                        >
                                    </div>
                                    <div class="space-y-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Opzioni (minimo 2, massimo 4)</p>
                                        <?php foreach ($oldPollOptions as $index => $optionValue): ?>
                                            <input
                                                type="text"
                                                name="poll_options[]"
                                                value="<?php echo htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8'); ?>"
                                                class="w-full rounded-2xl bg-black/60 border border-white/10 px-4 py-2 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white"
                                                placeholder="Opzione <?php echo $index + 1; ?>"
                                                data-composer-poll-option
                                            >
                                        <?php endforeach; ?>
                                        <p class="text-xs text-gray-500">Suggerimento: mantieni le risposte concise (es. “4-3-3 offensivo”).</p>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="mt-3 space-y-2">
                                <p class="text-sm text-gray-400">Effettua il <a href="?page=login" class="text-white underline">login</a> o <a href="?page=register" class="text-white underline">registrati</a> per pubblicare nel feed.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($posts)): ?>
                <?php foreach ($posts as $post):
                    $comments = getCommunityComments($post['id'], 20);
                    $likeCount = number_format($post['likes_count'] ?? 0, 0, ',', '.');
                    $supportCount = number_format($post['supports_count'] ?? 0, 0, ',', '.');
                    $commentCount = number_format($post['comments_count'] ?? 0, 0, ',', '.');
                    $userHasLiked = !empty($post['has_liked']);
                    $userHasSupported = !empty($post['has_supported']);
                    $isActiveCommentForm = $oldCommentPostId === ($post['id'] ?? 0);
                    $commentPrefill = $isActiveCommentForm ? $oldCommentBody : '';
                    $contentType = $post['content_type'] ?? 'text';
                    $mediaUrl = trim((string) ($post['media_url'] ?? ''));
                    $pollQuestion = trim((string) ($post['poll_question'] ?? ''));
                    $pollOptions = is_array($post['poll_options'] ?? null) ? $post['poll_options'] : [];
                ?>
                    <article id="post-<?php echo (int) $post['id']; ?>" class="fan-card px-5 py-6 space-y-4">
                        <div class="flex items-center justify-between text-xs uppercase tracking-wide text-gray-500">
                            <div class="flex items-center gap-2">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-xs font-semibold text-white">
                                    <?php echo strtoupper(substr($post['author'] ?? 'BH', 0, 2)); ?>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($post['author'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php if (!empty($post['badge'])): ?>
                                        <span class="text-xs text-gray-400"><?php echo htmlspecialchars($post['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <?php if ($contentType === 'photo'): ?>
                                        <span class="timeline-pill mt-2 inline-flex text-[0.6rem] font-semibold">Foto</span>
                                    <?php elseif ($contentType === 'poll'): ?>
                                        <span class="timeline-pill mt-2 inline-flex text-[0.6rem] font-semibold">Sondaggio</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span><?php echo htmlspecialchars(getHumanTimeDiff($post['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <?php if ($contentType === 'photo' && $mediaUrl !== ''): ?>
                            <div class="overflow-hidden rounded-2xl border border-white/10">
                                <img src="<?php echo htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Contenuto condiviso da <?php echo htmlspecialchars($post['author'], ENT_QUOTES, 'UTF-8'); ?>" class="h-64 w-full object-cover">
                            </div>
                        <?php endif; ?>

                        <?php if ($contentType === 'poll' && $pollQuestion !== ''): ?>
                            <div class="space-y-3">
                                <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($pollQuestion, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if (!empty($pollOptions)): ?>
                                    <ul class="space-y-2 text-sm text-gray-300">
                                        <?php foreach ($pollOptions as $option): ?>
                                            <li class="flex items-center justify-between rounded-2xl border border-white/10 bg-black/40 px-4 py-2">
                                                <span><?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="text-xs uppercase tracking-wide text-gray-500">0 voti</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (trim((string) $post['content']) !== ''): ?>
                            <p class="text-sm text-gray-200 leading-relaxed"><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <?php endif; ?>
                        <div class="flex flex-wrap items-center gap-3 text-xs uppercase tracking-wide text-gray-500">
                            <?php if ($isLoggedIn):
                                $likeButtonClasses = $userHasLiked ? 'text-white' : 'hover:text-white';
                                $supportButtonClasses = $userHasSupported ? 'text-white' : 'hover:text-white';
                            ?>
                                <form method="post" class="inline">
                                    <input type="hidden" name="form_type" value="community_reaction">
                                    <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                    <input type="hidden" name="reaction_type" value="like">
                                    <input type="hidden" name="redirect_to" value="?page=community#post-<?php echo (int) $post['id']; ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 transition-all <?php echo $likeButtonClasses; ?>">
                                        <span class="font-semibold text-white/80"><?php echo $likeCount; ?></span>
                                        <span>Mi piace</span>
                                    </button>
                                </form>
                                <form method="post" class="inline">
                                    <input type="hidden" name="form_type" value="community_reaction">
                                    <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                    <input type="hidden" name="reaction_type" value="support">
                                    <input type="hidden" name="redirect_to" value="?page=community#post-<?php echo (int) $post['id']; ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 transition-all <?php echo $supportButtonClasses; ?>">
                                        <span class="font-semibold text-white/80"><?php echo $supportCount; ?></span>
                                        <span>Supporta</span>
                                    </button>
                                </form>
                            <?php else: ?>
                                <a href="?page=login" class="inline-flex items-center gap-1 transition-all hover:text-white">
                                    <span class="font-semibold text-white/80"><?php echo $likeCount; ?></span>
                                    <span>Mi piace</span>
                                </a>
                                <a href="?page=login" class="inline-flex items-center gap-1 transition-all hover:text-white">
                                    <span class="font-semibold text-white/80"><?php echo $supportCount; ?></span>
                                    <span>Supporta</span>
                                </a>
                            <?php endif; ?>
                            <a href="#post-<?php echo (int) $post['id']; ?>" class="inline-flex items-center gap-1 transition-all hover:text-white">
                                <span class="font-semibold text-white/80"><?php echo $commentCount; ?></span>
                                <span>Commenti</span>
                            </a>
                        </div>

                        <?php if (!empty($comments)): ?>
                            <ul class="space-y-3 text-sm text-gray-200">
                                <?php foreach ($comments as $comment): ?>
                                    <li class="rounded-2xl border border-white/10 bg-black/40 px-4 py-3">
                                        <div class="flex items-center justify-between text-[0.65rem] uppercase tracking-wide text-gray-500">
                                            <span class="font-semibold text-white"><?php echo htmlspecialchars($comment['author'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span><?php echo htmlspecialchars(getHumanTimeDiff($comment['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-200 leading-relaxed"><?php echo nl2br(htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8')); ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if ($isLoggedIn): ?>
                            <form action="" method="post" class="space-y-3">
                                <input type="hidden" name="form_type" value="community_comment">
                                <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                <input type="hidden" name="redirect_to" value="?page=community#post-<?php echo (int) $post['id']; ?>">
                                <textarea name="message" rows="3" class="w-full rounded-2xl bg-black/60 border border-white/10 px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" placeholder="Scrivi un pensiero per la curva..."><?php echo htmlspecialchars($commentPrefill, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div class="flex justify-end">
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-xs font-semibold text-black transition-all hover:bg-juventus-silver">
                                        Commenta
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3h6m-7.125 7.125L4.5 19.5V6.75A2.25 2.25 0 0 1 6.75 4.5h10.5A2.25 2.25 0 0 1 19.5 6.75v6.75a2.25 2.25 0 0 1-2.25 2.25H9.75L7.5 18.75Z" />
                                        </svg>
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <p class="text-xs text-gray-500">Effettua il <a href="?page=login" class="text-white underline">login</a> per aggiungere un commento.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="fan-card px-5 py-6">
                    <p class="text-center text-sm text-gray-400">La community è appena partita: pubblica tu il primo messaggio!</p>
                </div>
            <?php endif; ?>
        </div>

        <aside class="space-y-6">
            <?php if (!empty($matches)): ?>
            <div class="fan-card px-5 py-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Match thread</h2>
                    <a href="?page=partite" class="text-xs uppercase tracking-wide text-gray-500 hover:text-white">Calendario</a>
                </div>
                <ul class="space-y-3 text-sm text-gray-200">
                    <?php foreach ($matches as $match): ?>
                        <li class="rounded-2xl border border-white/10 bg-black/40 px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-gray-500"><?php echo htmlspecialchars($match['competition'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mt-1 text-sm font-semibold text-white">Juventus vs <?php echo htmlspecialchars($match['opponent'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($match['venue'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mt-2 text-xs text-gray-500"><?php echo htmlspecialchars($match['date'] . ' • ' . $match['time'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="fan-card px-5 py-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Metriche live</h2>
                    <span class="text-xs text-gray-500">Aggiornate ora</span>
                </div>
                <ul class="space-y-3 text-sm text-gray-300">
                    <li class="flex items-center justify-between">
                        <span>Post totali</span>
                        <span class="text-white font-semibold"><?php echo number_format($stats['posts'] ?? 0, 0, ',', '.'); ?></span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span>Tifosi registrati</span>
                        <span class="text-white font-semibold"><?php echo number_format($stats['members'] ?? 0, 0, ',', '.'); ?></span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span>Partite imminenti</span>
                        <span class="text-white font-semibold"><?php echo number_format($stats['upcoming_matches'] ?? 0, 0, ',', '.'); ?></span>
                    </li>
                </ul>
            </div>

            <div class="fan-card px-5 py-6 space-y-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Regolamento curva</h2>
                <ul class="space-y-2 text-xs text-gray-400 leading-relaxed">
                    <li>Mantieni i toni rispettosi, anche durante il derby.</li>
                    <li>Tagga le fonti quando condividi rumor o breaking news.</li>
                    <li>Segnala agli admin comportamenti contrari allo spirito bianconero.</li>
                </ul>
            </div>
        </aside>
    </div>
</section>
