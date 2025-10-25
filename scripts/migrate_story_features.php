<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$pdo = getDatabaseConnection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "[Error] Unable to obtain a database connection.\n");
    exit(1);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function bhColumnExists(PDO $pdo, string $table, string $column): bool
{
    $query = 'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->execute(['table' => $table, 'column' => $column]);

    return (bool) $statement->fetchColumn();
}

function bhContentTypeIncludesStory(PDO $pdo): bool
{
    $statement = $pdo->query("SHOW COLUMNS FROM community_posts LIKE 'content_type'");
    $column = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$column) {
        return false;
    }

    $type = (string) ($column['Type'] ?? '');
    return stripos($type, "'story'") !== false;
}

try {
    $transactionStarted = false;
    if (!$pdo->inTransaction()) {
        try {
            $transactionStarted = $pdo->beginTransaction();
        } catch (Throwable $transactionException) {
            // ALTER TABLE statements auto-commit; log and continue without an explicit transaction.
            fwrite(STDOUT, "Warning: unable to start transaction, continuing without it.\n");
            $transactionStarted = false;
        }
    }

    if (!bhContentTypeIncludesStory($pdo)) {
        $pdo->exec("ALTER TABLE community_posts MODIFY content_type ENUM('text','photo','gallery','poll','story') NOT NULL DEFAULT 'text'");
        fwrite(STDOUT, "Updated community_posts.content_type enum to include 'story'.\n");
    } else {
        fwrite(STDOUT, "community_posts.content_type already supports 'story'.\n");
    }

    if (!bhColumnExists($pdo, 'community_posts', 'story_title')) {
        $pdo->exec('ALTER TABLE community_posts ADD COLUMN story_title VARCHAR(120) NULL AFTER poll_options');
        fwrite(STDOUT, "Added story_title column to community_posts.\n");
    } else {
        fwrite(STDOUT, "story_title column already present.\n");
    }

    if (!bhColumnExists($pdo, 'community_posts', 'story_caption')) {
        $pdo->exec('ALTER TABLE community_posts ADD COLUMN story_caption VARCHAR(255) NULL AFTER story_title');
        fwrite(STDOUT, "Added story_caption column to community_posts.\n");
    } else {
        fwrite(STDOUT, "story_caption column already present.\n");
    }

    if (!bhColumnExists($pdo, 'community_posts', 'story_credit')) {
        $pdo->exec('ALTER TABLE community_posts ADD COLUMN story_credit VARCHAR(120) NULL AFTER story_caption');
        fwrite(STDOUT, "Added story_credit column to community_posts.\n");
    } else {
        fwrite(STDOUT, "story_credit column already present.\n");
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    fwrite(STDOUT, "Story migration completed successfully.\n");
    exit(0);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, '[Error] Migration failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
