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

function bhForeignKeyExists(PDO $pdo, string $table, string $constraintName): bool
{
    $query = 'SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = :table AND constraint_name = :name LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->execute(['table' => $table, 'name' => $constraintName]);

    return (bool) $statement->fetchColumn();
}

function bhTableExists(PDO $pdo, string $table): bool
{
    $query = 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->execute(['table' => $table]);

    return (bool) $statement->fetchColumn();
}

try {
    $pdo->beginTransaction();

    // 1. Ensure parent_comment_id column exists
    if (!bhColumnExists($pdo, 'community_post_comments', 'parent_comment_id')) {
        $pdo->exec('ALTER TABLE community_post_comments ADD COLUMN parent_comment_id INT UNSIGNED NULL AFTER user_id');
        fwrite(STDOUT, "Added parent_comment_id column to community_post_comments.\n");
    } else {
        fwrite(STDOUT, "parent_comment_id column already present.\n");
    }

    // 2. Ensure self-referencing foreign key exists
    $fkName = 'community_post_comments_parent_comment_id_foreign';
    if (!bhForeignKeyExists($pdo, 'community_post_comments', $fkName)) {
        // Remove orphan references before adding FK
        $cleanup = 'UPDATE community_post_comments AS c
                    LEFT JOIN community_post_comments AS p ON p.id = c.parent_comment_id
                    SET c.parent_comment_id = NULL
                    WHERE c.parent_comment_id IS NOT NULL AND p.id IS NULL';
        $pdo->exec($cleanup);

        $pdo->exec('ALTER TABLE community_post_comments ADD CONSTRAINT ' . $fkName . ' FOREIGN KEY (parent_comment_id) REFERENCES community_post_comments(id) ON DELETE CASCADE ON UPDATE CASCADE');
        fwrite(STDOUT, "Added foreign key $fkName on community_post_comments.parent_comment_id.\n");
    } else {
        fwrite(STDOUT, "Foreign key $fkName already present.\n");
    }

    // 3. Ensure helper table for comment reactions exists
    if (!bhTableExists($pdo, 'community_comment_reactions')) {
        $pdo->exec(
            'CREATE TABLE community_comment_reactions (
                comment_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (comment_id, user_id),
                KEY community_comment_reactions_user_id_index (user_id),
                CONSTRAINT community_comment_reactions_comment_id_foreign FOREIGN KEY (comment_id) REFERENCES community_post_comments(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT community_comment_reactions_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        fwrite(STDOUT, "Created community_comment_reactions table.\n");
    } else {
        fwrite(STDOUT, "community_comment_reactions table already present.\n");
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
