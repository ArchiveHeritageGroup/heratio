-- ahg-articles install: articles/news with downloadable guides & templates and
-- anonymous comments. Idempotent (CREATE TABLE IF NOT EXISTS). blog_post must
-- be created before its children (foreign keys).

CREATE TABLE IF NOT EXISTS `blog_post` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` mediumtext COLLATE utf8mb4_unicode_ci,
  `attachments_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `article_group` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `view_count` int unsigned NOT NULL DEFAULT '0',
  `published_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blog_post_slug` (`slug`),
  KEY `idx_blog_post_status` (`status`,`published_at`),
  KEY `idx_blog_post_group` (`article_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blog_attachment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `blog_post_id` bigint unsigned NOT NULL,
  `kind` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'guide',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_blog_attachment_post` (`blog_post_id`,`kind`,`sort_order`),
  CONSTRAINT `fk_blog_attachment_post` FOREIGN KEY (`blog_post_id`) REFERENCES `blog_post` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blog_comment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `blog_post_id` bigint unsigned NOT NULL,
  `author_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_blog_comment_post` (`blog_post_id`,`status`),
  KEY `idx_blog_comment_created` (`created_at`),
  CONSTRAINT `fk_blog_comment_post` FOREIGN KEY (`blog_post_id`) REFERENCES `blog_post` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
