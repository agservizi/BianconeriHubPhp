<?php
require __DIR__ . '/../config.php';

$pdo = getDatabaseConnection();
if (!$pdo) {
    fwrite(STDERR, "Connessione DB non riuscita\n");
    exit(1);
}

$sql = 'SELECT id, user_id, scope, endpoint, created_at, updated_at FROM user_push_subscriptions ORDER BY id DESC LIMIT 20';
$stmt = $pdo->query($sql);
foreach ($stmt as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
