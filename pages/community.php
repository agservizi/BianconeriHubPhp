<?php
$isLoggedIn = isUserLoggedIn();
$loggedUser = getLoggedInUser();
$viewerId = ($isLoggedIn && $loggedUser) ? (int) ($loggedUser['id'] ?? 0) : 0;

$feedPageSize = 8;
$feedFetchSize = $feedPageSize + 1;

$composerAvatarUrl = '';
$composerCoverPath = '';
$composerDisplayName = '';
$composerHandle = '';
$composerInitials = 'BH';
$composerMetaClass = 'text-xs uppercase tracking-wide text-gray-500';

if ($isLoggedIn && $loggedUser) {
    $composerAvatarUrl = trim((string) ($loggedUser['avatar_url'] ?? ''));
    $composerDisplayName = trim((string) ($loggedUser['display_name'] ?? buildUserDisplayName($loggedUser['first_name'] ?? null, $loggedUser['last_name'] ?? null, (string) ($loggedUser['username'] ?? 'BianconeriHub'))));
    $composerHandle = trim((string) ($loggedUser['username'] ?? ''));
    $initialsSource = $composerDisplayName !== '' ? $composerDisplayName : ($composerHandle !== '' ? $composerHandle : 'BH');
    $composerInitials = strtoupper(substr($initialsSource, 0, 2));

    if ($composerHandle !== '') {
        $composerProfileView = getUserProfileView((string) $composerHandle, $viewerId) ?: [];
        $composerCoverPath = trim((string) ($composerProfileView['cover_path'] ?? ''));
    }

    if ($composerCoverPath !== '') {
        $composerMetaClass = 'text-xs uppercase tracking-wide text-gray-200';
    }
}

if ($composerDisplayName === '') {
    $composerDisplayName = 'BianconeriHub';
}

if (!function_exists('renderCommunityPostCard')) {
    function renderCommunityPostCard(array $post, bool $isLoggedIn, int $viewerId = 0, int $oldCommentPostId = 0, string $oldCommentBody = '', int $oldCommentParentId = 0): string
    {
        $template = __DIR__ . '/../includes/community_post_card.php';
        if (!is_file($template)) {
            return '';
        }

        ob_start();
        include $template;

        return (string) ob_get_clean();
    }
}

if (isset($_GET['community_feed'])) {
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    $limit = (int) ($_GET['limit'] ?? $feedPageSize);
    if ($limit <= 0) {
        $limit = $feedPageSize;
    }
    $limit = min($limit, 20);

    $posts = getCommunityPosts($offset, $limit + 1);
    $hasMore = count($posts) > $limit;
    if ($hasMore) {
        $posts = array_slice($posts, 0, $limit);
    }

    $html = '';
    foreach ($posts as $post) {
        $html .= renderCommunityPostCard($post, $isLoggedIn, $viewerId, 0, '', 0);
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'html' => $html,
        'has_more' => $hasMore,
        'next_offset' => $offset + count($posts),
    ]);
    exit;
}

