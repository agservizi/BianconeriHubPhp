<?php
require __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

echo $keys['publicKey'] . PHP_EOL;
echo $keys['privateKey'] . PHP_EOL;
