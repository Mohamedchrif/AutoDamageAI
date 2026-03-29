-- AutoDamg Database Schema (source of truth for fresh installs)
-- Import once via phpMyAdmin or: mysql < autodamg_db.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `autodamg_db`
--
CREATE DATABASE IF NOT EXISTS `autodamg_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `autodamg_db`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(80) NOT NULL,
  `email` varchar(120) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `blocked` tinyint(1) DEFAULT 0,
  `blocked_reason` varchar(255) DEFAULT NULL,
  `blocked_at` datetime DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(256) NOT NULL,
  `profile_pic` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analyses`
--

CREATE TABLE IF NOT EXISTS `analyses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `filename` varchar(256) NOT NULL,
  `original_filename` varchar(256) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  -- result_json.original_image: user photo as data URI (base64). Legacy rows may use uploads/... paths.
  `result_json` longtext NOT NULL,
  -- Annotated image as data URI, or legacy path uploads/annotated/...
  `annotated_image` longtext DEFAULT NULL,
  `cost_min` decimal(10,2) DEFAULT 0.00,
  `cost_max` decimal(10,2) DEFAULT 0.00,
  `total_detections` int(11) DEFAULT 0,
  `is_undamaged` tinyint(1) DEFAULT 0,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_analyses_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
