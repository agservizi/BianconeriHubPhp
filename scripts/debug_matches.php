<?php
require __DIR__ . '/../config.php';

$pdo = getDatabaseConnection();
if (!$pdo) {
    fwrite(STDERR, "Connessione al database non disponibile\n");
    exit(1);
}

try {
    ensureMatchesTableSupportsExternalSource($pdo);

    $columns = $pdo->query('SHOW COLUMNS FROM matches')->fetchAll(PDO::FETCH_ASSOC);
    echo "\n== Colonne della tabella matches ==\n";
    foreach ($columns as $column) {
        printf("- %s (%s)\n", $column['Field'], $column['Type']);
    }

    echo "\n== Ultime 10 righe ==\n";
    $sql = "SELECT id, external_id, source, competition, opponent, home_team, away_team, juventus_is_home, kickoff_at, status, status_code, home_score, away_score FROM matches ORDER BY kickoff_at DESC LIMIT 10";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "Nessuna riga trovata\n";
        exit(0);
    }

    foreach ($rows as $row) {
        print_r($row);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Errore durante la lettura: ' . $exception->getMessage() . "\n");
    exit(2);
}