$registeredUsers = getRegisteredUsers();
$stats = getCommunityStats();
$allNewsItems = getNewsItems();
$newsItems = array_slice($allNewsItems, 0, 5);
$composerNewsOptions = array_slice($allNewsItems, 0, 10);
$matches = array_slice(getUpcomingMatches(), 0, 4);
$communityEmojiOptions = getCommunityEmojiOptions();

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
$oldCommentParentId = (int) getOldInput('community_comment_parent_id', 0);
$oldMessage = getOldInput('message');
$oldComposerMode = strtolower((string) getOldInput('composer_mode', 'text'));
if (!in_array($oldComposerMode, ['text', 'photo', 'poll', 'story', 'news'], true)) {
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
$oldStoryTitle = (string) getOldInput('story_title', '');
$oldStoryCaption = (string) getOldInput('story_caption', '');
$oldStoryCredit = (string) getOldInput('story_credit', '');
$oldSharedNewsId = (int) getOldInput('shared_news_id', 0);
$prefilledNewsItem = null;
$incomingShareNewsId = isset($_GET['share_news_id']) ? (int) $_GET['share_news_id'] : 0;
$incomingShareNewsSlug = trim((string) ($_GET['share_news_slug'] ?? ''));
$incomingShareParam = isset($_GET['share_news']) ? trim((string) $_GET['share_news']) : '';
if ($incomingShareNewsId <= 0 && $incomingShareParam !== '') {
    if (ctype_digit($incomingShareParam)) {
        $incomingShareNewsId = (int) $incomingShareParam;
    } elseif ($incomingShareNewsSlug === '') {
        $incomingShareNewsSlug = $incomingShareParam;
    }
}

if ($oldSharedNewsId <= 0) {
    $incomingSelection = null;
    if ($incomingShareNewsId > 0) {
        $incomingSelection = findNewsItemById($incomingShareNewsId);
    }
    if (!$incomingSelection && $incomingShareNewsSlug !== '') {
        $incomingSelection = findNewsItemBySlug($incomingShareNewsSlug);
    }

    if ($incomingSelection) {
        $resolvedNewsId = (int) ($incomingSelection['id'] ?? 0);
        if ($resolvedNewsId > 0) {
            $oldSharedNewsId = $resolvedNewsId;
            $oldComposerMode = 'news';
            $prefilledNewsItem = $incomingSelection;
        }
    }
}
$oldComposerAction = strtolower((string) getOldInput('composer_action', 'publish'));
if (!in_array($oldComposerAction, ['publish', 'schedule', 'draft'], true)) {
    $oldComposerAction = 'publish';
}
$oldScheduleAt = (string) getOldInput('schedule_at', '');
$oldDraftId = (int) getOldInput('draft_id', 0);
$oldExistingMedia = getOldInput('existing_media', []);
if (!is_array($oldExistingMedia)) {
    $oldExistingMedia = [];
}
$oldExistingMedia = array_values(array_unique(array_map('intval', $oldExistingMedia)));
$existingMediaCount = 0;
foreach ($oldExistingMedia as $mediaId) {
    if ($mediaId > 0) {
        $existingMediaCount++;
    }
}

if ($oldSharedNewsId > 0) {
    $hasOldNews = false;
    foreach ($composerNewsOptions as $option) {
        if ((int) ($option['id'] ?? 0) === $oldSharedNewsId) {
            $hasOldNews = true;
            if ($prefilledNewsItem === null) {
                $prefilledNewsItem = $option;
            }
            break;
        }
    }

    if (!$hasOldNews) {
        $selectedNewsOption = findNewsItemById($oldSharedNewsId);
        if ($selectedNewsOption) {
            array_unshift($composerNewsOptions, $selectedNewsOption);
            $composerNewsOptions = array_slice($composerNewsOptions, 0, 10);
            if ($prefilledNewsItem === null) {
                $prefilledNewsItem = $selectedNewsOption;
            }
        }
    }
}

$posts = getCommunityPosts(0, $feedFetchSize);
$hasMorePosts = count($posts) > $feedPageSize;
if ($hasMorePosts) {
    $posts = array_slice($posts, 0, $feedPageSize);
}
$nextFeedOffset = count($posts);
$maxComposerAttachments = communityMediaTableAvailable() ? 4 : 1;
$pushNotificationsEnabled = $isLoggedIn && isWebPushConfigured();
$pushPublicKey = $pushNotificationsEnabled ? getPushVapidPublicKey() : '';
$pushFollowingCount = 0;
if ($pushNotificationsEnabled && $loggedUser) {
    $pushFollowingCount = getCommunityFollowingCount((int) ($loggedUser['id'] ?? 0));
}
$pushHasFollowing = $pushFollowingCount > 0;
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
                    <?php foreach ($recentMembers as $user):
                        $memberDisplay = trim((string) ($user['display_name'] ?? ''));
                        $memberHandle = trim((string) ($user['username'] ?? ''));
                        $memberLabel = $memberDisplay !== '' ? $memberDisplay : ($memberHandle !== '' ? $memberHandle : 'Tifoso');
                    ?>
                        <li class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-white"><?php echo htmlspecialchars($memberLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-xs uppercase tracking-wide text-gray-500"><?php echo htmlspecialchars($user['badge'] ?? 'Tifoso', ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if ($memberHandle !== '' && strcasecmp($memberHandle, $memberLabel) !== 0): ?>
                                    <p class="text-xs text-gray-500">@<?php echo htmlspecialchars($memberHandle, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs text-gray-500"><?php echo htmlspecialchars(getHumanTimeDiff($user['created_at'] ?? time()), ENT_QUOTES, 'UTF-8'); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </aside>

        <div class="space-y-6">
            <div class="fan-card relative overflow-hidden px-5 py-6">
                <?php if ($composerCoverPath !== ''): ?>
                    <img src="<?php echo htmlspecialchars($composerCoverPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Cover profilo" class="absolute inset-0 h-full w-full object-cover opacity-40">
                    <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-black/70 to-black/90"></div>
                <?php endif; ?>
                <div class="relative flex flex-col gap-4 lg:flex-row lg:items-start">
                    <div class="flex h-10 w-10 overflow-hidden rounded-full border border-white/20 bg-white/10 text-sm font-semibold text-white">
                        <?php if ($composerAvatarUrl !== ''): ?>
                            <img src="<?php echo htmlspecialchars($composerAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar di <?php echo htmlspecialchars($composerDisplayName !== '' ? $composerDisplayName : ($composerHandle !== '' ? $composerHandle : 'Tifoso'), ENT_QUOTES, 'UTF-8'); ?>" class="h-full w-full object-cover">
                        <?php else: ?>
                            <span class="flex h-full w-full items-center justify-center"><?php echo htmlspecialchars($composerInitials, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($composerDisplayName, ENT_QUOTES, 'UTF-8'); ?><?php if ($composerHandle !== ''): ?><span class="ml-2 text-xs uppercase tracking-wide text-white/60">@<?php echo htmlspecialchars($composerHandle, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></p>
                            <span class="<?php echo $composerMetaClass; ?>"><?php echo htmlspecialchars($stats['posts'] > 0 ? 'Thread attivi ' . number_format($stats['posts'], 0, ',', '.') : 'Thread in partenza', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <?php if ($isLoggedIn && $loggedUser): ?>
                            <form action="" method="post" enctype="multipart/form-data" class="mt-3 space-y-4" id="community-composer" data-community-composer data-composer-max-attachments="<?php echo (int) $maxComposerAttachments; ?>" data-composer-selected-news="<?php echo htmlspecialchars($oldSharedNewsId > 0 ? (string) $oldSharedNewsId : '', ENT_QUOTES, 'UTF-8'); ?>" data-composer-existing-media-count="<?php echo (int) $existingMediaCount; ?>">
                                <input type="hidden" name="form_type" value="community_post">
                                <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="composer_mode" value="<?php echo htmlspecialchars($oldComposerMode, ENT_QUOTES, 'UTF-8'); ?>" data-composer-mode-input>
                                <input type="hidden" name="composer_action" value="<?php echo htmlspecialchars($oldComposerAction, ENT_QUOTES, 'UTF-8'); ?>" data-composer-action-input>
                                <input type="hidden" name="draft_id" value="<?php echo $oldDraftId; ?>" data-composer-draft-id>
                                <?php if (!empty($oldExistingMedia)): ?>
                                    <?php foreach ($oldExistingMedia as $existingMediaId): ?>
                                        <?php if ($existingMediaId > 0): ?>
                                            <input type="hidden" name="existing_media[]" value="<?php echo (int) $existingMediaId; ?>" data-composer-existing-media>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if ($prefilledNewsItem !== null): ?>
                                    <input type="hidden" name="shared_news_slug" value="<?php echo htmlspecialchars((string) ($prefilledNewsItem['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                                <?php if ($prefilledNewsItem !== null && $oldComposerMode === 'news'): ?>
                                    <?php
                                    $prefillTitle = trim((string) ($prefilledNewsItem['title'] ?? ''));
                                    $prefillTag = trim((string) ($prefilledNewsItem['tag'] ?? ''));
                                    ?>
                                    <div class="rounded-2xl border border-white/15 bg-white/5 px-4 py-3 text-xs text-gray-200" data-composer-news-prefill>
                                        <p class="text-sm font-semibold text-white">Condividi con la community: <?php echo htmlspecialchars($prefillTitle, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php if ($prefillTag !== ''): ?>
                                            <p class="mt-1 text-xs uppercase tracking-wide text-gray-300">#<?php echo htmlspecialchars($prefillTag, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                        <p class="mt-2 text-xs text-gray-300">Aggiungi un commento personale e pubblica per avviare la discussione.</p>
                                    </div>
                                <?php endif; ?>
                                <div class="space-y-2" data-emoji-picker>
                                    <textarea
                                        name="message"
                                        rows="4"
                                        class="w-full rounded-2xl bg-black/60 border border-white/10 px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white"
                                        placeholder="Lancia una nuova discussione o racconta un momento da stadio..."
                                        data-composer-textarea
                                        data-emoji-input
                                        data-placeholder-base="Lancia una nuova discussione o racconta un momento da stadio..."
                                        data-placeholder-photo="Aggiungi una descrizione alla foto bianconera che vuoi condividere..."
                                        data-placeholder-poll="Spiega il contesto del sondaggio o aggiungi un commento iniziale..."
                                        data-placeholder-story="Anticipa la tua storia bianconera con poche parole..."
                                        data-placeholder-news="Aggiungi un commento per accompagnare la news che vuoi condividere..."
                                    ><?php echo htmlspecialchars($oldMessage, ENT_QUOTES, 'UTF-8'); ?></textarea>
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
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400">
                                        <?php
                                        $composerModes = [
                                            'text' => 'Testo',
                                            'photo' => 'Foto',
                                            'story' => 'Storia',
                                            'news' => 'News',
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
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400" data-composer-action-buttons>
                                        <?php
                                        $composerActions = [
                                            'publish' => 'Pubblica subito',
                                            'schedule' => 'Programma',
                                            'draft' => 'Salva bozza',
                                        ];
                                        foreach ($composerActions as $actionKey => $actionLabel):
                                            $isActive = $oldComposerAction === $actionKey ? ' composer-action-active' : '';
                                        ?>
                                            <button type="button" class="timeline-pill composer-action-button<?php echo $isActive; ?>" data-composer-action="<?php echo htmlspecialchars($actionKey, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo $actionLabel; ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="<?php echo $oldComposerAction === 'schedule' ? '' : 'hidden'; ?> rounded-2xl border border-white/10 bg-black/40 px-4 py-3 text-xs text-gray-200" data-composer-schedule-wrapper>
                                    <label for="composer-schedule" class="font-semibold uppercase tracking-wide text-gray-400">Data e ora di pubblicazione</label>
                                    <input
                                        type="datetime-local"
                                        id="composer-schedule"
                                        name="schedule_at"
                                        value="<?php echo htmlspecialchars($oldScheduleAt, ENT_QUOTES, 'UTF-8'); ?>"
                                        class="mt-2 w-full rounded-xl border border-white/10 bg-black/60 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-white"
                                        data-composer-schedule-input
                                    >
                                    <p class="mt-2 text-[0.65rem] uppercase tracking-wide text-gray-500">Programma con almeno cinque minuti di anticipo.</p>
                                </div>
                                <div class="space-y-3 <?php echo $oldComposerMode === 'news' ? '' : 'hidden'; ?>" data-composer-news-section>
                                    <div class="space-y-2">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400">Condividi una news</label>
                                        <?php if (!empty($composerNewsOptions)): ?>
                                            <div class="space-y-2">
                                                <?php foreach ($composerNewsOptions as $index => $newsOption):
                                                    $newsId = (int) ($newsOption['id'] ?? 0);
                                                    $newsTitle = trim((string) ($newsOption['title'] ?? ''));
                                                    if ($newsId <= 0 || $newsTitle === '') {
                                                        continue;
                                                    }

                                                    $newsTag = trim((string) ($newsOption['tag'] ?? ''));
                                                    $newsImage = trim((string) ($newsOption['image'] ?? ''));
                                                    $newsExcerptFull = trim((string) ($newsOption['excerpt'] ?? ''));
                                                    $newsExcerpt = $newsExcerptFull !== '' ? mb_substr($newsExcerptFull, 0, 180) : '';
                                                    $isExcerptTruncated = $newsExcerptFull !== '' && mb_strlen($newsExcerptFull) > mb_strlen($newsExcerpt);
                                                    $isChecked = $oldSharedNewsId > 0 ? ($oldSharedNewsId === $newsId) : ($index === 0);
                                                    ?>
                                                    <label class="flex gap-3 rounded-2xl border border-white/10 bg-black/50 p-3 text-left text-sm text-gray-200 transition-shadow hover:border-white/30 hover:shadow-lg">
                                                        <input
                                                            type="radio"
                                                            name="shared_news_id"
                                                            value="<?php echo $newsId; ?>"
                                                            class="mt-1 h-4 w-4 flex-shrink-0 border-white/20 bg-black/60 text-white focus:ring-white"
                                                            data-composer-news-option
                                                            <?php echo $isChecked ? 'checked' : ''; ?>
                                                            <?php echo $oldComposerMode === 'news' ? 'required' : ''; ?>
                                                            <?php echo $oldComposerMode === 'news' ? '' : 'disabled'; ?>
                                                        >
                                                        <div class="flex flex-1 gap-3">
                                                            <?php if ($newsImage !== ''): ?>
                                                                <div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl border border-white/10">
                                                                    <img src="<?php echo htmlspecialchars($newsImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Anteprima news" class="h-full w-full object-cover">
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="flex-1 space-y-1">
                                                                <?php if ($newsTag !== ''): ?>
                                                                    <span class="inline-flex rounded-full bg-white/10 px-2 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wide text-white/80">#<?php echo htmlspecialchars($newsTag, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <?php endif; ?>
                                                                <p class="font-semibold text-white leading-snug"><?php echo htmlspecialchars($newsTitle, ENT_QUOTES, 'UTF-8'); ?></p>
                                                                <?php if ($newsExcerpt !== ''): ?>
                                                                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($newsExcerpt, ENT_QUOTES, 'UTF-8'); ?><?php echo $isExcerptTruncated ? '…' : ''; ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <p class="text-xs text-gray-500">La news selezionata verrà mostrata con anteprima nel tuo post.</p>
                                        <?php else: ?>
                                            <p class="text-xs text-gray-400" data-composer-news-empty>Nessuna news disponibile da condividere in questo momento.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="space-y-3 <?php echo in_array($oldComposerMode, ['photo', 'story'], true) ? '' : 'hidden'; ?>" data-composer-photo-section>
                                    <div class="space-y-2">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400">Carica immagini</label>
                                        <input
                                            type="file"
                                            name="media_files[]"
                                            accept="image/png,image/jpeg,image/webp,image/gif"
                                            class="hidden"
                                            data-composer-photo-file
                                            <?php echo $maxComposerAttachments > 1 ? 'multiple' : ''; ?>
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
                                            <p class="text-xs text-gray-500" data-composer-photo-hint>
                                                <?php if ($maxComposerAttachments > 1): ?>
                                                    Puoi allegare fino a <?php echo (int) $maxComposerAttachments; ?> immagini e incollare direttamente dagli appunti.
                                                <?php else: ?>
                                                    Al momento puoi allegare una sola immagine oppure incollarne una dagli appunti.
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <p class="hidden text-xs text-yellow-400" data-composer-photo-error></p>
                                    <div class="hidden rounded-2xl border border-white/10 bg-black/40 p-3" data-composer-photo-preview-wrapper>
                                        <div class="grid gap-3 sm:grid-cols-2" data-composer-photo-previews></div>
                                    </div>
                                    <template data-composer-photo-template>
                                        <div class="relative overflow-hidden rounded-xl border border-white/10 bg-black/60">
                                            <img src="" alt="Anteprima immagine" class="h-40 w-full object-cover" data-composer-photo-preview>
                                            <div class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-black/70 px-3 py-2 text-xs">
                                                <div class="flex flex-col">
                                                    <span class="font-semibold text-white" data-composer-photo-name></span>
                                                    <span class="text-[0.65rem] uppercase tracking-wide text-gray-400" data-composer-photo-origin></span>
                                                </div>
                                                <button type="button" class="inline-flex items-center gap-1 rounded-full border border-white/30 px-3 py-1 text-[0.65rem] font-semibold text-white transition-all hover:bg-white hover:text-black" data-composer-photo-remove>
                                                    Rimuovi
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <div class="space-y-3 <?php echo $oldComposerMode === 'story' ? '' : 'hidden'; ?>" data-composer-story-section>
                                    <div class="space-y-2">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400" for="story-title">Titolo della storia</label>
                                        <input
                                            type="text"
                                            id="story-title"
                                            name="story_title"
                                            value="<?php echo htmlspecialchars($oldStoryTitle, ENT_QUOTES, 'UTF-8'); ?>"
                                            maxlength="80"
                                            class="w-full rounded-2xl bg-black/60 border border-white/10 px-4 py-2 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white"
                                            placeholder="Es. Trasferta a Wembley"
                                            data-composer-story-title
                                        >
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400" for="story-caption">Racconto breve</label>
                                        <textarea
                                            id="story-caption"
                                            name="story_caption"
                                            rows="4"
                                            maxlength="600"
                                            class="w-full rounded-2xl bg-black/60 border border-white/10 px-4 py-2 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white"
                                            placeholder="Descrivi emozioni, contesto e cosa rende speciale questo momento..."
                                            data-composer-story-caption
                                        ><?php echo htmlspecialchars($oldStoryCaption, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400" for="story-credit">Crediti (opzionale)</label>
                                        <input
                                            type="text"
                                            id="story-credit"
                                            name="story_credit"
                                            value="<?php echo htmlspecialchars($oldStoryCredit, ENT_QUOTES, 'UTF-8'); ?>"
                                            maxlength="80"
                                            class="w-full rounded-2xl bg-black/60 border border-white/10 px-4 py-2 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white"
                                            placeholder="@autore oppure fonte dell’immagine"
                                            data-composer-story-credit
                                        >
                                    </div>
                                    <p class="text-xs text-gray-500">Suggerimento: scegli un titolo incisivo (max 80 caratteri) e cita chi ha scattato la foto se necessario.</p>
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
                                <div class="flex justify-end">
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver" data-composer-submit>
                                        <span data-composer-submit-label>
                                            <?php
                                            echo $oldComposerAction === 'draft' ? 'Salva bozza' : ($oldComposerAction === 'schedule' ? 'Programma' : 'Pubblica');
                                            ?>
                                        </span>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12h12m-6-6v12" />
                                        </svg>
                                    </button>
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

            <div
                class="space-y-6"
                data-community-feed
                data-feed-endpoint="index.php?page=community&amp;community_feed=1"
                data-feed-page-size="<?php echo (int) $feedPageSize; ?>"
                data-feed-offset="<?php echo (int) $nextFeedOffset; ?>"
                data-feed-has-more="<?php echo $hasMorePosts ? '1' : '0'; ?>"
            >
                <div class="space-y-6" data-community-feed-list>
                    <?php if (!empty($posts)): ?>
                        <?php foreach ($posts as $post): ?>
                            <?php echo renderCommunityPostCard($post, $isLoggedIn, $viewerId, $oldCommentPostId, $oldCommentBody, $oldCommentParentId); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="fan-card px-5 py-6">
                            <p class="text-center text-sm text-gray-400">La community è appena partita: pubblica tu il primo messaggio!</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-4 flex justify-center" data-community-feed-controls>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black<?php echo $hasMorePosts ? '' : ' hidden'; ?>"
                        data-community-load-more
                    >
                        Carica altri
                    </button>
                </div>
                <p class="mt-2 hidden text-center text-sm text-gray-400" data-community-feed-status>Caricamento in corso…</p>
                <div class="h-2" data-community-feed-sentinel></div>
            </div>
        </div>

        <aside class="space-y-6">
            <?php if ($pushNotificationsEnabled): ?>
            <div
                class="fan-card px-5 py-6 space-y-4"
                data-push-setup
                data-push-endpoint="scripts/push_subscriptions.php"
                data-push-public-key="<?php echo htmlspecialchars($pushPublicKey, ENT_QUOTES, 'UTF-8'); ?>"
                data-push-token="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>"
                data-push-following-available="<?php echo $pushHasFollowing ? '1' : '0'; ?>"
            >
                <div class="space-y-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Notifiche push</h2>
                    <p class="text-sm text-gray-400">Ricevi un avviso quando la curva pubblica nuovi contenuti o quando scrivono i tifosi che segui.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver" data-push-enable>
                        Attiva notifiche
                    </button>
                    <button type="button" class="hidden inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-white hover:text-black" data-push-disable>
                        Disattiva
                    </button>
                </div>
                <div class="space-y-2 text-xs text-gray-400">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="push-scope" value="global" class="h-4 w-4 rounded-full border-white/20 bg-black/50" checked>
                        <span>Tutta la community</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="push-scope" value="following" class="h-4 w-4 rounded-full border-white/20 bg-black/50"<?php echo $pushHasFollowing ? '' : ' disabled'; ?>>
                        <span>Solo gli utenti che seguo</span>
                    </label>
                    <?php if (!$pushHasFollowing): ?>
                        <p class="text-[0.65rem] text-gray-500">Segui almeno un tifoso per abilitare questa opzione.</p>
                    <?php endif; ?>
                </div>
                <p class="hidden text-xs text-yellow-400" data-push-unsupported>Il tuo browser non supporta le notifiche push.</p>
                <p class="hidden text-xs text-gray-400" data-push-status></p>
            </div>
            <?php endif; ?>

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
