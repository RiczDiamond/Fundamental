-- phpMyAdmin SQL Dump
-- version 6.0.0-dev+20251224.2c1a942e07
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 06, 2026 at 06:47 PM
-- Server version: 8.4.3
-- PHP Version: 8.5.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fundamental`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `display_name` varchar(150) DEFAULT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `role` varchar(50) DEFAULT 'user',
  `status` varchar(50) DEFAULT 'active',
  `deletion_requested_at` datetime DEFAULT NULL,
  `banned_until` datetime DEFAULT NULL,
  `ban_reason` text,
  `email_verified` tinyint(1) DEFAULT '0',
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `username`, `first_name`, `last_name`, `display_name`, `gender`, `birth_date`, `role`, `status`, `deletion_requested_at`, `banned_until`, `ban_reason`, `email_verified`, `last_login`, `last_ip`, `created_at`, `updated_at`) VALUES
(1, 'jjrmol2005@gmail.com', '$2y$12$4PM0wXtFbJBeUX11QdXmY..YfGxhNCPuhP9o.GBSk4.awUd1AIfLm', 'riczdiamond', 'Ricardo', 'Mol', 'Ricardo Mol', NULL, NULL, 'admin', 'active', NULL, NULL, NULL, 1, NULL, NULL, '2026-03-02 14:35:55', '2026-03-06 18:30:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
