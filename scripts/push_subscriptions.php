<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Effettua il login per gestire le notifiche push.']);
    exit;
}

if (!isWebPushConfigured()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Le notifiche push non sono disponibili in questo momento.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode((string) $rawInput, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Richiesta push non valida.']);
    exit;
}

$token = (string) ($payload['token'] ?? '');
if (!validateCsrfToken($token)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Sessione scaduta. Aggiorna la pagina e riprova.']);
    exit;
}

$user = getLoggedInUser();
$userId = (int) ($user['id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utente non riconosciuto.']);
    exit;
}

$action = strtolower((string) ($payload['action'] ?? 'subscribe'));
$scope = (string) ($payload['scope'] ?? 'global');
$subscription = isset($payload['subscription']) && is_array($payload['subscription']) ? $payload['subscription'] : null;
$meta = isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : [];

if ($action === 'unsubscribe') {
    $endpoint = (string) ($payload['endpoint'] ?? ($subscription['endpoint'] ?? ''));
    $result = removePushSubscription($userId, $endpoint);
    http_response_code($result['success'] ? 200 : 422);
    echo json_encode($result);
    exit;
}

if ($action === 'subscribe' || $action === 'update') {
    $result = registerPushSubscription($userId, $subscription, $scope, $meta);
    http_response_code($result['success'] ? 200 : 422);
    echo json_encode($result);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Operazione sulle notifiche non supportata.']);
