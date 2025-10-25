<?php
require __DIR__ . '/../config.php';
try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof PDO) {
        echo "Connection OK\n";
        $count = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        echo "users.count=" . $count . "\n";
    } else {
        echo "Connection missing\n";
    }
} catch (Throwable $exception) {
    echo 'Error: ' . $exception->getMessage() . "\n";
}
