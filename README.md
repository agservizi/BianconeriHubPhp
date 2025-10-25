# BianconeriHub PHP

Portale community dedicato ai tifosi juventini con news reali, calendario partite e funzionalità social (commenti, like) basate su PHP 8 + MySQL.

## Requisiti

- PHP 8.1 o superiore con estensioni `pdo_mysql`, `curl`, `mbstring`, `openssl` abilitate
- Server MySQL/MariaDB 10.6+
- Composer consigliato (richiesto per abilitare le notifiche push tramite Web Push)

## Configurazione rapida

1. Copia `.env.example` (o crea manualmente) in `.env` e imposta i parametri:
   ```env
   APP_NAME="BianconeriHub"
   APP_TAGLINE="Il cuore pulsante dei tifosi juventini"
   APP_TIMEZONE="Europe/Rome"
   BASE_URL="https://example.com"   # usato per generare link di condivisione
   APP_DEBUG=true

   DB_DRIVER=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=bianconerihub
   DB_USER=root
   DB_PASSWORD=secret
   DB_CHARSET=utf8mb4

   NEWS_FEED_URL=https://www.tuttojuve.com/rss
   SESSION_NAME=bianconerihub_session
   ```

2. Importa/aggiorna lo schema database:
   ```bash
   mysql -u your_user -p your_database < database/schema.sql
   ```
   > **Aggiornamento da versioni precedenti**: se il database era già in uso, applica manualmente i seguenti cambi:
   > ```sql
   > ALTER TABLE news ADD COLUMN source_url VARCHAR(255) NULL AFTER image_path;
   > ALTER TABLE news ADD UNIQUE KEY news_source_url_unique (source_url);
   > 
   > CREATE TABLE news_comments (
   >   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   >   news_id INT UNSIGNED NOT NULL,
   >   user_id INT UNSIGNED NOT NULL,
   >   content TEXT NOT NULL,
   >   created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   >   updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
   >   KEY news_comments_news_id_foreign (news_id),
   >   KEY news_comments_user_id_foreign (user_id),
   >   CONSTRAINT news_comments_news_id_foreign FOREIGN KEY (news_id) REFERENCES news (id) ON DELETE CASCADE ON UPDATE CASCADE,
   >   CONSTRAINT news_comments_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
   > ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   > 
   > CREATE TABLE news_likes (
   >   news_id INT UNSIGNED NOT NULL,
   >   user_id INT UNSIGNED NOT NULL,
   >   created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   >   PRIMARY KEY (news_id, user_id),
   >   KEY news_likes_user_id_foreign (user_id),
   >   CONSTRAINT news_likes_news_id_foreign FOREIGN KEY (news_id) REFERENCES news (id) ON DELETE CASCADE ON UPDATE CASCADE,
   >   CONSTRAINT news_likes_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
   > ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   >
   > -- Notifiche push (ottobre 2025)
   > CREATE TABLE community_followers (
   >   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   >   user_id INT UNSIGNED NOT NULL,
   >   follower_id INT UNSIGNED NOT NULL,
   >   created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   >   UNIQUE KEY community_followers_unique (user_id, follower_id),
   >   KEY community_followers_follower_id_foreign (follower_id),
   >   CONSTRAINT community_followers_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
   >   CONSTRAINT community_followers_follower_id_foreign FOREIGN KEY (follower_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
   > ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

   > -- Community commenti: risposte e like (ottobre 2025)
   > ALTER TABLE community_post_comments ADD COLUMN IF NOT EXISTS parent_comment_id INT UNSIGNED NULL AFTER user_id;
   > -- Se il vincolo esiste già, salta la riga seguente
   > ALTER TABLE community_post_comments ADD CONSTRAINT community_post_comments_parent_comment_id_foreign FOREIGN KEY (parent_comment_id) REFERENCES community_post_comments(id) ON DELETE CASCADE ON UPDATE CASCADE;
   > 
   > CREATE TABLE IF NOT EXISTS community_comment_reactions (
   >   comment_id INT UNSIGNED NOT NULL,
   >   user_id INT UNSIGNED NOT NULL,
   >   created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   >   PRIMARY KEY (comment_id, user_id),
   >   KEY community_comment_reactions_user_id_index (user_id),
   >   CONSTRAINT community_comment_reactions_comment_id_foreign FOREIGN KEY (comment_id) REFERENCES community_post_comments(id) ON DELETE CASCADE ON UPDATE CASCADE,
   >   CONSTRAINT community_comment_reactions_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
   > ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   > 
   > -- Community sondaggi: votazioni (ottobre 2025)
   > CREATE TABLE IF NOT EXISTS community_poll_votes (
   >   post_id INT UNSIGNED NOT NULL,
   >   user_id INT UNSIGNED NOT NULL,
   >   option_index TINYINT UNSIGNED NOT NULL,
   >   created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   >   updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
   >   PRIMARY KEY (post_id, user_id),
   >   KEY community_poll_votes_user_id_foreign (user_id),
   >   CONSTRAINT community_poll_votes_post_id_foreign FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE ON UPDATE CASCADE,
   >   CONSTRAINT community_poll_votes_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
   > ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   > 
   > -- In alternativa puoi lanciare gli script idempotenti:
   > php scripts/migrate_comment_features.php
   > php scripts/migrate_poll_votes.php
   >
   > CREATE TABLE user_push_subscriptions (
   >   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   >   user_id INT UNSIGNED NOT NULL,
   >   endpoint VARCHAR(500) NOT NULL,
   >   public_key VARCHAR(255) NOT NULL,
   >   auth_token VARCHAR(255) NOT NULL,
   >   content_encoding VARCHAR(40) DEFAULT 'aes128gcm',
   >   device_name VARCHAR(120) DEFAULT NULL,
   >   user_agent VARCHAR(255) DEFAULT NULL,
   >   scope ENUM('global','following') NOT NULL DEFAULT 'global',
   >   created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   >   updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
   >   UNIQUE KEY user_push_subscriptions_endpoint_unique (endpoint(191)),
   >   KEY user_push_subscriptions_user_id_foreign (user_id),
   >   CONSTRAINT user_push_subscriptions_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
   > ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   > ```

