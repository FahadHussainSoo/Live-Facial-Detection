-- =========================================================
-- Live Facial Authentication — Database Schema (Phase 2)
-- =========================================================
-- Compatible with MySQL 5.7+/8.x as shipped with XAMPP.
-- Run this file in phpMyAdmin (Import) or via the MySQL CLI:
--     mysql -u root -p < database.sql
--
-- This file supersedes the Phase 1 database/schema.sql file
-- (it recreates the `users` table with the additional columns
-- required for facial authentication, plus two new tables).
--
-- FIX (this version): tables are now dropped in CHILD-FIRST order
-- (authentication_logs, then face_authentication, then users).
-- The previous version dropped `users` first, which only worked
-- because SET FOREIGN_KEY_CHECKS=0 happened to be active for the
-- whole script — if any DROP statement was ever run on its own
-- (e.g. re-running a single highlighted line in the phpMyAdmin SQL
-- tab), MySQL would correctly refuse with error #1451, since
-- face_authentication/authentication_logs still had foreign keys
-- pointing at users. Dropping in child-first order means this file
-- is now safe to run as a whole script OR in pieces.
--
-- Security notes:
--   * Passwords are never stored in plain text — only a hash
--     (bcrypt/argon2, produced by PHP's password_hash()) is stored.
--   * Face embeddings are stored as encoded/serialized numeric
--     vectors, never as raw images.
--   * No sample/fake user or face data is inserted by this script.
-- =========================================================

CREATE DATABASE IF NOT EXISTS face_auth_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE face_auth_db;

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------
-- Drop existing tables, CHILD tables first (both reference
-- `users` via a foreign key), THEN the parent `users` table.
-- -------------------------------------------------------
DROP TABLE IF EXISTS authentication_logs;
DROP TABLE IF EXISTS face_authentication;
DROP TABLE IF EXISTS users;

-- -------------------------------------------------------
-- Table: users
-- Core account/profile information.
-- -------------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name       VARCHAR(150) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    phone           VARCHAR(20)  NULL,
    password_hash   VARCHAR(255) NOT NULL,
    face_registered TINYINT(1)   NOT NULL DEFAULT 0,
    status          ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_status (status),
    KEY idx_users_face_registered (face_registered)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: face_authentication
-- Stores one encoded face embedding per user (no raw images).
-- -------------------------------------------------------
CREATE TABLE face_authentication (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    face_embedding  LONGTEXT NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_face_auth_user_id (user_id),
    CONSTRAINT fk_face_auth_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: authentication_logs
-- Audit trail for every login / verification attempt.
-- user_id is nullable + ON DELETE SET NULL so the audit
-- history survives even if the related account is deleted.
-- -------------------------------------------------------
CREATE TABLE authentication_logs (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id              INT UNSIGNED NULL,
    authentication_type  ENUM('password', 'face', 'password_face') NOT NULL,
    status               ENUM('success', 'failed') NOT NULL,
    ip_address           VARCHAR(45) NOT NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auth_logs_user_id (user_id),
    KEY idx_auth_logs_status (status),
    KEY idx_auth_logs_created_at (created_at),
    CONSTRAINT fk_auth_logs_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
