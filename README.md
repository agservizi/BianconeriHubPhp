# BianconeriHub PHP

Portale community dedicato ai tifosi juventini con news reali, calendario partite e funzionalità social (commenti, like) basate su PHP 8 + MySQL.

## Requisiti

- PHP 8.1 o superiore con estensioni `pdo_mysql`, `curl`, `mbstring`, `openssl` abilitate
- Server MySQL/MariaDB 10.6+
- Composer facoltativo (il progetto non usa librerie esterne al momento)

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

## Funzionalità principali

- **News live** da TuttoJuve con attribuzione fonte, immagini e lettura approfondita.
- **Interazioni social** su ogni notizia: like/unlike con feedback immediato e commenti moderati (limite 800 caratteri, badge utente).
- **Community board** interna con messaggi dei tifosi, statistiche aggregate e badge ruolo.
- **Calendario partite** con download ICS personalizzato (`?action=download_match_ics&id=...`).
- **Protezione CSRF** su tutti i form e gestione flash message per feedback utente.

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
