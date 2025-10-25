<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$pdo = getDatabaseConnection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "[Error] Unable to obtain a database connection.\n");
    exit(1);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function bhTableExists(PDO $pdo, string $table): bool
{
    $query = 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->execute(['table' => $table]);

    return (bool) $statement->fetchColumn();
}

function bhColumnExists(PDO $pdo, string $table, string $column): bool
{
    $query = 'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->execute(['table' => $table, 'column' => $column]);

    return (bool) $statement->fetchColumn();
}

function bhIndexExists(PDO $pdo, string $table, string $index): bool
{
    $query = 'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->execute(['table' => $table, 'index' => $index]);

    return (bool) $statement->fetchColumn();
}

function bhForeignKeyExists(PDO $pdo, string $table, string $constraintName): bool
{
    $query = 'SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = :table AND constraint_name = :name LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->execute(['table' => $table, 'name' => $constraintName]);

    return (bool) $statement->fetchColumn();
}

try {
    $pdo->beginTransaction();

    $table = 'community_post_mentions';

    if (!bhTableExists($pdo, $table)) {
        $pdo->exec(
            'CREATE TABLE community_post_mentions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                post_id INT UNSIGNED NOT NULL,
                author_id INT UNSIGNED NOT NULL,
                mentioned_user_id INT UNSIGNED NOT NULL,
                notified_at DATETIME DEFAULT NULL,
                viewed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY community_post_mentions_post_user_unique (post_id, mentioned_user_id),
                KEY community_post_mentions_post_id_foreign (post_id),
                KEY community_post_mentions_mentioned_user_id_foreign (mentioned_user_id),
                KEY community_post_mentions_viewed_index (viewed_at),
                CONSTRAINT community_post_mentions_post_id_foreign FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT community_post_mentions_author_id_foreign FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT community_post_mentions_mentioned_user_id_foreign FOREIGN KEY (mentioned_user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        fwrite(STDOUT, "Created community_post_mentions table.\n");
    } else {
        $columns = ['id', 'post_id', 'author_id', 'mentioned_user_id', 'notified_at', 'viewed_at', 'created_at'];
        foreach ($columns as $column) {
            if (!bhColumnExists($pdo, $table, $column)) {
                $definition = match ($column) {
                    'id' => 'ADD COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
                    'post_id', 'author_id', 'mentioned_user_id' => 'ADD COLUMN ' . $column . ' INT UNSIGNED NOT NULL',
                    'notified_at' => 'ADD COLUMN notified_at DATETIME DEFAULT NULL',
                    'viewed_at' => 'ADD COLUMN viewed_at DATETIME DEFAULT NULL',
                    'created_at' => 'ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    default => null,
                };

                if ($definition !== null) {
                    $pdo->exec('ALTER TABLE ' . $table . ' ' . $definition);
                    fwrite(STDOUT, "Added column $column to $table.\n");
                }
            }
        }

        if (!bhIndexExists($pdo, $table, 'community_post_mentions_post_user_unique')) {
            $pdo->exec('ALTER TABLE ' . $table . ' ADD UNIQUE KEY community_post_mentions_post_user_unique (post_id, mentioned_user_id)');
            fwrite(STDOUT, "Added unique index community_post_mentions_post_user_unique.\n");
        }

        if (!bhIndexExists($pdo, $table, 'community_post_mentions_post_id_foreign')) {
            $pdo->exec('ALTER TABLE ' . $table . ' ADD KEY community_post_mentions_post_id_foreign (post_id)');
            fwrite(STDOUT, "Added index community_post_mentions_post_id_foreign.\n");
        }

        if (!bhIndexExists($pdo, $table, 'community_post_mentions_mentioned_user_id_foreign')) {
            $pdo->exec('ALTER TABLE ' . $table . ' ADD KEY community_post_mentions_mentioned_user_id_foreign (mentioned_user_id)');
            fwrite(STDOUT, "Added index community_post_mentions_mentioned_user_id_foreign.\n");
        }

        if (!bhIndexExists($pdo, $table, 'community_post_mentions_viewed_index')) {
            $pdo->exec('ALTER TABLE ' . $table . ' ADD KEY community_post_mentions_viewed_index (viewed_at)');
            fwrite(STDOUT, "Added index community_post_mentions_viewed_index.\n");
        }

        if (!bhForeignKeyExists($pdo, $table, 'community_post_mentions_post_id_foreign')) {
            $pdo->exec('ALTER TABLE ' . $table . ' ADD CONSTRAINT community_post_mentions_post_id_foreign FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE ON UPDATE CASCADE');
            fwrite(STDOUT, "Added FK community_post_mentions_post_id_foreign.\n");
        }

        if (!bhForeignKeyExists($pdo, $table, 'community_post_mentions_author_id_foreign')) {
            $pdo->exec('ALTER TABLE ' . $table . ' ADD CONSTRAINT community_post_mentions_author_id_foreign FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE');
            fwrite(STDOUT, "Added FK community_post_mentions_author_id_foreign.\n");
        }

        if (!bhForeignKeyExists($pdo, $table, 'community_post_mentions_mentioned_user_id_foreign')) {
            $pdo->exec('ALTER TABLE ' . $table . ' ADD CONSTRAINT community_post_mentions_mentioned_user_id_foreign FOREIGN KEY (mentioned_user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE');
            fwrite(STDOUT, "Added FK community_post_mentions_mentioned_user_id_foreign.\n");
        }
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
