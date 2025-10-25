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

try {
    if (!bhTableExists($pdo, 'community_posts')) {
        fwrite(STDERR, "[Error] Table community_posts not found.\n");
        exit(1);
    }

    $transactionStarted = $pdo->beginTransaction();

    $columnTypeStatement = $pdo->prepare('SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1');
    $columnTypeStatement->execute([
        'table' => 'community_posts',
        'column' => 'content_type',
    ]);
    $columnType = (string) $columnTypeStatement->fetchColumn();

    if (stripos($columnType, "'news'") === false) {
        $pdo->exec("ALTER TABLE community_posts MODIFY content_type ENUM('text','photo','gallery','poll','story','news') NOT NULL DEFAULT 'text'");
        fwrite(STDOUT, "Added 'news' content type to community_posts.content_type.\n");
    } else {
        fwrite(STDOUT, "'news' content type already present.\n");
    }

    $columnDefinitions = [
        'shared_news_id' => 'ALTER TABLE community_posts ADD COLUMN shared_news_id INT UNSIGNED DEFAULT NULL',
        'shared_news_title' => 'ALTER TABLE community_posts ADD COLUMN shared_news_title VARCHAR(255) DEFAULT NULL',
        'shared_news_slug' => 'ALTER TABLE community_posts ADD COLUMN shared_news_slug VARCHAR(255) DEFAULT NULL',
        'shared_news_excerpt' => 'ALTER TABLE community_posts ADD COLUMN shared_news_excerpt TEXT DEFAULT NULL',
        'shared_news_tag' => 'ALTER TABLE community_posts ADD COLUMN shared_news_tag VARCHAR(120) DEFAULT NULL',
        'shared_news_image' => 'ALTER TABLE community_posts ADD COLUMN shared_news_image VARCHAR(255) DEFAULT NULL',
        'shared_news_source_url' => 'ALTER TABLE community_posts ADD COLUMN shared_news_source_url VARCHAR(255) DEFAULT NULL',
        'shared_news_published_at' => 'ALTER TABLE community_posts ADD COLUMN shared_news_published_at DATETIME DEFAULT NULL',
    ];

    foreach ($columnDefinitions as $column => $statementSql) {
        if (!bhColumnExists($pdo, 'community_posts', $column)) {
            $pdo->exec($statementSql);
            fwrite(STDOUT, "Added column $column to community_posts.\n");
        } else {
            fwrite(STDOUT, "Column $column already present.\n");
        }
    }

    if (!bhIndexExists($pdo, 'community_posts', 'community_posts_shared_news_id_index')) {
        $pdo->exec('ALTER TABLE community_posts ADD KEY community_posts_shared_news_id_index (shared_news_id)');
        fwrite(STDOUT, "Added index community_posts_shared_news_id_index.\n");
    } else {
        fwrite(STDOUT, "Index community_posts_shared_news_id_index already present.\n");
    }

    if ($transactionStarted && $pdo->inTransaction()) {
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
