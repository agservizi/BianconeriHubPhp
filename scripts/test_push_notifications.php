<?php
require __DIR__ . '/../config.php';

$pdo = getDatabaseConnection();
if (!$pdo) {
    fwrite(STDERR, "Connessione DB fallita\n");
    exit(1);
}

$subscriptions = $pdo->query('SELECT * FROM user_push_subscriptions ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
if (!$subscriptions) {
    fwrite(STDOUT, "Nessuna iscrizione push trovata\n");
    exit(0);
}

$webPush = getWebPushClient();
if (!$webPush) {
    fwrite(STDERR, "Client WebPush non disponibile\n");
    exit(1);
}

$subscriptionClass = 'Minishlink\\WebPush\\Subscription';
if (!class_exists($subscriptionClass)) {
    fwrite(STDERR, "Classe Subscription non caricata\n");
    exit(1);
}

$payload = json_encode([
    'title' => 'BianconeriHub (test)',
    'body' => 'Questa è una notifica di prova inviata dall\'ambiente locale.',
    'icon' => appUrl(env('PUSH_ICON_PATH', 'assets/img/push-icon.png')),
    'badge' => appUrl(env('PUSH_ICON_PATH', 'assets/img/push-icon.png')),
    'data' => ['url' => appUrl('?page=community')],
], JSON_UNESCAPED_UNICODE);

$queueMethod = method_exists($webPush, 'queueNotification') ? 'queueNotification' : (method_exists($webPush, 'sendNotification') ? 'sendNotification' : null);
if ($queueMethod === null) {
    fwrite(STDERR, "Nessun metodo di coda disponibile\n");
    exit(1);
}

foreach ($subscriptions as $subscription) {
    if (empty($subscription['endpoint'])) {
        continue;
    }

    $subscriptionObject = $subscriptionClass::create([
        'endpoint' => $subscription['endpoint'],
        'publicKey' => $subscription['public_key'],
        'authToken' => $subscription['auth_token'],
        'contentEncoding' => $subscription['content_encoding'] ?: 'aes128gcm',
    ]);

    $webPush->{$queueMethod}($subscriptionObject, $payload);
}

$allSuccess = true;
foreach ($webPush->flush() as $report) {
    $line = 'Endpoint: ' . $report->getEndpoint() . PHP_EOL;
    $line .= 'Successo: ' . ($report->isSuccess() ? 'sì' : 'no') . PHP_EOL;

    if (!$report->isSuccess()) {
        $allSuccess = false;
        $line .= 'Errore: ' . $report->getReason() . PHP_EOL;
    }

    fwrite(STDOUT, $line . PHP_EOL);
}

exit($allSuccess ? 0 : 2);
