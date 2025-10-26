<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$baseUrl = rtrim(getApplicationBaseUrl(), '/');
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$lines = [
    '# robots.txt generato dinamicamente',
    'User-agent: *',
    'Allow: /assets/',
    'Allow: /uploads/',
    'Disallow: /scripts/',
    'Disallow: /storage/',
    'Disallow: /database/',
    'Disallow: /vendor/',
    'Disallow: /*?ajax=1',
    'Disallow: /*?page=login',
    'Disallow: /*?page=register',
    'Disallow: /*?page=password_forgot',
    'Disallow: /*?page=password_reset',
    '',
    'Sitemap: ' . $baseUrl . '/sitemap.xml',
];

$content = implode(PHP_EOL, $lines) . PHP_EOL;

if ($requestMethod === 'HEAD') {
    header('Content-Length: ' . strlen($content));
    exit;
}

echo $content;
