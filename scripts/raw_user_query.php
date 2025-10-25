<?php
require __DIR__ . '/../config.php';

$pdo = getDatabaseConnection();
if (!$pdo) {
    echo "Connection failed\n";
    exit(1);
}

$term = $argv[1] ?? '';
if ($term === '') {
    echo "Usage: php scripts/raw_user_query.php <term>\n";
    exit(1);
}

$sql = "SELECT u.id, u.username, u.badge, u.avatar_url, u.created_at FROM users u WHERE u.username LIKE :term ORDER BY u.username ASC LIMIT 5 OFFSET 0";
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['term' => "%{$term}%"]);
    $result = $stmt->fetchAll();
    var_export($result);
    echo "\n";
} catch (Throwable $exception) {
    echo 'Exception: ' . $exception->getMessage() . "\n";
    exit(1);
}
