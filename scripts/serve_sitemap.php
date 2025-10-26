<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=900');

$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$cacheFile = __DIR__ . '/../storage/cache/sitemap.xml';
$cacheTtl = 900;

if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile) < $cacheTtl)) {
    $cachedContent = file_get_contents($cacheFile);
    if ($cachedContent !== false) {
        if ($requestMethod === 'HEAD') {
            header('Content-Length: ' . strlen($cachedContent));
            exit;
        }
        echo $cachedContent;
        exit;
    }
}

$baseUrl = rtrim(getApplicationBaseUrl(), '/');
$nowIso = gmdate('c');
$urls = [];
$seen = [];

$appendUrl = static function (array $entry) use (&$urls, &$seen) {
    $loc = $entry['loc'] ?? '';
    if ($loc === '' || isset($seen[$loc])) {
        return;
    }
    $seen[$loc] = true;
    $urls[] = $entry;
};

$appendUrl([
    'loc' => $baseUrl . '/',
    'lastmod' => $nowIso,
    'changefreq' => 'hourly',
    'priority' => '1.0',
]);

$appendUrl([
    'loc' => appUrl('?page=news'),
    'lastmod' => $nowIso,
    'changefreq' => 'hourly',
    'priority' => '0.9',
]);

$appendUrl([
    'loc' => appUrl('?page=partite'),
    'lastmod' => $nowIso,
    'changefreq' => 'daily',
    'priority' => '0.8',
]);

$appendUrl([
    'loc' => appUrl('?page=community'),
    'lastmod' => $nowIso,
    'changefreq' => 'hourly',
    'priority' => '0.8',
]);

$appendUrl([
    'loc' => appUrl('?page=profile_search'),
    'lastmod' => $nowIso,
    'changefreq' => 'weekly',
    'priority' => '0.4',
]);

$pdo = getDatabaseConnection();
if ($pdo) {
    try {
        $statement = $pdo->query('SELECT slug, published_at, created_at FROM news WHERE slug IS NOT NULL AND slug <> "" ORDER BY COALESCE(published_at, created_at) DESC LIMIT 500');
        foreach ($statement as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $lastmod = formatToIso8601($row['published_at'] ?? $row['created_at'] ?? null) ?? $nowIso;
            $appendUrl([
                'loc' => appUrl('?page=news_article&slug=' . rawurlencode($slug)),
                'lastmod' => $lastmod,
                'changefreq' => 'daily',
                'priority' => '0.7',
            ]);
        }
    } catch (Throwable $exception) {
        error_log('[BianconeriHub] Impossibile generare la sitemap dalle news: ' . $exception->getMessage());
    }
}

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;

$urlset = $dom->createElement('urlset');
$urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
$dom->appendChild($urlset);

foreach ($urls as $entry) {
    $urlElement = $dom->createElement('url');
    $loc = $dom->createElement('loc', $entry['loc']);
    $urlElement->appendChild($loc);

    if (!empty($entry['lastmod'] ?? '')) {
        $urlElement->appendChild($dom->createElement('lastmod', $entry['lastmod']));
    }
    if (!empty($entry['changefreq'] ?? '')) {
        $urlElement->appendChild($dom->createElement('changefreq', $entry['changefreq']));
    }
    if (!empty($entry['priority'] ?? '')) {
        $urlElement->appendChild($dom->createElement('priority', $entry['priority']));
    }

    $urlset->appendChild($urlElement);
}

$xmlOutput = $dom->saveXML();

if (!is_dir(dirname($cacheFile))) {
    @mkdir(dirname($cacheFile), 0775, true);
}
@file_put_contents($cacheFile, $xmlOutput, LOCK_EX);

if ($requestMethod === 'HEAD') {
    header('Content-Length: ' . strlen($xmlOutput));
    exit;
}

echo $xmlOutput;
