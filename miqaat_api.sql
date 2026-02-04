-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Feb 03, 2026 at 07:57 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `miqaat_api`
--

-- --------------------------------------------------------

--
-- Table structure for table `census`
--

CREATE TABLE `census` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `its_id` varchar(255) NOT NULL,
  `hof_id` varchar(255) NOT NULL,
  `father_its` varchar(255) DEFAULT NULL,
  `mother_its` varchar(255) DEFAULT NULL,
  `spouse_its` varchar(255) DEFAULT NULL,
  `sabeel` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `arabic_name` varchar(255) DEFAULT NULL,
  `age` int(10) UNSIGNED DEFAULT NULL,
  `gender` varchar(255) DEFAULT NULL,
  `misaq` varchar(255) DEFAULT NULL,
  `marital_status` varchar(255) DEFAULT NULL,
  `blood_group` varchar(255) DEFAULT NULL,
  `mobile` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `pincode` varchar(255) DEFAULT NULL,
  `mohalla` varchar(255) DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `jamaat` varchar(255) DEFAULT NULL,
  `jamiat` varchar(255) DEFAULT NULL,
  `pwd` varchar(255) DEFAULT NULL,
  `synced` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `currency_conversions`
--

CREATE TABLE `currency_conversions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `from_currency` varchar(3) NOT NULL,
  `to_currency` varchar(3) NOT NULL DEFAULT 'INR',
  `rate` decimal(12,6) NOT NULL,
  `effective_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `source` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `miqaat_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `miqaats`
--

CREATE TABLE `miqaats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `active_status` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `miqaat_checks`
--

CREATE TABLE `miqaat_checks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `its_id` varchar(255) NOT NULL,
  `mcd_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_cleared` tinyint(1) NOT NULL DEFAULT 0,
  `cleared_by_its` varchar(255) DEFAULT NULL,
  `cleared_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `miqaat_check_definitions`
--

