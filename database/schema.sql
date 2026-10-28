-- =========================================================
-- Live Facial Authentication — Database Schema (Phase 1)
-- =========================================================
-- Run this in phpMyAdmin or the MySQL CLI to create the database.
-- Facial data tables will be added in a later phase.

CREATE DATABASE IF NOT EXISTS face_auth_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE face_auth_db;

-- Basic users table (authentication logic to be implemented later)
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