3. Avvia il server PHP integrato (opzionale):
   ```bash
   php -S 127.0.0.1:8000 -t .
   ```

## Sincronizzazione feed TuttoJuve

- Il feed viene sincronizzato automaticamente al caricamento delle pagine news e memorizzato tramite `updated_at` delle notizie. Il fetch avviene al massimo una volta ogni 15 minuti.
- Puoi forzare la sincronizzazione manualmente da CLI:
  ```bash
  php scripts/sync_news.php
  ```
- Per mantenere il feed aggiornato in produzione, schedula il comando ogni 10-15 minuti (es. cron):
  ```cron
  */15 * * * * /usr/bin/php /path/to/BianconeriHubPhp/scripts/sync_news.php >> /var/log/bianconerihub_sync.log 2>&1
  ```

## Automazioni community

- Pianifica la pubblicazione dei post programmati lanciando periodicamente `php scripts/publish_scheduled_posts.php`. Esempio cron ogni 5 minuti:
   ```cron
   */5 * * * * /usr/bin/php /path/to/BianconeriHubPhp/scripts/publish_scheduled_posts.php >> /var/log/bianconerihub_scheduler.log 2>&1
   ```
- Accertati che la cartella `uploads/community` sia scrivibile dall’utente del web server prima di abilitare gli upload fotografici.

## Utility CLI

- `php scripts/check_db_status.php` stampa un riepilogo delle tabelle attese e segnala eventuali colonne mancanti.
- `php scripts/migrate_comment_features.php` e `php scripts/migrate_poll_votes.php` applicano in modo idempotente le migrazioni community più recenti.
- `php scripts/remote_debug.php` utilizza le credenziali facoltative configurate nello `.env` (`REMOTE_DEBUG_*`) per verificare rapidamente la connettività ad un database remoto.

## Funzionalità principali

- **News live** da TuttoJuve con attribuzione fonte, immagini e lettura approfondita.
- **Interazioni social** su ogni notizia: like/unlike con feedback immediato e commenti moderati (limite 800 caratteri, badge utente).
- **Community board** interna con messaggi dei tifosi, statistiche aggregate e badge ruolo.
- **Calendario partite** con download ICS personalizzato (`?action=download_match_ics&id=...`).
- **Protezione CSRF** su tutti i form e gestione flash message per feedback utente.

## Notifiche push Web Push

1. Installa la libreria PHP per Web Push:
   ```bash
   composer require minishlink/web-push
   ```
2. Genera le chiavi VAPID (una sola volta):
   ```bash
   ./vendor/bin/web-push generate:vapid
   # oppure
   npx web-push generate-vapid-keys
   ```
3. Aggiungi allo `.env` le nuove variabili:
   ```env
   VAPID_PUBLIC_KEY="<chiave pubblica>"
   VAPID_PRIVATE_KEY="<chiave privata>"
   PUSH_SUBJECT="mailto:contatto@esempio.com"   # opzionale, ma consigliato
   PUSH_ICON_PATH="assets/img/push-icon.png"     # opzionale, icona mostrata nelle notifiche
   ```
4. Assicurati che il sito sia servito via HTTPS e che `service-worker.js` sia raggiungibile dalla root del dominio.
5. Gli utenti autenticati possono attivare/disattivare le notifiche dalla pagina community; le preferenze vengono salvate tramite `scripts/push_subscriptions.php` e gestite dal service worker.

Le notifiche vengono inviate automaticamente quando un post viene pubblicato (immediato o programmato) a:
- tutti gli utenti che hanno optato per le notifiche globali;
- i follower dell'autore (se dispongono di una sottoscrizione attiva e hanno scelto l'opzione "Solo gli utenti che seguo").

## Testing manuale consigliato

1. Registra un nuovo utente, fai login e verifica la persistenza sessione.
2. Visita `?page=news` e controlla che le notizie corrispondano al feed RSS.
3. Apri un articolo, metti/togli "Mi piace" e pubblica un commento.
4. Scarica un file ICS da `?page=partite` e importalo nel tuo calendario.
5. Prova a commentare da utente non autenticato per verificare il messaggio di errore.

## Struttura cartelle

- `pages/` – viste principali (home, news, community, partite, auth)
- `includes/` – header, footer, navbar
- `assets/` – CSS/JS statici
- `scripts/` – utility CLI (`sync_news.php`)
- `database/` – schema SQL completo

## Licenza

Progetto a scopo didattico/fan project. Nessuna affiliazione con Juventus FC.
