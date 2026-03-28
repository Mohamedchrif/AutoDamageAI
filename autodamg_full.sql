-- ============================================================
--  AutoDamg Database – Full Schema (with all updates)
--  Generated: 2026-03-27
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ── Drop & recreate the database ──────────────────────────────────────────────
DROP DATABASE IF EXISTS `autodamg_db`;
CREATE DATABASE `autodamg_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE `autodamg_db`;

-- ─────────────────────────────────────────────────────────────────────────────
--  TABLE: users
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE `users` (
    `id`             INT(11)              NOT NULL AUTO_INCREMENT,
    `username`       VARCHAR(80)          NOT NULL,
    `email`          VARCHAR(120)         NOT NULL,
    `role`           ENUM('user','admin') NOT NULL DEFAULT 'user',
    `blocked`        TINYINT(1)           NOT NULL DEFAULT 0,
    `blocked_reason` VARCHAR(255)                  DEFAULT NULL,
    `blocked_at`     DATETIME                      DEFAULT NULL,
    `password_hash`  VARCHAR(255)         NOT NULL,
    `phone`          VARCHAR(20)                   DEFAULT NULL,
    `profile_picture` VARCHAR(255)                 DEFAULT NULL,
    `profile_pic`    LONGTEXT                      DEFAULT NULL,
    `created_at`     TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────────────────────
--  TABLE: analyses
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE `analyses` (
    `id`                INT(11)      NOT NULL AUTO_INCREMENT,
    `user_id`           INT(11)               DEFAULT NULL,
    `filename`          VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255)          DEFAULT NULL,
    `file_size`         INT(11)               DEFAULT NULL,
    `result_json`       LONGTEXT     NOT NULL,
    `annotated_image`   TEXT                  DEFAULT NULL,
    `cost_min`          FLOAT                 DEFAULT NULL,
    `cost_max`          FLOAT                 DEFAULT NULL,
    `total_detections`  INT(11)               DEFAULT NULL,
    `is_undamaged`      TINYINT(1)            DEFAULT 0,
    `timestamp`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_user` (`user_id`),
    CONSTRAINT `fk_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────────────────────
--  AUTO_INCREMENT starting points
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `users`    MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `analyses` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

COMMIT;
