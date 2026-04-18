-- ============================================================
-- Lost Knowledge - MySQL Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS lost_knowledge
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE lost_knowledge;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username    VARCHAR(50)     NOT NULL UNIQUE,
    email       VARCHAR(150)    NOT NULL UNIQUE,
    phone       VARCHAR(15)     DEFAULT NULL UNIQUE,
    google_id   VARCHAR(255)    DEFAULT NULL,
    password    VARCHAR(255)    DEFAULT NULL,          -- bcrypt hash (NULL for Google OAuth users)
    role        ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_email     (email),
    INDEX idx_username  (username),
    INDEX idx_phone     (phone),
    INDEX idx_google_id (google_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: categories
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name        VARCHAR(80)     NOT NULL UNIQUE,
    slug        VARCHAR(80)     NOT NULL UNIQUE,
    description TEXT,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: knowledge_entries
-- ============================================================
CREATE TABLE IF NOT EXISTS knowledge_entries (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED    NOT NULL,
    category_id     INT UNSIGNED,
    title           VARCHAR(200)    NOT NULL,
    summary         VARCHAR(400)    NOT NULL,
    body            TEXT            NOT NULL,
    region          VARCHAR(100),
    era             VARCHAR(100),
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    image_path      VARCHAR(255),
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY fk_entry_user     (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY fk_entry_category (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_status     (status),
    INDEX idx_user       (user_id),
    INDEX idx_category   (category_id),
    FULLTEXT idx_search  (title, summary, body)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: votes
-- ============================================================
CREATE TABLE IF NOT EXISTS votes (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    entry_id    INT UNSIGNED    NOT NULL,
    user_id     INT UNSIGNED    NOT NULL,
    vote_type   ENUM('up','down') NOT NULL,
    voted_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_entry (user_id, entry_id),   -- one vote per user per entry
    FOREIGN KEY fk_vote_entry (entry_id) REFERENCES knowledge_entries(id) ON DELETE CASCADE,
    FOREIGN KEY fk_vote_user  (user_id)  REFERENCES users(id)             ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: comments  (bonus relational table)
-- ============================================================
CREATE TABLE IF NOT EXISTS comments (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    entry_id    INT UNSIGNED    NOT NULL,
    user_id     INT UNSIGNED    NOT NULL,
    body        TEXT            NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY fk_comment_entry (entry_id) REFERENCES knowledge_entries(id) ON DELETE CASCADE,
    FOREIGN KEY fk_comment_user  (user_id)  REFERENCES users(id)             ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED: Default categories
-- ============================================================
INSERT INTO categories (name, slug, description) VALUES
('Ancient Crafts',      'ancient-crafts',     'Forgotten artisanal techniques and handcraft traditions'),
('Oral Traditions',     'oral-traditions',    'Myths, epics, and knowledge passed through storytelling'),
('Herbal Medicine',     'herbal-medicine',    'Lost remedies, plant lore, and healing practices'),
('Agricultural Lore',   'agricultural-lore',  'Extinct farming methods and seed knowledge'),
('Architecture',        'architecture',       'Vanished building techniques and sacred geometry'),
('Navigation',          'navigation',         'Lost star maps, ocean reading, and wayfinding arts'),
('Language & Scripts',  'language-scripts',   'Dead languages, lost alphabets, and forgotten dialects'),
('Ritual & Ceremony',   'ritual-ceremony',    'Sacred rites and ceremonial traditions no longer practiced');

-- ============================================================
-- TABLE: user_tokens (for Remember Me functionality)
-- ============================================================
CREATE TABLE IF NOT EXISTS user_tokens (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NOT NULL,
    token       VARCHAR(64)     NOT NULL UNIQUE,
    expires     DATETIME        NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY fk_token_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_token (user_id, token)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: password_resets (for Forgot Password functionality)
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NOT NULL,
    token       VARCHAR(64)     NOT NULL UNIQUE,
    expires_at  DATETIME        NOT NULL,
    used        TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY fk_reset_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_reset_token (token),
    INDEX idx_user_expires (user_id, expires_at)
) ENGINE=InnoDB;

-- ============================================================
-- SEED: Default admin user  (password: Admin@1234)
-- ============================================================
INSERT INTO users (username, email, phone, password, role) VALUES
('admin', 'admin@lostknowledge.local', NULL,
 '$2y$12$eImiTXuWVxfM37uY4JANjO7lRKxwf5VVbQ9C9wOWdHs3wN1VZHfJO',
 'admin');

