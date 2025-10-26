<?php
require __DIR__ . '/../config.php';

$pdo = getDatabaseConnection();
if (!$pdo) {
    fwrite(STDERR, "Connessione DB non riuscita\n");
    exit(1);
}

$subscriptionClass = 'Minishlink\\WebPush\\Subscription';
if (!class_exists($subscriptionClass)) {
    fwrite(STDERR, "Classe Subscription non disponibile\n");
    exit(1);
}

$subscription = $pdo->query('SELECT * FROM user_push_subscriptions ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$subscription) {
    fwrite(STDERR, "Nessuna iscrizione push trovata\n");
    exit(1);
}

$webPush = getWebPushClient();
if (!$webPush) {
    fwrite(STDERR, "Client WebPush non inizializzato\n");
    exit(1);
}

$payload = json_encode([
    'title' => 'Test Push',
    'body' => 'Questa è una notifica di prova inviata dal CLI.',
    'icon' => appUrl(env('PUSH_ICON_PATH', 'assets/img/push-icon.png')),
    'url' => appUrl('?page=community'),
], JSON_UNESCAPED_UNICODE);

$subscriptionObject = $subscriptionClass::create([
    'endpoint' => $subscription['endpoint'],
    'publicKey' => $subscription['public_key'],
    'authToken' => $subscription['auth_token'],
    'contentEncoding' => $subscription['content_encoding'] ?: 'aes128gcm',
]);

$queueMethod = method_exists($webPush, 'queueNotification') ? 'queueNotification' : (method_exists($webPush, 'sendNotification') ? 'sendNotification' : null);
if (!$queueMethod) {
    fwrite(STDERR, "Nessun metodo disponibile per inviare notifiche\n");
    exit(1);
}

$webPush->{$queueMethod}($subscriptionObject, $payload);

foreach ($webPush->flush() as $report) {
    echo 'Endpoint: ' . $report->getEndpoint() . PHP_EOL;
    echo 'Successo: ' . ($report->isSuccess() ? 'sì' : 'no') . PHP_EOL;
    if (!$report->isSuccess()) {
        echo 'Errore: ' . $report->getReason() . PHP_EOL;
    }
}
