<?php
declare(strict_types=1);

define('BH_NEWS_SYNC_SCRIPT', true);

require_once __DIR__ . '/../config.php';

$options = getopt('', ['dry-run', 'limit:']);
$dryRun = array_key_exists('dry-run', $options);
$limit = null;
if (isset($options['limit'])) {
    $limit = (int) $options['limit'];
    if ($limit <= 0) {
        fwrite(STDERR, "[Error] --limit deve essere un intero positivo.\n");
        exit(1);
    }
}

$pdo = getDatabaseConnection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "[Error] Impossibile connettersi al database.\n");
    exit(1);
}

if (!communityPostsExtendedSchemaAvailable($pdo)) {
    fwrite(STDERR, "[Error] La tabella community_posts non supporta ancora i campi shared_news_*.\n");
    exit(1);
}

try {
    syncNewsFeed(true);
} catch (Throwable $exception) {
    fwrite(STDERR, '[Warning] Sincronizzazione feed fallita: ' . $exception->getMessage() . "\n");
}

$sql = 'SELECT id, user_id, content, content_type, media_url, shared_news_id, shared_news_title, shared_news_slug, shared_news_excerpt, shared_news_tag, shared_news_image, shared_news_source_url, shared_news_published_at FROM community_posts WHERE content_type = "news" AND (shared_news_id IS NULL OR shared_news_id = 0 OR shared_news_title IS NULL OR shared_news_title = "") ORDER BY id ASC';

if ($limit !== null) {
    $sql .= ' LIMIT :limit';
}

$statement = $pdo->prepare($sql);
if ($limit !== null) {
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
}

$statement->execute();
$posts = $statement->fetchAll();

if (empty($posts)) {
    fwrite(STDOUT, "[Info] Nessun post richiede il seeding dei campi shared_news_*.\n");
    exit(0);
}

$lengthFn = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
$substrFn = function_exists('mb_substr') ? 'mb_substr' : 'substr';

$loadBySlug = $pdo->prepare('SELECT id, title, slug, tag, excerpt, body, image_path, source_url, published_at, created_at FROM news WHERE slug = :slug LIMIT 1');
$loadBySource = $pdo->prepare('SELECT id, title, slug, tag, excerpt, body, image_path, source_url, published_at, created_at FROM news WHERE source_url = :source LIMIT 1');

$update = $pdo->prepare(
    'UPDATE community_posts SET shared_news_id = :shared_news_id, shared_news_title = :shared_news_title, shared_news_slug = :shared_news_slug, shared_news_excerpt = :shared_news_excerpt, shared_news_tag = :shared_news_tag, shared_news_image = :shared_news_image, shared_news_source_url = :shared_news_source_url, shared_news_published_at = :shared_news_published_at, updated_at = NOW() WHERE id = :id'
);

function truncateString(string $value, int $limit, callable $lengthFn, callable $substrFn): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    if ($lengthFn($trimmed) <= $limit) {
        return $trimmed;
    }

    return $substrFn($trimmed, 0, $limit);
}

function normalizeSourceCandidate(string $candidate): string
{
    $normalized = normalizeRemoteUrl($candidate);
    if ($normalized === '') {
        return '';
    }

    $host = parse_url($normalized, PHP_URL_HOST);
    if (!is_string($host)) {
        return '';
    }

    $host = strtolower($host);
    $allowedHosts = ['bianconerihub.com', 'www.bianconerihub.com', 'bianconerihub.local', 'www.tuttojuve.com'];
    if (!in_array($host, $allowedHosts, true)) {
        return '';
    }

    return $normalized;
}

function extractSlugCandidates(array $post): array
{
    $candidates = [];

    $existing = trim((string) ($post['shared_news_slug'] ?? ''));
    if ($existing !== '') {
        $candidates[$existing] = true;
    }

    $content = (string) ($post['content'] ?? '');
    if ($content !== '') {
        if (preg_match_all('/news_article(?:\.php)?\?[^\s"\']*slug=([a-z0-9\-]+)/i', $content, $matches)) {
            foreach ($matches[1] as $slug) {
                $slug = strtolower($slug);
                if ($slug !== '') {
                    $candidates[$slug] = true;
                }
            }
        }

        if (preg_match_all('/data-[a-z_-]*slug\s*=\s*["\']([^"\']+)["\']/i', $content, $attrMatches)) {
            foreach ($attrMatches[1] as $slug) {
                $slug = strtolower(trim($slug));
                if ($slug !== '') {
                    $candidates[$slug] = true;
                }
            }
        }
    }

    return array_keys($candidates);
}

