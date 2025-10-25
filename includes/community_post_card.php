<?php
$postId = (int) ($post['id'] ?? 0);
if ($postId <= 0) {
    return;
}

$authorId = (int) ($post['user_id'] ?? 0);
$viewerCanFollow = !empty($post['viewer_can_follow']);
$isFollowingAuthor = !empty($post['is_following_author']);
$followRedirect = '?page=community#post-' . $postId;
$followAction = $isFollowingAuthor ? 'unfollow' : 'follow';
$followLabel = $isFollowingAuthor ? 'Smetti di seguire' : 'Segui';
$followButtonClasses = $isFollowingAuthor
    ? 'inline-flex items-center gap-2 rounded-full border border-white/20 px-3 py-1.5 text-[0.65rem] font-semibold text-white transition-all hover:bg-white hover:text-black'
    : 'inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-[0.65rem] font-semibold text-black transition-all hover:bg-juventus-silver';

$currentViewerId = isset($viewerId) ? (int) $viewerId : 0;
$comments = getCommunityComments($postId, 50, $currentViewerId);
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
$pollTotalVotes = (int) ($post['poll_total_votes'] ?? 0);
$viewerPollChoice = $post['poll_viewer_choice'] ?? null;
$viewerHasVotedPoll = !empty($post['viewer_has_voted_poll']);
$storyTitle = trim((string) ($post['story_title'] ?? ''));
$storyCaption = trim((string) ($post['story_caption'] ?? ''));
$storyCredit = trim((string) ($post['story_credit'] ?? ''));
$parentPrefillId = isset($oldCommentParentId) ? (int) $oldCommentParentId : 0;
$commentPrefill = ($oldCommentPostId === $postId && $parentPrefillId === 0) ? $oldCommentBody : '';
$replyPrefillId = ($oldCommentPostId === $postId && $parentPrefillId > 0) ? $parentPrefillId : 0;
$replyPrefillBody = $replyPrefillId > 0 ? $oldCommentBody : '';
$communityEmojiOptions = getCommunityEmojiOptions();
?>
<article id="post-<?php echo $postId; ?>" class="fan-card px-5 py-6 space-y-4" data-community-post>
    <div class="flex flex-col gap-2 text-xs uppercase tracking-wide text-gray-500 sm:flex-row sm:items-center sm:justify-between">
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
                <?php elseif ($contentType === 'story'): ?>
                    <span class="timeline-pill mt-2 inline-flex text-[0.6rem] font-semibold">Storia</span>
                <?php elseif ($contentType === 'poll'): ?>
                    <span class="timeline-pill mt-2 inline-flex text-[0.6rem] font-semibold">Sondaggio</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 sm:justify-end">
            <?php if ($viewerCanFollow): ?>
                <form method="post" class="inline">
                    <input type="hidden" name="form_type" value="community_follow">
                    <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="user_id" value="<?php echo $authorId; ?>">
                    <input type="hidden" name="follow_action" value="<?php echo htmlspecialchars($followAction, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($followRedirect, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="<?php echo $followButtonClasses; ?>">
                        <?php echo htmlspecialchars($followLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                </form>
            <?php endif; ?>
            <span class="text-right"><?php echo htmlspecialchars(getHumanTimeDiff($post['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
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
    <?php elseif (in_array($contentType, ['photo', 'story'], true) && $mediaUrl !== ''): ?>
        <div class="overflow-hidden rounded-2xl border border-white/10">
            <img src="<?php echo htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Contenuto condiviso da <?php echo htmlspecialchars($post['author'], ENT_QUOTES, 'UTF-8'); ?>" class="h-64 w-full object-cover">
        </div>
    <?php endif; ?>

    <?php if ($contentType === 'story'): ?>
        <div class="space-y-2">
            <?php if ($storyTitle !== ''): ?>
                <h2 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($storyTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php endif; ?>
            <?php if ($storyCaption !== ''): ?>
                <p class="text-sm text-gray-300 leading-relaxed"><?php echo nl2br(htmlspecialchars($storyCaption, ENT_QUOTES, 'UTF-8')); ?></p>
            <?php endif; ?>
            <?php if ($storyCredit !== ''): ?>
                <p class="text-xs uppercase tracking-wide text-gray-500">Credito: <?php echo htmlspecialchars($storyCredit, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($contentType === 'poll' && $pollQuestion !== ''): ?>
        <div class="space-y-3">
            <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($pollQuestion, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if (!empty($pollOptions)): ?>
                <ul class="space-y-2 text-sm text-gray-300">
                    <?php foreach ($pollOptions as $option) {
                        $optionLabel = is_array($option) ? (string) ($option['label'] ?? '') : (string) $option;
                        $optionVotes = is_array($option) ? (int) ($option['votes'] ?? 0) : 0;
                        $optionPercentage = is_array($option) ? (int) ($option['percentage'] ?? 0) : 0;
                        $optionIndex = is_array($option) ? (int) ($option['index'] ?? -1) : -1;
                        $isSelected = is_array($option) ? !empty($option['is_selected']) : false;
                        if ($optionLabel === '') {
                            $optionLabel = $optionIndex >= 0 ? 'Opzione ' . ($optionIndex + 1) : 'Opzione';
                        }
                        if ($optionPercentage < 0) {
                            $optionPercentage = 0;
                        } elseif ($optionPercentage > 100) {
                            $optionPercentage = 100;
                        }
                        $optionClasses = 'rounded-2xl border border-white/10 bg-black/40 px-4 py-3';
                        if ($isSelected) {
                            $optionClasses .= ' border-white/60 bg-white/10 text-white';
                        }
                    ?>
                        <li class="<?php echo $optionClasses; ?>">
                            <div class="flex items-center justify-between gap-2">
                                <span><?php echo htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="text-xs uppercase tracking-wide text-gray-500">
                                    <?php echo number_format($optionVotes, 0, ',', '.'); ?> · <?php echo $optionPercentage; ?>%
                                </span>
                            </div>
                            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                                <div class="h-full rounded-full bg-white/60" style="width: <?php echo $optionPercentage; ?>%;"></div>
                            </div>
                            <?php if ($viewerHasVotedPoll && $viewerPollChoice === $optionIndex): ?>
                                <p class="mt-2 text-[0.65rem] font-semibold uppercase tracking-wide text-white/80">Il tuo voto</p>
                            <?php endif; ?>
                        </li>
                    <?php } ?>
                </ul>
                <p class="text-xs uppercase tracking-wide text-gray-500"><?php echo number_format($pollTotalVotes, 0, ',', '.'); ?> voti totali</p>
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

    <?php
    $renderCommunityComment = static function (array $comment, int $depth = 0) use (&$renderCommunityComment, $isLoggedIn, $postId, $replyPrefillId, $replyPrefillBody, $communityEmojiOptions) {
        $commentId = (int) ($comment['id'] ?? 0);
        if ($commentId <= 0) {
            return '';
        }

        $author = htmlspecialchars($comment['author'] ?? 'Tifoso', ENT_QUOTES, 'UTF-8');
        $badge = trim((string) ($comment['badge'] ?? ''));
        $badgeHtml = $badge !== ''
            ? '<span class="text-[0.6rem] uppercase tracking-wide text-gray-500">' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '</span>'
            : '';
        $createdAt = htmlspecialchars(getHumanTimeDiff($comment['created_at'] ?? time()), ENT_QUOTES, 'UTF-8');
        $contentHtml = nl2br(htmlspecialchars((string) ($comment['content'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $likesCountRaw = (int) ($comment['likes'] ?? 0);
    $likesCountFormatted = number_format($likesCountRaw, 0, ',', '.');
    $hasLiked = !empty($comment['has_liked']);
        $replies = is_array($comment['replies'] ?? null) ? $comment['replies'] : [];
        $replyOpen = $replyPrefillId === $commentId;
        $replyValue = $replyOpen ? $replyPrefillBody : '';
        $commentAnchor = '?page=community#comment-' . $commentId;
        $containerShade = $depth > 0 ? 'bg-black/30' : 'bg-black/40';

        ob_start();
        ?>
        <li id="comment-<?php echo $commentId; ?>" class="rounded-2xl border border-white/10 <?php echo $containerShade; ?> px-4 py-3 space-y-2">
            <div class="flex items-center justify-between text-[0.65rem] uppercase tracking-wide text-gray-500">
                <div class="flex flex-col">
                    <span class="font-semibold text-white"><?php echo $author; ?></span>
                    <?php if ($badgeHtml !== ''): ?>
                        <?php echo $badgeHtml; ?>
                    <?php endif; ?>
                </div>
                <span><?php echo $createdAt; ?></span>
            </div>
            <p class="text-sm text-gray-200 leading-relaxed"><?php echo $contentHtml; ?></p>
            <div class="flex flex-wrap items-center gap-3 text-xs uppercase tracking-wide text-gray-500">
                <?php if ($isLoggedIn): ?>
                    <form method="post" class="inline" data-comment-like-form data-comment-id="<?php echo $commentId; ?>" data-comment-like-endpoint="scripts/comment_like_toggle.php">
                        <input type="hidden" name="form_type" value="community_comment_reaction">
                        <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="comment_id" value="<?php echo $commentId; ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($commentAnchor, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="inline-flex items-center gap-1 transition-all hover:text-white<?php echo $hasLiked ? ' text-white' : ''; ?>" data-comment-like-button data-comment-like-state="<?php echo $hasLiked ? 'liked' : 'unliked'; ?>" aria-pressed="<?php echo $hasLiked ? 'true' : 'false'; ?>">
                            <span class="font-semibold text-white/80" data-comment-like-count data-comment-like-count-value="<?php echo $likesCountRaw; ?>"><?php echo $likesCountFormatted; ?></span>
                            <span>Mi piace</span>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="?page=login" class="inline-flex items-center gap-1 transition-all hover:text-white">
                        <span class="font-semibold text-white/80"><?php echo $likesCountFormatted; ?></span>
                        <span>Mi piace</span>
                    </a>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($commentAnchor, ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center gap-1 transition-all hover:text-white">
                    <span>Rispondi</span>
                </a>
            </div>
            <?php if ($isLoggedIn): ?>
                <details class="group mt-2 text-sm text-gray-200" <?php echo $replyOpen ? 'open' : ''; ?>>
                    <summary class="cursor-pointer text-xs uppercase tracking-wide text-gray-500 transition-all group-open:text-white">Rispondi</summary>
                    <form action="" method="post" class="mt-2 space-y-2">
                        <input type="hidden" name="form_type" value="community_comment">
                        <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
                        <input type="hidden" name="parent_comment_id" value="<?php echo $commentId; ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($commentAnchor, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="space-y-2" data-emoji-picker>
                            <textarea name="message" rows="3" class="w-full rounded-2xl bg-black/60 border border-white/10 px-4 py-2 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" placeholder="Rispondi a questo commento..." data-emoji-input><?php echo htmlspecialchars($replyValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <div class="flex items-center justify-between">
                                <div class="relative inline-block">
                                    <button type="button" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-3 py-1.5 text-[0.65rem] font-semibold uppercase tracking-wide text-white transition-all hover:bg-white hover:text-black" data-emoji-toggle aria-expanded="false" aria-haspopup="true">
                                        <span>Emoji</span>
                                        <span aria-hidden="true">😊</span>
                                    </button>
                                    <div class="absolute left-0 z-20 mt-2 hidden w-52 rounded-2xl border border-white/10 bg-black/90 p-2 shadow-xl" data-emoji-panel role="listbox">
                                        <div class="grid grid-cols-6 gap-1">
                                            <?php foreach ($communityEmojiOptions as $emoji): ?>
                                                <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full text-lg transition-colors hover:bg-white/15" data-emoji-value="<?php echo htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8'); ?>" role="option"><?php echo htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8'); ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-black transition-all hover:bg-juventus-silver">
                                Invia risposta
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3h6m-7.125 7.125L4.5 19.5V6.75A2.25 2.25 0 0 1 6.75 4.5h10.5A2.25 2.25 0 0 1 19.5 6.75v6.75a2.25 2.25 0 0 1-2.25 2.25H9.75L7.5 18.75Z" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </details>
            <?php endif; ?>
            <?php if (!empty($replies)): ?>
                <div class="mt-3 border-l border-white/10 pl-4">
                    <ul class="space-y-3">
                        <?php foreach ($replies as $child): ?>
                            <?php echo $renderCommunityComment($child, $depth + 1); ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </li>
        <?php
        return (string) ob_get_clean();
    };
    ?>
    <?php if (!empty($comments)): ?>
        <ul class="space-y-3 text-sm text-gray-200">
            <?php foreach ($comments as $comment): ?>
                <?php echo $renderCommunityComment($comment); ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($isLoggedIn): ?>
        <form action="" method="post" class="space-y-3">
            <input type="hidden" name="form_type" value="community_comment">
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
            <input type="hidden" name="parent_comment_id" value="0">
            <input type="hidden" name="redirect_to" value="?page=community#post-<?php echo $postId; ?>">
               <div class="space-y-2" data-emoji-picker>
                   <textarea name="message" rows="3" class="w-full rounded-2xl bg-black/60 border border-white/10 px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" placeholder="Scrivi un pensiero per la curva..." data-emoji-input><?php echo htmlspecialchars($commentPrefill, ENT_QUOTES, 'UTF-8'); ?></textarea>
                   <div class="flex items-center justify-between">
                       <div class="relative inline-block">
                           <button type="button" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white transition-all hover:bg-white hover:text-black" data-emoji-toggle aria-expanded="false" aria-haspopup="true">
                               <span>Emoji</span>
                               <span aria-hidden="true">😊</span>
                           </button>
                           <div class="absolute left-0 z-20 mt-2 hidden w-56 rounded-2xl border border-white/10 bg-black/90 p-2 shadow-xl" data-emoji-panel role="listbox">
                               <div class="grid grid-cols-6 gap-1">
                                   <?php foreach ($communityEmojiOptions as $emoji): ?>
                                       <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full text-lg transition-colors hover:bg-white/15" data-emoji-value="<?php echo htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8'); ?>" role="option"><?php echo htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8'); ?></button>
                                   <?php endforeach; ?>
                               </div>
                           </div>
                       </div>
                   </div>
               </div>
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
