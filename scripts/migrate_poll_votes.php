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
    $pdo->beginTransaction();

    $tableCheck = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1'
    );
    $tableCheck->execute(['table' => 'community_poll_votes']);
    $tableExists = (bool) $tableCheck->fetchColumn();

    if (!$tableExists) {
        $pdo->exec(
            'CREATE TABLE community_poll_votes (
                post_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                option_index TINYINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (post_id, user_id),
                KEY community_poll_votes_user_id_foreign (user_id),
                CONSTRAINT community_poll_votes_post_id_foreign FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT community_poll_votes_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        fwrite(STDOUT, "Created community_poll_votes table.\n");
    } else {
        fwrite(STDOUT, "community_poll_votes table already present.\n");
    }

    $pdo->commit();
    fwrite(STDOUT, "Migration completed successfully.\n");
    exit(0);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, '[Error] Migration failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
