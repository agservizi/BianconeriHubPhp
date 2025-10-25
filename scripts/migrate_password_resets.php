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
    $sql = 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1';
    $statement = $pdo->prepare($sql);
    $statement->execute(['table' => $table]);

    return (bool) $statement->fetchColumn();
}

function bhColumnExists(PDO $pdo, string $table, string $column): bool
{
    $sql = 'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1';
    $statement = $pdo->prepare($sql);
    $statement->execute(['table' => $table, 'column' => $column]);

    return (bool) $statement->fetchColumn();
}

function bhIndexExists(PDO $pdo, string $table, string $index): bool
{
    $sql = 'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index LIMIT 1';
    $statement = $pdo->prepare($sql);
    $statement->execute(['table' => $table, 'index' => $index]);

    return (bool) $statement->fetchColumn();
}

function bhForeignKeyExists(PDO $pdo, string $table, string $constraint): bool
{
    $sql = 'SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = :table AND constraint_name = :constraint LIMIT 1';
    $statement = $pdo->prepare($sql);
    $statement->execute(['table' => $table, 'constraint' => $constraint]);

    return (bool) $statement->fetchColumn();
}

try {
    $pdo->beginTransaction();

    $table = 'password_resets';

    if (!bhTableExists($pdo, $table)) {
        $pdo->exec(
            'CREATE TABLE password_resets (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY password_resets_token_hash_unique (token_hash),
                KEY password_resets_user_id_foreign (user_id),
                KEY password_resets_expires_at_index (expires_at),
                CONSTRAINT password_resets_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        fwrite(STDOUT, "Created password_resets table.\n");
    } else {
        fwrite(STDOUT, "password_resets table already present.\n");

        if (!bhColumnExists($pdo, $table, 'token_hash')) {
            $pdo->exec('ALTER TABLE password_resets ADD COLUMN token_hash CHAR(64) NOT NULL AFTER user_id');
            fwrite(STDOUT, "Added token_hash column.\n");
        }

        if (!bhColumnExists($pdo, $table, 'expires_at')) {
            $pdo->exec('ALTER TABLE password_resets ADD COLUMN expires_at DATETIME NOT NULL AFTER token_hash');
            fwrite(STDOUT, "Added expires_at column.\n");
        }

        if (!bhColumnExists($pdo, $table, 'created_at')) {
            $pdo->exec('ALTER TABLE password_resets ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER expires_at');
            fwrite(STDOUT, "Added created_at column.\n");
        }

        if (!bhIndexExists($pdo, $table, 'password_resets_token_hash_unique')) {
            $pdo->exec('ALTER TABLE password_resets ADD UNIQUE KEY password_resets_token_hash_unique (token_hash)');
            fwrite(STDOUT, "Created unique index on token_hash.\n");
        }

        if (!bhIndexExists($pdo, $table, 'password_resets_user_id_foreign')) {
            $pdo->exec('ALTER TABLE password_resets ADD KEY password_resets_user_id_foreign (user_id)');
            fwrite(STDOUT, "Created index on user_id.\n");
        }

        if (!bhIndexExists($pdo, $table, 'password_resets_expires_at_index')) {
            $pdo->exec('ALTER TABLE password_resets ADD KEY password_resets_expires_at_index (expires_at)');
            fwrite(STDOUT, "Created index on expires_at.\n");
        }

        $fkName = 'password_resets_user_id_foreign';
        if (!bhForeignKeyExists($pdo, $table, $fkName)) {
            $pdo->exec('ALTER TABLE password_resets ADD CONSTRAINT ' . $fkName . ' FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE');
            fwrite(STDOUT, "Added foreign key constraint on user_id.\n");
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
