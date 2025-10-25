-- BianconeriHub schema verification script
-- Esegue alcuni controlli sulla struttura del database corrente.
-- Utilizzo suggerito:
--   mysql -u <utente> -p <database> < database/verify_schema.sql

-- 1. Identifica il database corrente
SELECT DATABASE() AS current_schema;

-- 2. Elenco tabelle mancanti rispetto allo schema atteso
SELECT
    t.expected_table AS missing_table
FROM (
    SELECT 'users' AS expected_table UNION ALL
    SELECT 'news' UNION ALL
    SELECT 'matches' UNION ALL
    SELECT 'community_posts' UNION ALL
    SELECT 'community_post_media' UNION ALL
    SELECT 'community_post_reactions' UNION ALL
    SELECT 'community_post_comments' UNION ALL
    SELECT 'community_comment_reactions' UNION ALL
    SELECT 'community_followers' UNION ALL
    SELECT 'news_likes' UNION ALL
    SELECT 'news_comments' UNION ALL
    SELECT 'user_push_subscriptions'
) AS t
LEFT JOIN information_schema.tables ist
    ON ist.table_schema = DATABASE()
    AND ist.table_name = t.expected_table
WHERE ist.table_name IS NULL;

-- 3. Controllo colonne mancanti per tabella
SELECT
    ec.table_name,
    ec.column_name
