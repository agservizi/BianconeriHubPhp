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
DROP TABLE IF EXISTS `community_comment_reactions`;
DROP TABLE IF EXISTS `community_post_comments`;
DROP TABLE IF EXISTS `community_post_reactions`;
DROP TABLE IF EXISTS `community_posts`;
DROP TABLE IF EXISTS `matches`;
DROP TABLE IF EXISTS `news`;
DROP TABLE IF EXISTS `password_resets`;
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
    `first_name` VARCHAR(80) DEFAULT NULL,
    `last_name` VARCHAR(80) DEFAULT NULL,
    `avatar_url` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_username_unique` (`username`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Table: password_resets
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
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
    `content_type` ENUM('text', 'photo', 'gallery', 'poll', 'story', 'news') NOT NULL DEFAULT 'text',
    `media_url` VARCHAR(255) DEFAULT NULL,
    `poll_question` VARCHAR(255) DEFAULT NULL,
    `poll_options` JSON DEFAULT NULL,
    `story_title` VARCHAR(120) DEFAULT NULL,
    `story_caption` VARCHAR(255) DEFAULT NULL,
    `story_credit` VARCHAR(120) DEFAULT NULL,
    `shared_news_id` INT UNSIGNED DEFAULT NULL,
    `shared_news_title` VARCHAR(255) DEFAULT NULL,
    `shared_news_slug` VARCHAR(255) DEFAULT NULL,
    `shared_news_excerpt` TEXT DEFAULT NULL,
    `shared_news_tag` VARCHAR(120) DEFAULT NULL,
    `shared_news_image` VARCHAR(255) DEFAULT NULL,
    `shared_news_source_url` VARCHAR(255) DEFAULT NULL,
    `shared_news_published_at` DATETIME DEFAULT NULL,
    `status` ENUM('published', 'scheduled', 'draft') NOT NULL DEFAULT 'published',
    `scheduled_for` DATETIME DEFAULT NULL,
    `published_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `community_posts_user_id_foreign` (`user_id`),
    KEY `community_posts_status_index` (`status`),
    KEY `community_posts_scheduled_for_index` (`scheduled_for`),
    KEY `community_posts_shared_news_id_index` (`shared_news_id`),
    CONSTRAINT `community_posts_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Table: community_post_media
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `community_post_media`;
CREATE TABLE `community_post_media` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(80) DEFAULT NULL,
    `position` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `community_post_media_post_id_foreign` (`post_id`),
    CONSTRAINT `community_post_media_post_id_foreign`
        FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`)
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
-- Table: community_post_mentions
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `community_post_mentions`;
CREATE TABLE `community_post_mentions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `author_id` INT UNSIGNED NOT NULL,
    `mentioned_user_id` INT UNSIGNED NOT NULL,
    `notified_at` DATETIME DEFAULT NULL,
    `viewed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `community_post_mentions_post_user_unique` (`post_id`, `mentioned_user_id`),
    KEY `community_post_mentions_post_id_foreign` (`post_id`),
    KEY `community_post_mentions_mentioned_user_id_foreign` (`mentioned_user_id`),
    KEY `community_post_mentions_viewed_index` (`viewed_at`),
    CONSTRAINT `community_post_mentions_post_id_foreign`
        FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `community_post_mentions_author_id_foreign`
        FOREIGN KEY (`author_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `community_post_mentions_mentioned_user_id_foreign`
        FOREIGN KEY (`mentioned_user_id`) REFERENCES `users` (`id`)
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
-- Table: community_poll_votes
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `community_poll_votes`;
CREATE TABLE `community_poll_votes` (
    `post_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `option_index` TINYINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`post_id`, `user_id`),
    KEY `community_poll_votes_user_id_foreign` (`user_id`),
    CONSTRAINT `community_poll_votes_post_id_foreign`
        FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `community_poll_votes_user_id_foreign`
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
-- Seed data
-- ---------------------------------------------------------------------------
-- Nessun dato demo di default; popolare le tabelle tramite l'applicazione o script.
