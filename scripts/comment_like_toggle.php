<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$respond = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(405, [
        'success' => false,
        'message' => 'Metodo non consentito.',
    ]);
}

$token = isset($_POST['_token']) ? (string) $_POST['_token'] : '';
if (!validateCsrfToken($token)) {
    $respond(419, [
        'success' => false,
        'message' => 'Token non valido. Aggiorna la pagina e riprova.',
    ]);
}

if (!isUserLoggedIn()) {
    $respond(401, [
        'success' => false,
        'message' => 'Effettua il login per mettere Mi piace ai commenti.',
    ]);
}

$commentId = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;
if ($commentId <= 0) {
    $respond(400, [
        'success' => false,
        'message' => 'Commento non valido.',
    ]);
}

$user = getLoggedInUser();
$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    $respond(401, [
        'success' => false,
        'message' => 'Sessione non valida. Effettua di nuovo l\'accesso.',
    ]);
}

$result = toggleCommunityCommentReaction($commentId, $userId);
if (!$result['success']) {
    $respond(500, [
        'success' => false,
        'message' => $result['message'] ?? 'Impossibile aggiornare il Mi piace.',
    ]);
}

$pdo = getDatabaseConnection();
$likesCount = 0;

if ($pdo instanceof PDO) {
    try {
        $countStatement = $pdo->prepare('SELECT COUNT(*) FROM community_comment_reactions WHERE comment_id = :comment_id');
        $countStatement->execute(['comment_id' => $commentId]);
        $likesCount = (int) $countStatement->fetchColumn();
    } catch (PDOException $exception) {
        error_log('[BianconeriHub] Impossibile contare i like del commento: ' . $exception->getMessage());
    }
}

$state = $result['state'] ?? '';
$isLiked = $state === 'added';

$respond(200, [
    'success' => true,
    'comment_id' => $commentId,
    'likes_count' => $likesCount,
    'formatted_count' => number_format($likesCount, 0, ',', '.'),
    'liked' => $isLiked,
    'state' => $state,
]);
