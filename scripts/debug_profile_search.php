<?php
require_once __DIR__ . '/../config.php';

$query = $argv[1] ?? '';
$limit = isset($argv[2]) ? (int) $argv[2] : 5;
$offset = isset($argv[3]) ? (int) $argv[3] : 0;

if ($query === '') {
    fwrite(STDERR, "Usage: php scripts/debug_profile_search.php <query> [limit] [offset]\n");
    exit(1);
}

$result = searchCommunityUsers($query, $limit, $offset);

print_r($result);
