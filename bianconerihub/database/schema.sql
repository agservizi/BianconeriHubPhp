-- BianconeriHub database schema
-- Target RDBMS: MySQL 8.x (compatible with MariaDB 10.6+)

-- IMPORTANT
-- - Replace the placeholder database name below if you want to install
--   the schema to a different schema than the one defined in your .env file.
-- - Run this script with a MySQL user that has privileges to create databases,
--   tables and foreign keys.

CREATE DATABASE IF NOT EXISTS `u427445037_bianconerihub`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `u427445037_bianconerihub`;

-- ---------------------------------------------------------------------------
-- Table: users
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `badge` VARCHAR(60) DEFAULT 'Tifoso',
    `first_name` VARCHAR(80) DEFAULT NULL,
    `last_name` VARCHAR(80) DEFAULT NULL,
    `avatar_url` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_username_unique` (`username`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `password_resets_token_hash_unique` (`token_hash`),
    KEY `password_resets_user_id_foreign` (`user_id`),
    KEY `password_resets_expires_at_index` (`expires_at`),
    CONSTRAINT `password_resets_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Table: news
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `news`;
CREATE TABLE `news` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(180) NOT NULL,
    `slug` VARCHAR(200) NOT NULL,
    `tag` VARCHAR(40) DEFAULT NULL,
    `excerpt` TEXT,
    `body` LONGTEXT,
    `image_path` VARCHAR(255) DEFAULT NULL,
    `published_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `news_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `matches`;
CREATE TABLE `matches` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `external_id` VARCHAR(50) DEFAULT NULL,
    `source` VARCHAR(40) DEFAULT NULL,
    `competition` VARCHAR(80) NOT NULL,
    `opponent` VARCHAR(120) NOT NULL,
    `home_team` VARCHAR(120) DEFAULT NULL,
    `away_team` VARCHAR(120) DEFAULT NULL,
    `juventus_is_home` TINYINT(1) DEFAULT NULL,
    `venue` VARCHAR(120) DEFAULT NULL,
    `kickoff_at` DATETIME NOT NULL,
    `status` VARCHAR(80) DEFAULT NULL,
    `status_code` VARCHAR(40) DEFAULT NULL,
    `home_score` TINYINT UNSIGNED DEFAULT NULL,
    `away_score` TINYINT UNSIGNED DEFAULT NULL,
    `broadcast` VARCHAR(120) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `matches_external_id_unique` (`external_id`),
    KEY `matches_source_index` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Table: community_posts
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `community_posts`;
CREATE TABLE `community_posts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `community_posts_user_id_foreign` (`user_id`),
    CONSTRAINT `community_posts_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Table: community_post_comments
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `community_post_comments`;
CREATE TABLE `community_post_comments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `parent_comment_id` INT UNSIGNED DEFAULT NULL,
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `community_post_comments_post_id_foreign` (`post_id`),
    KEY `community_post_comments_user_id_foreign` (`user_id`),
    KEY `community_post_comments_parent_comment_id_foreign` (`parent_comment_id`),
    CONSTRAINT `community_post_comments_post_id_foreign`
        FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `community_post_comments_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `community_post_comments_parent_comment_id_foreign`
        FOREIGN KEY (`parent_comment_id`) REFERENCES `community_post_comments` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Table: community_comment_reactions
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `community_comment_reactions`;
CREATE TABLE `community_comment_reactions` (
    `comment_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`comment_id`, `user_id`),
    KEY `community_comment_reactions_user_id_index` (`user_id`),
    CONSTRAINT `community_comment_reactions_comment_id_foreign`
        FOREIGN KEY (`comment_id`) REFERENCES `community_post_comments` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `community_comment_reactions_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Table: community_followers
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `community_followers`;
CREATE TABLE `community_followers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `follower_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `community_followers_unique` (`user_id`, `follower_id`),
    KEY `community_followers_follower_id_foreign` (`follower_id`),
    CONSTRAINT `community_followers_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `community_followers_follower_id_foreign`
        FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Table: user_push_subscriptions
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `user_push_subscriptions`;
CREATE TABLE `user_push_subscriptions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `endpoint` VARCHAR(500) NOT NULL,
    `public_key` VARCHAR(255) NOT NULL,
    `auth_token` VARCHAR(255) NOT NULL,
    `content_encoding` VARCHAR(40) DEFAULT 'aes128gcm',
    `device_name` VARCHAR(120) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `scope` ENUM('global', 'following') NOT NULL DEFAULT 'global',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_push_subscriptions_endpoint_unique` (`endpoint`(191)),
    KEY `user_push_subscriptions_user_id_foreign` (`user_id`),
    CONSTRAINT `user_push_subscriptions_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed data (optional)
-- ---------------------------------------------------------------------------
INSERT INTO `users` (`username`, `email`, `password_hash`, `badge`) VALUES
    ('chiara96', 'chiara96@example.com', '$2y$10$C05E8Q6NhuGtpdD95cyE3e0GAn28AL5soiceqd7qV3CyMfCVxYvBy', 'Veterana'),
    ('marco_juve', 'marco@example.com', '$2y$10$3AP9bZY4F1ZXQ17Y27RzO.WsQtPjUJ4YGAPAJHHtYEpPvdEnkQg5G', 'Curva Sud'),
    ('carminecavaliere', 'ag.servizi16@gmail.com', '$2y$12$hdCLkxideZrfxYYjSrhg7ukgitISfpx4p.cLUYOls6qLavIsxlNC.', 'Founder');

INSERT INTO `community_posts` (`user_id`, `content`, `created_at`) VALUES
    (1, 'Che emozione rivedere in campo il capitano! Prestazione da leader vero, continuiamo così! ⚪⚫', NOW() - INTERVAL 5 MINUTE),
    (2, 'Secondo voi dovremmo cambiare modulo contro il Bayern? Difesa a tre o restiamo col 4-3-3?', NOW() - INTERVAL 20 MINUTE);

INSERT INTO `matches` (`competition`, `opponent`, `venue`, `kickoff_at`, `status`, `broadcast`) VALUES
    ('Serie A', 'Milan', 'Allianz Stadium', '2025-10-27 20:45:00', 'Big match', 'DAZN; Sky Sport'),
    ('Champions League', 'Bayern Monaco', 'Allianz Arena', '2025-11-05 21:00:00', 'Trasferta impegnativa', 'Prime Video');

INSERT INTO `news` (`title`, `slug`, `tag`, `excerpt`, `body`, `image_path`, `published_at`) VALUES
    ('La nuova era bianconera: focus sui giovani', 'nuova-era-bianconera-giovani', 'Analisi', 'Under 23 e Next Gen pronte a conquistare minuti importanti con la prima squadra.', 'Contenuto completo da integrare.', 'assets/img/news1.jpg', NOW() - INTERVAL 1 DAY),
    ('Allenamento a porte aperte: entusiasmo a Vinovo', 'allenamento-porte-aperte-vinovo', 'Report', 'Più di 5.000 tifosi presenti per abbracciare la squadra prima del big match.', 'Contenuto completo da integrare.', 'assets/img/news2.jpg', NOW() - INTERVAL 2 DAY);
