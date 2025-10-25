<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$pdo = getDatabaseConnection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "[Error] Unable to obtain a database connection.\n");
    exit(1);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function bhUsersColumnExists(PDO $pdo, string $column): bool
{
    $query = 'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'users\' AND column_name = :column LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->execute(['column' => $column]);

    return (bool) $statement->fetchColumn();
}

try {
    $pdo->beginTransaction();

    if (!bhUsersColumnExists($pdo, 'first_name')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN first_name VARCHAR(80) DEFAULT NULL AFTER badge');
        fwrite(STDOUT, "Added first_name column to users.\n");
    }

    if (!bhUsersColumnExists($pdo, 'last_name')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN last_name VARCHAR(80) DEFAULT NULL AFTER first_name');
        fwrite(STDOUT, "Added last_name column to users.\n");
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    fwrite(STDOUT, "Migration completed successfully.\n");
    exit(0);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, '[Error] Migration failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