FROM (
    SELECT 'users' AS table_name, 'id' AS column_name UNION ALL
    SELECT 'users', 'username' UNION ALL
    SELECT 'users', 'email' UNION ALL
    SELECT 'users', 'password_hash' UNION ALL
    SELECT 'users', 'badge' UNION ALL
    SELECT 'users', 'avatar_url' UNION ALL
    SELECT 'users', 'created_at' UNION ALL
    SELECT 'users', 'updated_at' UNION ALL

    SELECT 'news', 'id' UNION ALL
    SELECT 'news', 'title' UNION ALL
    SELECT 'news', 'slug' UNION ALL
    SELECT 'news', 'tag' UNION ALL
    SELECT 'news', 'excerpt' UNION ALL
    SELECT 'news', 'body' UNION ALL
    SELECT 'news', 'image_path' UNION ALL
    SELECT 'news', 'source_url' UNION ALL
    SELECT 'news', 'published_at' UNION ALL
    SELECT 'news', 'created_at' UNION ALL
    SELECT 'news', 'updated_at' UNION ALL

    SELECT 'matches', 'id' UNION ALL
    SELECT 'matches', 'external_id' UNION ALL
    SELECT 'matches', 'source' UNION ALL
    SELECT 'matches', 'competition' UNION ALL
    SELECT 'matches', 'opponent' UNION ALL
    SELECT 'matches', 'venue' UNION ALL
    SELECT 'matches', 'kickoff_at' UNION ALL
    SELECT 'matches', 'status' UNION ALL
    SELECT 'matches', 'broadcast' UNION ALL
    SELECT 'matches', 'created_at' UNION ALL
    SELECT 'matches', 'updated_at' UNION ALL

    SELECT 'community_posts', 'id' UNION ALL
    SELECT 'community_posts', 'user_id' UNION ALL
    SELECT 'community_posts', 'content' UNION ALL
    SELECT 'community_posts', 'content_type' UNION ALL
    SELECT 'community_posts', 'media_url' UNION ALL
    SELECT 'community_posts', 'poll_question' UNION ALL
    SELECT 'community_posts', 'poll_options' UNION ALL
    SELECT 'community_posts', 'status' UNION ALL
    SELECT 'community_posts', 'scheduled_for' UNION ALL
    SELECT 'community_posts', 'published_at' UNION ALL
    SELECT 'community_posts', 'created_at' UNION ALL
    SELECT 'community_posts', 'updated_at' UNION ALL

    SELECT 'community_post_media', 'id' UNION ALL
    SELECT 'community_post_media', 'post_id' UNION ALL
    SELECT 'community_post_media', 'file_path' UNION ALL
    SELECT 'community_post_media', 'mime_type' UNION ALL
    SELECT 'community_post_media', 'position' UNION ALL
    SELECT 'community_post_media', 'created_at' UNION ALL

    SELECT 'community_post_reactions', 'post_id' UNION ALL
    SELECT 'community_post_reactions', 'user_id' UNION ALL
    SELECT 'community_post_reactions', 'reaction_type' UNION ALL
    SELECT 'community_post_reactions', 'created_at' UNION ALL

    SELECT 'community_post_comments', 'id' UNION ALL
    SELECT 'community_post_comments', 'post_id' UNION ALL
    SELECT 'community_post_comments', 'user_id' UNION ALL
    SELECT 'community_post_comments', 'parent_comment_id' UNION ALL
    SELECT 'community_post_comments', 'content' UNION ALL
    SELECT 'community_post_comments', 'created_at' UNION ALL
    SELECT 'community_post_comments', 'updated_at' UNION ALL

    SELECT 'community_comment_reactions', 'comment_id' UNION ALL
    SELECT 'community_comment_reactions', 'user_id' UNION ALL
    SELECT 'community_comment_reactions', 'created_at' UNION ALL

    SELECT 'community_followers', 'id' UNION ALL
    SELECT 'community_followers', 'user_id' UNION ALL
    SELECT 'community_followers', 'follower_id' UNION ALL
    SELECT 'community_followers', 'created_at' UNION ALL

    SELECT 'news_likes', 'news_id' UNION ALL
    SELECT 'news_likes', 'user_id' UNION ALL
    SELECT 'news_likes', 'created_at' UNION ALL

    SELECT 'news_comments', 'id' UNION ALL
    SELECT 'news_comments', 'news_id' UNION ALL
    SELECT 'news_comments', 'user_id' UNION ALL
    SELECT 'news_comments', 'content' UNION ALL
    SELECT 'news_comments', 'created_at' UNION ALL
    SELECT 'news_comments', 'updated_at' UNION ALL

    SELECT 'user_push_subscriptions', 'id' UNION ALL
    SELECT 'user_push_subscriptions', 'user_id' UNION ALL
    SELECT 'user_push_subscriptions', 'endpoint' UNION ALL
    SELECT 'user_push_subscriptions', 'public_key' UNION ALL
    SELECT 'user_push_subscriptions', 'auth_token' UNION ALL
    SELECT 'user_push_subscriptions', 'content_encoding' UNION ALL
    SELECT 'user_push_subscriptions', 'device_name' UNION ALL
    SELECT 'user_push_subscriptions', 'user_agent' UNION ALL
    SELECT 'user_push_subscriptions', 'scope' UNION ALL
    SELECT 'user_push_subscriptions', 'created_at' UNION ALL
    SELECT 'user_push_subscriptions', 'updated_at'
) AS ec
LEFT JOIN information_schema.columns ic
    ON ic.table_schema = DATABASE()
    AND ic.table_name = ec.table_name
    AND ic.column_name = ec.column_name
WHERE ic.column_name IS NULL
ORDER BY ec.table_name, ec.column_name;

-- 4. Verifica della presenza dei vincoli di chiave primaria
SELECT
    t.table_name,
    CASE WHEN tc.constraint_name IS NULL THEN 'missing' ELSE 'ok' END AS primary_key_status
FROM (
    SELECT 'users' AS table_name UNION ALL
    SELECT 'news' UNION ALL
    SELECT 'matches' UNION ALL
    SELECT 'community_posts' UNION ALL
    SELECT 'community_post_media' UNION ALL
    SELECT 'community_post_reactions' UNION ALL
    SELECT 'community_post_comments' UNION ALL
    SELECT 'community_comment_reactions' UNION ALL
    SELECT 'community_followers' UNION ALL
    SELECT 'news_likes' UNION ALL
    SELECT 'news_comments' UNION ALL
    SELECT 'user_push_subscriptions'
) AS t
LEFT JOIN information_schema.table_constraints tc
    ON tc.table_schema = DATABASE()
    AND tc.table_name = t.table_name
    AND tc.constraint_type = 'PRIMARY KEY'
ORDER BY t.table_name;