function extractSourceCandidates(array $post): array
{
    $candidates = [];

    $initial = trim((string) ($post['shared_news_source_url'] ?? ''));
    if ($initial !== '') {
        $normalized = normalizeSourceCandidate($initial);
        if ($normalized !== '') {
            $candidates[$normalized] = true;
        }
    }

    $mediaUrl = trim((string) ($post['media_url'] ?? ''));
    if ($mediaUrl !== '') {
        $normalized = normalizeSourceCandidate($mediaUrl);
        if ($normalized !== '') {
            $candidates[$normalized] = true;
        }
    }

    $content = (string) ($post['content'] ?? '');
    if ($content !== '') {
        if (preg_match_all('/https?:\/\/[^\s"\']+/i', $content, $matches)) {
            foreach ($matches[0] as $rawUrl) {
                $normalized = normalizeSourceCandidate($rawUrl);
                if ($normalized !== '') {
                    $candidates[$normalized] = true;
                }
            }
        }
    }

    return array_keys($candidates);
}

$processed = 0;
$updated = 0;
$skipped = 0;

foreach ($posts as $post) {
    $postId = (int) ($post['id'] ?? 0);
    $slugMatches = extractSlugCandidates($post);
    $sourceMatches = extractSourceCandidates($post);

    $newsRow = null;
    $matchType = '';
    $matchValue = '';

    foreach ($slugMatches as $slugCandidate) {
        $loadBySlug->execute(['slug' => $slugCandidate]);
        $row = $loadBySlug->fetch();
        if ($row) {
            $newsRow = $row;
            $matchType = 'slug';
            $matchValue = $slugCandidate;
            break;
        }
    }

    if (!$newsRow) {
        foreach ($sourceMatches as $sourceCandidate) {
            $loadBySource->execute(['source' => $sourceCandidate]);
            $row = $loadBySource->fetch();
            if ($row) {
                $newsRow = $row;
                $matchType = 'source';
                $matchValue = $sourceCandidate;
                break;
            }
        }
    }

    if (!$newsRow) {
        $skipped++;
        fwrite(STDOUT, '[Skip] Post #' . $postId . " nessuna corrispondenza trovata.\n");
        continue;
    }

    $payload = [
        'shared_news_id' => (int) ($newsRow['id'] ?? 0),
        'shared_news_title' => truncateString((string) ($newsRow['title'] ?? ''), 255, $lengthFn, $substrFn),
        'shared_news_slug' => truncateString((string) ($newsRow['slug'] ?? ''), 255, $lengthFn, $substrFn),
        'shared_news_excerpt' => truncateString((string) ($newsRow['excerpt'] ?? ''), 65535, $lengthFn, $substrFn),
        'shared_news_tag' => truncateString((string) ($newsRow['tag'] ?? ''), 120, $lengthFn, $substrFn),
        'shared_news_image' => truncateString((string) ($newsRow['image_path'] ?? ''), 255, $lengthFn, $substrFn),
        'shared_news_source_url' => truncateString((string) ($newsRow['source_url'] ?? ''), 255, $lengthFn, $substrFn),
        'shared_news_published_at' => null,
    ];

    $publishedRaw = $newsRow['published_at'] ?? ($newsRow['created_at'] ?? null);
    if (is_string($publishedRaw) && $publishedRaw !== '') {
        $payload['shared_news_published_at'] = $publishedRaw;
    }

    if ($payload['shared_news_id'] <= 0 || $payload['shared_news_title'] === '') {
        $skipped++;
        fwrite(STDOUT, '[Skip] Post #' . $postId . " corrispondenza incompleta (" . $matchType . '=' . $matchValue . ").\n");
        continue;
    }

    $processed++;

    if ($dryRun) {
        fwrite(STDOUT, '[Dry-Run] Post #' . $postId . ' → News #' . $payload['shared_news_id'] . ' (' . $payload['shared_news_title'] . ') via ' . $matchType . '=' . $matchValue . "\n");
        $updated++;
        continue;
    }

    try {
        $update->execute([
            'shared_news_id' => $payload['shared_news_id'],
            'shared_news_title' => $payload['shared_news_title'] !== '' ? $payload['shared_news_title'] : null,
            'shared_news_slug' => $payload['shared_news_slug'] !== '' ? $payload['shared_news_slug'] : null,
            'shared_news_excerpt' => $payload['shared_news_excerpt'] !== '' ? $payload['shared_news_excerpt'] : null,
            'shared_news_tag' => $payload['shared_news_tag'] !== '' ? $payload['shared_news_tag'] : null,
            'shared_news_image' => $payload['shared_news_image'] !== '' ? $payload['shared_news_image'] : null,
            'shared_news_source_url' => $payload['shared_news_source_url'] !== '' ? $payload['shared_news_source_url'] : null,
            'shared_news_published_at' => $payload['shared_news_published_at'],
            'id' => $postId,
        ]);

        fwrite(STDOUT, '[OK] Post #' . $postId . ' aggiornato con News #' . $payload['shared_news_id'] . ' via ' . $matchType . '=' . $matchValue . "\n");
        $updated++;
    } catch (Throwable $exception) {
        $skipped++;
        fwrite(STDERR, '[Error] Post #' . $postId . ' non aggiornato: ' . $exception->getMessage() . "\n");
    }
}

fwrite(STDOUT, '\n[Report] Aggiornati: ' . $updated . ' | Saltati: ' . $skipped . "\n");

exit($skipped > 0 && $updated === 0 ? 2 : 0);
