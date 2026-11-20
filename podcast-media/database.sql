
-- ============================================================
-- Курсовой проект: Медиатека для подкастов/аудио
-- СУБД: MySQL 8.0.16+ / совместимая версия MariaDB
-- Кодировка: UTF-8
-- ============================================================

CREATE DATABASE IF NOT EXISTS podcast_media
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE podcast_media;

SET NAMES utf8mb4;


-- ============================================================
-- 1. Пользователи и роли
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    login VARCHAR(50) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,

    role ENUM('listener', 'editor', 'admin')
        NOT NULL DEFAULT 'listener',

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_login (login),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_active (role, is_active)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2. Категории аудиоматериалов
-- ============================================================

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description TEXT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 3. Подкасты
-- Одна запись представляет тематическую серию.
-- ============================================================

CREATE TABLE IF NOT EXISTS podcasts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    cover_path VARCHAR(500) NULL,

    category_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,

    status ENUM('draft', 'published', 'hidden')
        NOT NULL DEFAULT 'draft',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_podcasts_category_status (category_id, status),
    KEY idx_podcasts_status_created (status, created_at),

    CONSTRAINT fk_podcasts_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_podcasts_creator
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 4. Аудиоматериалы и выпуски подкастов
-- podcast_id = NULL: самостоятельный аудиоматериал.
-- ============================================================

CREATE TABLE IF NOT EXISTS tracks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    podcast_id BIGINT UNSIGNED NULL,
    episode_number INT UNSIGNED NULL,

    title VARCHAR(200) NOT NULL,
    description TEXT NULL,

    file_path VARCHAR(500) NOT NULL,
    cover_path VARCHAR(500) NULL,

    mime_type VARCHAR(100) NOT NULL,
    file_size_bytes BIGINT UNSIGNED NOT NULL,
    duration_seconds INT UNSIGNED NULL,

    category_id BIGINT UNSIGNED NULL,
    uploader_id BIGINT UNSIGNED NULL,

    status ENUM('draft', 'published', 'hidden')
        NOT NULL DEFAULT 'draft',

    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_tracks_podcast_episode (podcast_id, episode_number),
    KEY idx_tracks_category_status (category_id, status),
    KEY idx_tracks_status_uploaded (status, uploaded_at),
    KEY idx_tracks_title (title),

    CONSTRAINT fk_tracks_podcast
        FOREIGN KEY (podcast_id)
        REFERENCES podcasts(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_tracks_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_tracks_uploader
        FOREIGN KEY (uploader_id)
        REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 5. Справочник тегов
-- ============================================================

CREATE TABLE IF NOT EXISTS tags (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tag_name VARCHAR(80) NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_tags_name (tag_name)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 6. Связь аудиоматериалов с тегами
-- Связь многие-ко-многим.
-- ============================================================

CREATE TABLE IF NOT EXISTS track_tags (
    track_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (track_id, tag_id),
    KEY idx_track_tags_tag (tag_id, track_id),

    CONSTRAINT fk_track_tags_track
        FOREIGN KEY (track_id)
        REFERENCES tracks(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_track_tags_tag
        FOREIGN KEY (tag_id)
        REFERENCES tags(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 7. Пользовательские плейлисты
-- ============================================================

CREATE TABLE IF NOT EXISTS playlists (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,

    name VARCHAR(100) NOT NULL,
    description VARCHAR(500) NULL,

    is_public TINYINT(1) NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_playlists_user_name (user_id, name),
    KEY idx_playlists_user_created (user_id, created_at),

    CONSTRAINT fk_playlists_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 8. Состав плейлистов и порядок воспроизведения
-- ============================================================

CREATE TABLE IF NOT EXISTS playlist_tracks (
    playlist_id BIGINT UNSIGNED NOT NULL,
    track_id BIGINT UNSIGNED NOT NULL,

    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (playlist_id, track_id),

    KEY idx_playlist_tracks_order (playlist_id, sort_order),
    KEY idx_playlist_tracks_track (track_id),

    CONSTRAINT fk_playlist_tracks_playlist
        FOREIGN KEY (playlist_id)
        REFERENCES playlists(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_playlist_tracks_track
        FOREIGN KEY (track_id)
        REFERENCES tracks(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 9. Статистика прослушиваний
-- Одна запись = одно событие прослушивания.
-- ============================================================

CREATE TABLE IF NOT EXISTS statistics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    track_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,

    listened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    listened_seconds INT UNSIGNED NOT NULL DEFAULT 0,

    completed TINYINT(1) NOT NULL DEFAULT 0,

    PRIMARY KEY (id),

    KEY idx_statistics_track_date (track_id, listened_at),
    KEY idx_statistics_user_date (user_id, listened_at),
    KEY idx_statistics_date (listened_at),

    CONSTRAINT fk_statistics_track
        FOREIGN KEY (track_id)
        REFERENCES tracks(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_statistics_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 10. Сохранение позиции воспроизведения
-- Одна запись на пользователя и аудиоматериал.
-- ============================================================

CREATE TABLE IF NOT EXISTS playback_progress (
    user_id BIGINT UNSIGNED NOT NULL,
    track_id BIGINT UNSIGNED NOT NULL,

    position_seconds INT UNSIGNED NOT NULL DEFAULT 0,

    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, track_id),
    KEY idx_playback_progress_updated (updated_at),

    CONSTRAINT fk_playback_progress_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_playback_progress_track
        FOREIGN KEY (track_id)
        REFERENCES tracks(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 11. Отзывы и оценки
-- ============================================================

CREATE TABLE IF NOT EXISTS reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id BIGINT UNSIGNED NOT NULL,
    track_id BIGINT UNSIGNED NOT NULL,

    rating TINYINT UNSIGNED NOT NULL,
    review_text TEXT NULL,

    status ENUM('pending', 'published', 'rejected')
        NOT NULL DEFAULT 'pending',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_reviews_user_track (user_id, track_id),
    KEY idx_reviews_track_status (track_id, status, created_at),

    CONSTRAINT chk_reviews_rating
        CHECK (rating BETWEEN 1 AND 5),

    CONSTRAINT fk_reviews_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_reviews_track
        FOREIGN KEY (track_id)
        REFERENCES tracks(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 12. Сообщения формы обратной связи
-- ============================================================

CREATE TABLE IF NOT EXISTS feedback_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id BIGINT UNSIGNED NULL,

    name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    subject VARCHAR(200) NULL,
    message TEXT NOT NULL,

    status ENUM('new', 'in_progress', 'closed')
        NOT NULL DEFAULT 'new',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_feedback_status_date (status, created_at),
    KEY idx_feedback_email_date (email, created_at),

    CONSTRAINT fk_feedback_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Проверка количества созданных таблиц
-- Ожидаемый результат: 12
-- ============================================================

SELECT COUNT(*) AS tables_count
FROM information_schema.tables
WHERE table_schema = 'podcast_media'
  AND table_type = 'BASE TABLE';
