-- phpMyAdmin SQL Dump
-- version 6.0.0-dev+20251224.2c1a942e07
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 06, 2026 at 06:49 PM
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
-- Table structure for table `perm_account`
--

CREATE TABLE `perm_account` (
  `account_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `perm_account_group`
--

CREATE TABLE `perm_account_group` (
  `account_id` int NOT NULL,
  `group_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `perm_account_permission`
--

CREATE TABLE `perm_account_permission` (
  `account_id` int NOT NULL,
  `permission_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `perm_group`
--

CREATE TABLE `perm_group` (
  `group_id` int NOT NULL,
  `group_name` varchar(50) NOT NULL,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `perm_group_permission`
--

CREATE TABLE `perm_group_permission` (
  `group_id` int NOT NULL,
  `permission_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `perm_permission`
--

CREATE TABLE `perm_permission` (
  `permission_id` int NOT NULL,
  `permission_name` varchar(50) NOT NULL,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `perm_account`
--
ALTER TABLE `perm_account`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `perm_account_group`
--
ALTER TABLE `perm_account_group`
  ADD PRIMARY KEY (`account_id`,`group_id`),
  ADD KEY `fk_pag_group` (`group_id`);

--
-- Indexes for table `perm_account_permission`
--
ALTER TABLE `perm_account_permission`
  ADD PRIMARY KEY (`account_id`,`permission_id`),
  ADD KEY `fk_aperm_permission` (`permission_id`);

--
-- Indexes for table `perm_group`
--
ALTER TABLE `perm_group`
  ADD PRIMARY KEY (`group_id`),
  ADD UNIQUE KEY `group_name` (`group_name`);

--
-- Indexes for table `perm_group_permission`
--
ALTER TABLE `perm_group_permission`
  ADD PRIMARY KEY (`group_id`,`permission_id`),
  ADD KEY `fk_gperm_permission` (`permission_id`);

--
-- Indexes for table `perm_permission`
--
ALTER TABLE `perm_permission`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `permission_name` (`permission_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `perm_account`
--
ALTER TABLE `perm_account`
  MODIFY `account_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `perm_group`
--
ALTER TABLE `perm_group`
  MODIFY `group_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `perm_permission`
--
ALTER TABLE `perm_permission`
  MODIFY `permission_id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `perm_account_group`
--
ALTER TABLE `perm_account_group`
  ADD CONSTRAINT `fk_pag_account` FOREIGN KEY (`account_id`) REFERENCES `perm_account` (`account_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pag_group` FOREIGN KEY (`group_id`) REFERENCES `perm_group` (`group_id`) ON DELETE CASCADE;

--
-- Constraints for table `perm_account_permission`
--
ALTER TABLE `perm_account_permission`
  ADD CONSTRAINT `fk_aperm_account` FOREIGN KEY (`account_id`) REFERENCES `perm_account` (`account_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aperm_permission` FOREIGN KEY (`permission_id`) REFERENCES `perm_permission` (`permission_id`) ON DELETE CASCADE;

--
-- Constraints for table `perm_group_permission`
--
ALTER TABLE `perm_group_permission`
  ADD CONSTRAINT `fk_gperm_group` FOREIGN KEY (`group_id`) REFERENCES `perm_group` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_gperm_permission` FOREIGN KEY (`permission_id`) REFERENCES `perm_permission` (`permission_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
