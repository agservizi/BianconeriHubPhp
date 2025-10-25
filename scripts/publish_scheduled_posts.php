<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$count = publishDueCommunityPosts();

$timestamp = date('Y-m-d H:i:s');

if ($count === 0) {
    echo "[$timestamp] Nessun post programmato da pubblicare.\n";
    exit(0);
}

echo "[$timestamp] Pubblicati {$count} post programmati.\n";
