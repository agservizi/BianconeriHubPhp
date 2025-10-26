<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$pdo = getDatabaseConnection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "[Error] Unable to obtain a database connection.\n");
    exit(1);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    ensureUserConsentsTable($pdo);

    $pdo->beginTransaction();

    $insertMissing = $pdo->prepare(
        'INSERT INTO user_consents (user_id, cookie_consent_at, privacy_policy_accepted_at, data_processing_acknowledged_at)
         SELECT u.id, NOW(), NOW(), NOW()
         FROM users u
         LEFT JOIN user_consents uc ON uc.user_id = u.id
         WHERE uc.user_id IS NULL'
    );
    $insertMissing->execute();

    $affected = $insertMissing->rowCount();

    $pdo->commit();

    echo "Migration completed. Consents created for {$affected} user(s)." . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, '[Error] Migration failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
