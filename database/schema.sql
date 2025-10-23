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

-- Temporarily disable FK checks so dependent tables can be dropped safely
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `news_comments`;
DROP TABLE IF EXISTS `news_likes`;
DROP TABLE IF EXISTS `community_post_comments`;
DROP TABLE IF EXISTS `community_post_reactions`;
DROP TABLE IF EXISTS `community_posts`;
DROP TABLE IF EXISTS `matches`;
DROP TABLE IF EXISTS `news`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Table: users
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `badge` VARCHAR(60) DEFAULT 'Tifoso',
    `avatar_url` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_username_unique` (`username`),
    UNIQUE KEY `users_email_unique` (`email`)
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
    `source_url` VARCHAR(255) DEFAULT NULL,
    `published_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `news_slug_unique` (`slug`),
    UNIQUE KEY `news_source_url_unique` (`source_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Table: news_comments
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `news_comments`;
CREATE TABLE `news_comments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `news_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `news_comments_news_id_foreign` (`news_id`),
    KEY `news_comments_user_id_foreign` (`user_id`),
    CONSTRAINT `news_comments_news_id_foreign`
        FOREIGN KEY (`news_id`) REFERENCES `news` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `news_comments_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Table: news_likes
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `news_likes`;
CREATE TABLE `news_likes` (
    `news_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`news_id`, `user_id`),
    KEY `news_likes_user_id_foreign` (`user_id`),
    CONSTRAINT `news_likes_news_id_foreign`
        FOREIGN KEY (`news_id`) REFERENCES `news` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `news_likes_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Table: matches
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `matches`;
CREATE TABLE `matches` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `external_id` VARCHAR(50) DEFAULT NULL,
    `source` VARCHAR(40) DEFAULT NULL,
    `competition` VARCHAR(80) NOT NULL,
    `opponent` VARCHAR(120) NOT NULL,
    `venue` VARCHAR(120) DEFAULT NULL,
    `kickoff_at` DATETIME NOT NULL,
    `status` VARCHAR(80) DEFAULT NULL,
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
-- Table: community_post_reactions
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `community_post_reactions`;
CREATE TABLE `community_post_reactions` (
    `post_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `reaction_type` ENUM('like', 'support') NOT NULL DEFAULT 'like',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`post_id`, `user_id`, `reaction_type`),
    KEY `community_post_reactions_user_id_index` (`user_id`),
    CONSTRAINT `community_post_reactions_post_id_foreign`
        FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `community_post_reactions_user_id_foreign`
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
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `community_post_comments_post_id_foreign` (`post_id`),
    KEY `community_post_comments_user_id_foreign` (`user_id`),
    CONSTRAINT `community_post_comments_post_id_foreign`
        FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `community_post_comments_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------------------------
-- Nessun dato demo di default; popolare le tabelle tramite l'applicazione o script.
