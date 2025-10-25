<?php
try {
    $pdo = new PDO(
        'mysql:host=193.203.168.205;port=3306;dbname=u427445037_bianconerihub;charset=utf8mb4',
        'u427445037_bianconerihub',
        'Ottobre25@',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "Connection OK\n";
    $rows = $pdo->query('SELECT id, username FROM users ORDER BY id ASC')->fetchAll();
    foreach ($rows as $row) {
        echo $row['id'] . ' - ' . $row['username'] . "\n";
    }
} catch (Throwable $exception) {
    echo 'Error: ' . $exception->getMessage() . "\n";
}
