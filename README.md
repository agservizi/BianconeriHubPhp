```
**********************************************************************
*                                                                    *
*  BIANCONERIHUB PHP — LA CURVA DIGITALE DEI TIFOSI BIANCONERI ⭐⭐⭐  *
*                                                                    *
**********************************************************************
```

# BianconeriHub PHP

> **La curva digitale dei tifosi bianconeri.** Web app full-stack in PHP 8 che unisce news verificate, community interattiva, partite e funzionalità PWA.

---

## Indice

- [BianconeriHub PHP](#bianconerihub-php)
  - [Indice](#indice)
  - [Panoramica](#panoramica)
  - [Stack e dipendenze](#stack-e-dipendenze)
  - [Setup rapido](#setup-rapido)
  - [Configurazione ambiente](#configurazione-ambiente)
  - [Database e migrazioni](#database-e-migrazioni)
  - [Script CLI utili](#script-cli-utili)
  - [Funzionalità principali](#funzionalità-principali)
  - [PWA e notifiche push](#pwa-e-notifiche-push)
    - [Inviare notifiche](#inviare-notifiche)
  - [Flussi automatizzati](#flussi-automatizzati)
  - [Checklist di test manuali](#checklist-di-test-manuali)
  - [Struttura del progetto](#struttura-del-progetto)
  - [Licenza](#licenza)

---

## Panoramica

BianconeriHub è una fan app che replica lo stile visivo nero-argento con dettagli dorati tipici della curva juventina.

- **Community viva**: post, like, commenti annidati, reazioni e sondaggi.
- **News sempre fresche**: integrazione RSS con caching intelligente e deduplicazione URL.
- **Match center**: calendario, schede gara, esportazione ICS.
- **PWA mobile-first**: installabile, offline-ready, push notifications.
- **Governance**: consensi privacy automatici, compressione immagini, mail di benvenuto e avviso admin.

## Stack e dipendenze

- Linguaggio: **PHP 8.1+**
- Database: **MySQL/MariaDB 10.6+**
- Librerie principali:
   - `guzzlehttp/guzzle` per feed esterni
   - `minishlink/web-push` (opzionale) per notifiche push
   - `resend/resend-php` per l'invio email transazionale
- Service Worker e PWA via `service-worker.js`
- Front-end: Tailwind utility-first, JavaScript vanilla con progressive enhancement.

## Setup rapido

```bash
# 1. Clona la repo
git clone https://github.com/agservizi/BianconeriHubPhp.git
cd BianconeriHubPhp

# 2. Installa dipendenze opzionali (push/email)
composer install

# 3. Configura le variabili ambiente
cp .env.example .env

# 4. Genera chiavi VAPID se vuoi le push
php vendor/bin/web-push generate:vapid

# 5. Importa lo schema base
mysql -u user -p database < database/schema.sql

# 6. Avvia un server locale (opzionale)
php -S 127.0.0.1:8000 -t .
```

## Configurazione ambiente

Sezioni principali del file `.env`:

```env
APP_NAME="BianconeriHub"
APP_TAGLINE="Il cuore pulsante dei tifosi juventini"
APP_TIMEZONE="Europe/Rome"
BASE_URL="https://example.com"

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=bianconerihub
DB_USER=root
DB_PASSWORD=secret

SESSION_NAME=bianconerihub_session
NEWS_FEED_URL=https://www.tuttojuve.com/rss

# Email Resend
RESEND_API_KEY="re_xxx"
MAIL_FROM_ADDRESS="notifiche@bianconerihub.com"
MAIL_FROM_NAME="BianconeriHub"

# Notifiche push (facoltative)
VAPID_PUBLIC_KEY="..."
VAPID_PRIVATE_KEY="..."
PUSH_SUBJECT="mailto:contatto@example.com"
```

> ℹ️ **Resend sandbox**: autorizza `ag.servizi16@gmail.com` (usato per gli alert sugli utenti registrati) se stai testando senza dominio verificato.

## Database e migrazioni

Schema completo in `database/schema.sql` e copia speculare in `bianconerihub/database/schema.sql`.

Migratori idempotenti disponibili in `scripts/`:

```bash
php scripts/migrate_user_identity.php        # profili e avatar
php scripts/migrate_user_consents.php        # tabella consensi privacy
php scripts/migrate_comment_features.php     # risposte/commenti + like
php scripts/migrate_poll_votes.php           # voti sondaggi community
php scripts/migrate_story_features.php       # spotlight fan stories
php scripts/migrate_community_mentions.php   # menzioni utenti @username
php scripts/migrate_community_news_share.php # share news in community
php scripts/migrate_password_resets.php      # token reset password
php scripts/migrate_user_identity.php        # profili estesi
```

Se aggiorni da versioni precedenti, lancia gli script in sequenza per evitare SQL manuale. Tutte le query sono defensive (CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).

## Script CLI utili

| Script | Descrizione |
| --- | --- |
| `scripts/sync_news.php` | Forza l'aggiornamento del feed TuttoJuve. |
| `scripts/publish_scheduled_posts.php` | Pubblica i post programmati. |
| `scripts/check_db_status.php` | Confronta schema atteso vs database attuale. |
| `scripts/debug_matches.php` | Debug rapido del calendario partite. |
| `scripts/remote_debug.php` | Test connettività a database remoti configurati. |
| `scripts/push_subscriptions.php` | Gestione iscrizioni Web Push via CLI. |

## Funzionalità principali

- **Community board**
   - Post con immagini (compressione automatica WebP/JPEG a < 200 KB, GIF vincolate).
   - Like, support reazioni, commenti annidati, menzioni con notifiche.
   - Spotlight "Raccolta Storie Bianconere" con contenuto reale dal DB.
- **Profilo tifoso**
   - Avatar/Cover ottimizzati server-side, badge dinamici, statistiche personali.
- **Newsroom**
   - Import feed con deduplica `source_url`, commenti e like per articolo.
- **Partite**
   - Calendario stagionale, generazione ICS per singolo match, debug CLI.
- **Autenticazione**
   - Registrazione con consensi auto-approvati, email di benvenuto + notifica admin, reset password temporizzato.
- **Esperienza Mobile**
   - Header responsive con brand inline, guida installazione PWA per iOS/Android.

## PWA e notifiche push

- `service-worker.js` gestisce cache offline e routing fallback su `offline.html`.
- Banner installazione mobile (`data-pwa-install-banner`) intercetta l'evento `beforeinstallprompt` quando supportato.
- Guida modale con istruzioni manuali per i browser che non espongono il prompt (Safari iOS).
- Toggle notifiche all'interno della community con scoping `global` / `following`.
- Backend Web Push tramite `minishlink/web-push` e chiavi VAPID.

### Inviare notifiche

1. Configura le chiavi VAPID nello `.env`.
2. Assicurati che gli utenti abbiano permesso push (UI lato community).
3. Usa `scripts/push_subscriptions.php` o lancia il cron `publish_scheduled_posts.php` (invia in automatico al momento della pubblicazione).

## Flussi automatizzati

- **Sincronizzazione news** (via cron ogni 15 min): `php scripts/sync_news.php`
- **Pubblicazione programmata** (ogni 5 min): `php scripts/publish_scheduled_posts.php`
- **Pulizia sessioni/consensi**: gestita dal core PHP (cron consigliato per garbage collection sessioni).
- **Email**
   - Benvenuto immediato (utente).
   - Alert amministratore a `ag.servizi16@gmail.com`.
   - Reset password con scadenza 60 minuti.

## Checklist di test manuali

1. **Registrazione**: esegui signup, controlla email utente + notifica admin, verifica auto-login e cookie consensi.
2. **Upload foto community**: carica JPEG > 1 MB e verifica compressione < 200 KB, fallback per GIF > 200 KB.
3. **PWA**: su Android verifica prompt installazione, su iOS controlla apertura guida manuale.
4. **Push**: abilita push, crea post programmato, attendi invio e verifica ricezione.
5. **News**: apri pagina `?page=news`, conferma aggiornamento feed e interazione like/commento.
6. **Calendario**: scarica ICS da `?page=partite`, importa in calendar personale.

## Struttura del progetto

```
├─ assets/
│  ├─ css/tailwind.css
│  ├─ js/app.js
│  └─ data/ (dizionari comuni, città italiane)
├─ database/
│  ├─ schema.sql
│  └─ verify_schema.sql
├─ includes/ (header, footer, navbar, componenti)
├─ pages/ (home, community, news, login, ecc.)
├─ scripts/ (migrazioni, sync, debug)
├─ storage/ (cache, logs)
├─ uploads/ (avatar, cover, foto community)
├─ vendor/ (Composer)
├─ manifest.webmanifest
├─ service-worker.js
└─ config.php (core con helper DB, auth, email, pwa)
```

## Licenza

Progetto fan-based a uso didattico. Non affiliato a Juventus FC. Tutti i marchi appartengono ai rispettivi proprietari.
