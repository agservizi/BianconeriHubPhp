<?php
$postId = (int) ($post['id'] ?? 0);
if ($postId <= 0) {
    return;
}

$comments = getCommunityComments($postId, 20);
$likeCount = number_format($post['likes_count'] ?? 0, 0, ',', '.');
$supportCount = number_format($post['supports_count'] ?? 0, 0, ',', '.');
$commentCount = number_format($post['comments_count'] ?? 0, 0, ',', '.');
$userHasLiked = !empty($post['has_liked']);
$userHasSupported = !empty($post['has_supported']);
$contentType = $post['content_type'] ?? 'text';
$mediaItems = is_array($post['media'] ?? null) ? $post['media'] : [];
$mediaUrl = trim((string) ($post['media_url'] ?? ''));
$pollQuestion = trim((string) ($post['poll_question'] ?? ''));
$pollOptions = is_array($post['poll_options'] ?? null) ? $post['poll_options'] : [];
$commentPrefill = ($oldCommentPostId === $postId) ? $oldCommentBody : '';
?>
<article id="post-<?php echo $postId; ?>" class="fan-card px-5 py-6 space-y-4" data-community-post>
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
                <?php elseif ($contentType === 'gallery'): ?>
                    <span class="timeline-pill mt-2 inline-flex text-[0.6rem] font-semibold">Gallery</span>
                <?php elseif ($contentType === 'poll'): ?>
                    <span class="timeline-pill mt-2 inline-flex text-[0.6rem] font-semibold">Sondaggio</span>
                <?php endif; ?>
            </div>
        </div>
        <span><?php echo htmlspecialchars(getHumanTimeDiff($post['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <?php if (!empty($mediaItems)): ?>
        <?php if (count($mediaItems) === 1):
            $singleMedia = $mediaItems[0];
            $singlePath = htmlspecialchars($singleMedia['path'] ?? $mediaUrl, ENT_QUOTES, 'UTF-8');
        ?>
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <img src="<?php echo $singlePath; ?>" alt="Contenuto condiviso da <?php echo htmlspecialchars($post['author'], ENT_QUOTES, 'UTF-8'); ?>" class="h-64 w-full object-cover">
            </div>
        <?php else: ?>
            <div class="grid gap-2 sm:grid-cols-2">
                <?php foreach ($mediaItems as $index => $media):
                    $mediaPath = htmlspecialchars($media['path'] ?? '', ENT_QUOTES, 'UTF-8');
                    if ($mediaPath === '') {
                        continue;
                    }
                ?>
                    <div class="relative overflow-hidden rounded-2xl border border-white/10">
                        <img src="<?php echo $mediaPath; ?>" alt="Immagine <?php echo $index + 1; ?> condivisa da <?php echo htmlspecialchars($post['author'], ENT_QUOTES, 'UTF-8'); ?>" class="h-48 w-full object-cover">
                        <span class="absolute right-2 top-2 rounded-full bg-black/60 px-2 py-1 text-[0.55rem] font-semibold uppercase tracking-wide text-white">Foto <?php echo $index + 1; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($contentType === 'photo' && $mediaUrl !== ''): ?>
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

    <?php if (trim((string) ($post['content'] ?? '')) !== ''): ?>
        <div class="text-sm text-gray-200 leading-relaxed"><?php echo $post['content_rendered']; ?></div>
    <?php endif; ?>
    <div class="flex flex-wrap items-center gap-3 text-xs uppercase tracking-wide text-gray-500">
        <?php if ($isLoggedIn):
            $likeButtonClasses = $userHasLiked ? 'text-white' : 'hover:text-white';
            $supportButtonClasses = $userHasSupported ? 'text-white' : 'hover:text-white';
        ?>
            <form method="post" class="inline">
                <input type="hidden" name="form_type" value="community_reaction">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
                <input type="hidden" name="reaction_type" value="like">
                <input type="hidden" name="redirect_to" value="?page=community#post-<?php echo $postId; ?>">
                <button type="submit" class="inline-flex items-center gap-1 transition-all <?php echo $likeButtonClasses; ?>">
                    <span class="font-semibold text-white/80"><?php echo $likeCount; ?></span>
                    <span>Mi piace</span>
                </button>
            </form>
            <form method="post" class="inline">
                <input type="hidden" name="form_type" value="community_reaction">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
                <input type="hidden" name="reaction_type" value="support">
                <input type="hidden" name="redirect_to" value="?page=community#post-<?php echo $postId; ?>">
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
        <a href="#post-<?php echo $postId; ?>" class="inline-flex items-center gap-1 transition-all hover:text-white">
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
            <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
            <input type="hidden" name="redirect_to" value="?page=community#post-<?php echo $postId; ?>">
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
