<?php
declare(strict_types=1);

define('BH_NEWS_SYNC_SCRIPT', true);

require_once __DIR__ . '/../config.php';

try {
	syncNewsFeed(true);
	echo '[' . date('Y-m-d H:i:s') . "] Feed sincronizzato con successo\n";
	exit(0);
} catch (Throwable $exception) {
	fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] Feed non sincronizzato: ' . $exception->getMessage() . "\n");
	exit(1);
}