CREATE TABLE `miqaat_check_definitions` (
  `mcd_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `miqaat_id` bigint(20) UNSIGNED NOT NULL,
  `user_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_definitions`
--

CREATE TABLE `payment_definitions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sharaf_definition_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `user_type` varchar(255) NOT NULL DEFAULT 'Finance',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sharafs`
--

CREATE TABLE `sharafs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sharaf_definition_id` bigint(20) UNSIGNED NOT NULL,
  `rank` int(11) NOT NULL,
  `capacity` int(11) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `hof_its` varchar(255) NOT NULL,
  `token` varchar(50) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sharaf_clearances`
--

CREATE TABLE `sharaf_clearances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sharaf_id` bigint(20) UNSIGNED NOT NULL,
  `hof_its` varchar(255) NOT NULL,
  `is_cleared` tinyint(1) NOT NULL DEFAULT 0,
  `cleared_by_its` varchar(255) DEFAULT NULL,
  `cleared_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sharaf_definitions`
--

CREATE TABLE `sharaf_definitions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `sharaf_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `key` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sharaf_members`
--

CREATE TABLE `sharaf_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sharaf_id` bigint(20) UNSIGNED NOT NULL,
  `sharaf_position_id` bigint(20) UNSIGNED NOT NULL,
  `its_id` varchar(255) NOT NULL,
  `sp_keyno` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `najwa` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sharaf_payments`
--

CREATE TABLE `sharaf_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sharaf_id` bigint(20) UNSIGNED NOT NULL,
  `payment_definition_id` bigint(20) UNSIGNED NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` tinyint(4) NOT NULL DEFAULT 0,
  `payment_currency` varchar(3) NOT NULL DEFAULT 'LKR',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sharaf_positions`
--

CREATE TABLE `sharaf_positions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sharaf_definition_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `capacity` int(11) DEFAULT NULL,
  `order` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sharaf_types`
--

CREATE TABLE `sharaf_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `its_no` varchar(255) DEFAULT NULL,
  `user_type` enum('BS','Admin','Help Desk','Anjuman','Finance','Follow Up') DEFAULT 'BS',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wajebaat`
--

CREATE TABLE `wajebaat` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `miqaat_id` bigint(20) UNSIGNED NOT NULL,
  `its_id` varchar(255) NOT NULL,
  `wg_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'LKR',
  `conversion_rate` decimal(12,6) NOT NULL DEFAULT 1.000000,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `wc_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_isolated` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wajebaat_groups`
--

CREATE TABLE `wajebaat_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `wg_id` bigint(20) UNSIGNED NOT NULL,
  `group_name` varchar(255) DEFAULT NULL,
  `group_type` enum('business_grouping','personal_grouping','organization') DEFAULT NULL,
  `miqaat_id` bigint(20) UNSIGNED NOT NULL,
  `master_its` varchar(255) NOT NULL,
  `its_id` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `waj_categories`
--

CREATE TABLE `waj_categories` (
  `wc_id` bigint(20) UNSIGNED NOT NULL,
  `miqaat_id` bigint(20) UNSIGNED NOT NULL,
  `currency` varchar(3) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `low_bar` decimal(10,2) NOT NULL,
  `upper_bar` decimal(10,2) DEFAULT NULL,
  `hex_color` varchar(7) DEFAULT NULL,
  `order` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `census`
--
ALTER TABLE `census`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `census_its_id_unique` (`its_id`),
  ADD KEY `census_hof_id_index` (`hof_id`);

--
-- Indexes for table `currency_conversions`
--
ALTER TABLE `currency_conversions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cc_from_to_date_unique` (`from_currency`,`to_currency`,`effective_date`),
  ADD KEY `cc_from_to_date_idx` (`from_currency`,`to_currency`,`effective_date`),
  ADD KEY `cc_from_to_active_idx` (`from_currency`,`to_currency`,`is_active`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `events_miqaat_id_index` (`miqaat_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `miqaats`
--
ALTER TABLE `miqaats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `miqaat_checks`
--
ALTER TABLE `miqaat_checks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `miqaat_checks_its_id_mcd_id_unique` (`its_id`,`mcd_id`),
  ADD KEY `miqaat_checks_its_id_index` (`its_id`),
  ADD KEY `miqaat_checks_mcd_id_index` (`mcd_id`);

--
-- Indexes for table `miqaat_check_definitions`
--
ALTER TABLE `miqaat_check_definitions`
  ADD PRIMARY KEY (`mcd_id`),
  ADD UNIQUE KEY `miqaat_check_definitions_name_miqaat_id_unique` (`name`,`miqaat_id`),
  ADD KEY `miqaat_check_definitions_miqaat_id_index` (`miqaat_id`);

--
-- Indexes for table `payment_definitions`
--
ALTER TABLE `payment_definitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_definitions_sharaf_definition_id_name_unique` (`sharaf_definition_id`,`name`),
  ADD KEY `payment_definitions_sharaf_definition_id_index` (`sharaf_definition_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `sharafs`
--
ALTER TABLE `sharafs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sharafs_sharaf_definition_id_rank_unique` (`sharaf_definition_id`,`rank`),
  ADD UNIQUE KEY `sharafs_token_unique` (`token`),
  ADD KEY `sharafs_sharaf_definition_id_index` (`sharaf_definition_id`),
  ADD KEY `sharafs_hof_its_index` (`hof_its`);

--
-- Indexes for table `sharaf_clearances`
--
ALTER TABLE `sharaf_clearances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sharaf_clearances_sharaf_id_hof_its_unique` (`sharaf_id`,`hof_its`),
  ADD KEY `sharaf_clearances_sharaf_id_index` (`sharaf_id`),
  ADD KEY `sharaf_clearances_hof_its_index` (`hof_its`);

--
-- Indexes for table `sharaf_definitions`
--
ALTER TABLE `sharaf_definitions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sharaf_definitions_event_id_index` (`event_id`),
  ADD KEY `sharaf_definitions_sharaf_type_id_index` (`sharaf_type_id`);

--
-- Indexes for table `sharaf_members`
--
ALTER TABLE `sharaf_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sharaf_members_sharaf_id_its_id_unique` (`sharaf_id`,`its_id`),
  ADD KEY `sharaf_members_sharaf_id_index` (`sharaf_id`),
  ADD KEY `sharaf_members_sharaf_position_id_index` (`sharaf_position_id`),
  ADD KEY `sharaf_members_its_id_index` (`its_id`),
  ADD KEY `idx_sharaf_members_ranking` (`sharaf_id`,`sharaf_position_id`,`sp_keyno`);

--
-- Indexes for table `sharaf_payments`
--
ALTER TABLE `sharaf_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sharaf_payments_sharaf_id_payment_definition_id_unique` (`sharaf_id`,`payment_definition_id`),
  ADD KEY `sharaf_payments_sharaf_id_index` (`sharaf_id`),
  ADD KEY `sharaf_payments_payment_definition_id_index` (`payment_definition_id`);

--
-- Indexes for table `sharaf_positions`
--
ALTER TABLE `sharaf_positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sharaf_positions_sharaf_definition_id_name_unique` (`sharaf_definition_id`,`name`),
  ADD KEY `sharaf_positions_sharaf_definition_id_index` (`sharaf_definition_id`);

--
-- Indexes for table `sharaf_types`
--
ALTER TABLE `sharaf_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_its_no_unique` (`its_no`);

--
-- Indexes for table `wajebaat`
--
ALTER TABLE `wajebaat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wajebaat_miqaat_id_its_id_wg_id_unique` (`miqaat_id`,`its_id`,`wg_id`),
  ADD KEY `wajebaat_miqaat_id_wg_id_index` (`miqaat_id`,`wg_id`),
  ADD KEY `wajebaat_wc_id_foreign` (`wc_id`),
  ADD KEY `wajebaat_its_id_index` (`its_id`),
  ADD KEY `wajebaat_wg_id_index` (`wg_id`),
  ADD KEY `wajebaat_currency_index` (`currency`);

--
-- Indexes for table `wajebaat_groups`
--
ALTER TABLE `wajebaat_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wajebaat_groups_miqaat_id_its_id_unique` (`miqaat_id`,`its_id`),
  ADD UNIQUE KEY `wajebaat_groups_miqaat_id_wg_id_its_id_unique` (`miqaat_id`,`wg_id`,`its_id`),
  ADD KEY `wajebaat_groups_miqaat_id_wg_id_index` (`miqaat_id`,`wg_id`),
  ADD KEY `wajebaat_groups_miqaat_id_master_its_index` (`miqaat_id`,`master_its`),
  ADD KEY `wajebaat_groups_master_its_index` (`master_its`),
  ADD KEY `wajebaat_groups_its_id_index` (`its_id`);

--
-- Indexes for table `waj_categories`
--
ALTER TABLE `waj_categories`
  ADD PRIMARY KEY (`wc_id`),
  ADD UNIQUE KEY `waj_categories_miqaat_id_name_unique` (`miqaat_id`,`name`),
  ADD KEY `waj_categories_miqaat_id_index` (`miqaat_id`),
  ADD KEY `waj_categories_miqaat_id_low_bar_index` (`miqaat_id`,`low_bar`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `census`
--
ALTER TABLE `census`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currency_conversions`
--
ALTER TABLE `currency_conversions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `miqaats`
--
ALTER TABLE `miqaats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `miqaat_checks`
--
ALTER TABLE `miqaat_checks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `miqaat_check_definitions`
--
ALTER TABLE `miqaat_check_definitions`
  MODIFY `mcd_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_definitions`
--
ALTER TABLE `payment_definitions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sharafs`
--
ALTER TABLE `sharafs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sharaf_clearances`
--
ALTER TABLE `sharaf_clearances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sharaf_definitions`
--
ALTER TABLE `sharaf_definitions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sharaf_members`
--
ALTER TABLE `sharaf_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sharaf_payments`
--
ALTER TABLE `sharaf_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sharaf_positions`
--
ALTER TABLE `sharaf_positions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sharaf_types`
--
ALTER TABLE `sharaf_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wajebaat`
--
ALTER TABLE `wajebaat`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wajebaat_groups`
--
ALTER TABLE `wajebaat_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `waj_categories`
--
ALTER TABLE `waj_categories`
  MODIFY `wc_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_miqaat_id_foreign` FOREIGN KEY (`miqaat_id`) REFERENCES `miqaats` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `miqaat_checks`
--
ALTER TABLE `miqaat_checks`
  ADD CONSTRAINT `miqaat_checks_mcd_id_foreign` FOREIGN KEY (`mcd_id`) REFERENCES `miqaat_check_definitions` (`mcd_id`) ON DELETE SET NULL;

--
-- Constraints for table `miqaat_check_definitions`
--
ALTER TABLE `miqaat_check_definitions`
  ADD CONSTRAINT `miqaat_check_definitions_miqaat_id_foreign` FOREIGN KEY (`miqaat_id`) REFERENCES `miqaats` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_definitions`
--
ALTER TABLE `payment_definitions`
  ADD CONSTRAINT `payment_definitions_sharaf_definition_id_foreign` FOREIGN KEY (`sharaf_definition_id`) REFERENCES `sharaf_definitions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sharafs`
--
ALTER TABLE `sharafs`
  ADD CONSTRAINT `sharafs_sharaf_definition_id_foreign` FOREIGN KEY (`sharaf_definition_id`) REFERENCES `sharaf_definitions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sharaf_clearances`
--
ALTER TABLE `sharaf_clearances`
  ADD CONSTRAINT `sharaf_clearances_sharaf_id_foreign` FOREIGN KEY (`sharaf_id`) REFERENCES `sharafs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sharaf_definitions`
--
ALTER TABLE `sharaf_definitions`
  ADD CONSTRAINT `sharaf_definitions_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sharaf_definitions_sharaf_type_id_foreign` FOREIGN KEY (`sharaf_type_id`) REFERENCES `sharaf_types` (`id`);

--
-- Constraints for table `sharaf_members`
--
ALTER TABLE `sharaf_members`
  ADD CONSTRAINT `sharaf_members_sharaf_id_foreign` FOREIGN KEY (`sharaf_id`) REFERENCES `sharafs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sharaf_members_sharaf_position_id_foreign` FOREIGN KEY (`sharaf_position_id`) REFERENCES `sharaf_positions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sharaf_payments`
--
ALTER TABLE `sharaf_payments`
  ADD CONSTRAINT `sharaf_payments_payment_definition_id_foreign` FOREIGN KEY (`payment_definition_id`) REFERENCES `payment_definitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sharaf_payments_sharaf_id_foreign` FOREIGN KEY (`sharaf_id`) REFERENCES `sharafs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sharaf_positions`
--
ALTER TABLE `sharaf_positions`
  ADD CONSTRAINT `sharaf_positions_sharaf_definition_id_foreign` FOREIGN KEY (`sharaf_definition_id`) REFERENCES `sharaf_definitions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wajebaat`
--
ALTER TABLE `wajebaat`
  ADD CONSTRAINT `wajebaat_miqaat_id_foreign` FOREIGN KEY (`miqaat_id`) REFERENCES `miqaats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wajebaat_wc_id_foreign` FOREIGN KEY (`wc_id`) REFERENCES `waj_categories` (`wc_id`) ON DELETE SET NULL;

--
-- Constraints for table `wajebaat_groups`
--
ALTER TABLE `wajebaat_groups`
  ADD CONSTRAINT `wajebaat_groups_miqaat_id_foreign` FOREIGN KEY (`miqaat_id`) REFERENCES `miqaats` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `waj_categories`
--
ALTER TABLE `waj_categories`
  ADD CONSTRAINT `waj_categories_miqaat_id_foreign` FOREIGN KEY (`miqaat_id`) REFERENCES `miqaats` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
