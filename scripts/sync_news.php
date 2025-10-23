<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

syncNewsFeed(true);

echo '[' . date('Y-m-d H:i:s') . "] Feed sincronizzato con successo\n";
