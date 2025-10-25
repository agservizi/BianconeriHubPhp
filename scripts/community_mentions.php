<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

if (!isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Effettua il login per visualizzare le menzioni.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode((string) $rawInput, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Richiesta non valida.']);
    exit;
}

$token = (string) ($payload['_token'] ?? '');
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

$action = strtolower((string) ($payload['action'] ?? 'fetch'));

if ($action === 'fetch') {
    $limit = (int) ($payload['limit'] ?? 5);
    if ($limit < 1) {
        $limit = 5;
    }

    $offset = (int) ($payload['offset'] ?? 0);
    if ($offset < 0) {
        $offset = 0;
    }

    $summary = getCommunityMentionsSummary($userId, $limit, $offset);
    $markViewed = !empty($payload['mark_viewed']);
    $markedCount = 0;

    if ($markViewed && !empty($summary['items'])) {
        $pendingIds = [];
        foreach ($summary['items'] as $item) {
            if (!empty($item['is_unread'])) {
                $pendingIds[] = (int) ($item['id'] ?? 0);
            }
        }

        if (!empty($pendingIds)) {
            $markedCount = markCommunityMentionsAsViewed($userId, $pendingIds);
            if ($markedCount > 0) {
                $summary['unread_count'] = max(0, (int) $summary['unread_count'] - $markedCount);
                foreach ($summary['items'] as &$item) {
                    $item['is_unread'] = false;
                }
                unset($item);
            }
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'items' => $summary['items'],
        'total' => $summary['total'],
        'unread_count' => $summary['unread_count'],
        'has_more' => $summary['has_more'],
        'limit' => $summary['limit'],
        'offset' => $summary['offset'],
        'timestamp' => $summary['timestamp'],
        'marked_count' => $markedCount,
    ]);
    exit;
}

if ($action === 'mark_viewed') {
    $mentionIds = isset($payload['mention_ids']) && is_array($payload['mention_ids']) ? $payload['mention_ids'] : [];
    $normalizedIds = [];
    foreach ($mentionIds as $mentionId) {
        $intId = (int) $mentionId;
        if ($intId > 0) {
            $normalizedIds[] = $intId;
        }
    }

    $markAll = !empty($payload['mark_all']);
    $markedCount = $markAll ? markCommunityMentionsAsViewed($userId, null) : markCommunityMentionsAsViewed($userId, $normalizedIds);

    $limit = (int) ($payload['limit'] ?? 5);
    if ($limit < 1) {
        $limit = 5;
    }

    $summary = getCommunityMentionsSummary($userId, $limit, 0);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'marked_count' => $markedCount,
        'unread_count' => $summary['unread_count'],
        'total' => $summary['total'],
        'items' => $summary['items'],
        'has_more' => $summary['has_more'],
        'limit' => $summary['limit'],
        'offset' => $summary['offset'],
        'timestamp' => $summary['timestamp'],
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Operazione sulle menzioni non supportata.']);

