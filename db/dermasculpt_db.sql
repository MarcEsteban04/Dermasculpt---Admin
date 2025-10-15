-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2025 at 10:23 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dermasculpt_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_skin_scans`
--

CREATE TABLE `ai_skin_scans` (
  `scan_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `ai_scan_image_url` varchar(255) NOT NULL,
  `ai_results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_results`)),
  `manual_scan_notes` text DEFAULT NULL,
  `comparison_notes` text DEFAULT NULL,
  `scan_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `patient_name` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `dermatologist_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `reason_for_appointment` text DEFAULT NULL,
  `status` enum('Scheduled','Completed','Cancelled','Pending') NOT NULL DEFAULT 'Pending',
  `is_notified` tinyint(1) NOT NULL DEFAULT 0,
  `user_notes` text DEFAULT NULL,
  `image_paths` text DEFAULT NULL,
  `dermatologist_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `user_id`, `patient_name`, `email`, `phone_number`, `dermatologist_id`, `appointment_date`, `appointment_time`, `reason_for_appointment`, `status`, `is_notified`, `user_notes`, `image_paths`, `dermatologist_notes`, `created_at`, `updated_at`) VALUES
(2, 2, 'Princess Elfa', 'princesselfa07@gmail.com', '09690608014', 1, '2025-09-23', '14:00:00', 'test', 'Cancelled', 0, 'test', 'uploads/appointments/appt_68cc5126754480.98361456.jpg', NULL, '2025-09-18 18:36:22', '2025-10-01 15:00:10'),
(3, 2, 'Princess Elfa', 'princesselfa07@gmail.com', '09690608014', 1, '2025-09-23', '14:30:00', 'test', 'Completed', 0, 'test', NULL, NULL, '2025-09-20 12:52:36', '2025-10-01 17:12:21'),
(17, 1, 'Marc Esteban', 'marcdelacruzesteban@gmail.com', '09690608014', 1, '2025-10-10', '11:30:00', 'Consultation - Follow Up', 'Scheduled', 0, 'test', NULL, NULL, '2025-10-01 16:07:25', '2025-10-01 16:07:25');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_history`
--

CREATE TABLE `appointment_history` (
  `history_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `old_date` date DEFAULT NULL,
  `new_date` date DEFAULT NULL,
  `old_time` time DEFAULT NULL,
  `new_time` time DEFAULT NULL,
  `action_type` enum('status_change','reschedule','create','cancel','accept') NOT NULL,
  `performed_by` varchar(100) NOT NULL,
  `performed_by_role` enum('dermatologist','system','admin') NOT NULL DEFAULT 'dermatologist',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consents`
--

CREATE TABLE `consents` (
  `consent_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `type` enum('photo','telemed','procedure') NOT NULL,
  `signer_name` varchar(255) DEFAULT NULL,
  `signature_path` varchar(512) NOT NULL,
  `signed_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consents`
--

INSERT INTO `consents` (`consent_id`, `user_id`, `appointment_id`, `type`, `signer_name`, `signature_path`, `signed_at`) VALUES
(1, 2, 2, 'photo', 'Princess Elfa', 'uploads/consents/consent_68cd93cc3c6101.18776106.png', '2025-09-20 01:33:00'),
(2, 2, 2, 'telemed', 'Princess Elfa', 'uploads/consents/consent_68cd93cc3daa11.05920321.png', '2025-09-20 01:33:00');

-- --------------------------------------------------------

--
-- Table structure for table `conversation_participants`
--

CREATE TABLE `conversation_participants` (
  `participant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `dermatologist_id` int(11) NOT NULL,
  `cleared_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversation_participants`
--

INSERT INTO `conversation_participants` (`participant_id`, `user_id`, `dermatologist_id`, `cleared_until`) VALUES
(1, 2, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `dermatologists`
--

CREATE TABLE `dermatologists` (
  `dermatologist_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `license_number` varchar(50) NOT NULL,
  `bio` text DEFAULT NULL,
  `profile_picture_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dermatologists`
--

INSERT INTO `dermatologists` (`dermatologist_id`, `first_name`, `last_name`, `email`, `password`, `specialization`, `license_number`, `bio`, `profile_picture_url`, `created_at`) VALUES
(1, 'Maria Lourdes', 'Santos', 'maria.santos@dermacare.com', '$2y$10$tFARs3NUmIRJ3ii/jYhOjOb72ZCo71Ya1TRYaP1T.6a.81D1jhIrm', 'Clinical Dermatology', 'LICENSE-001', 'Dr. Reyes is a board-certified dermatologist specializing in the diagnosis and treatment of skin cancers, acne, and psoriasis.', 'uploads/profiles/profile_1_1751454747.jpg', '2025-06-18 18:06:43');

-- --------------------------------------------------------

--
-- Table structure for table `dermatologist_day_off`
--

CREATE TABLE `dermatologist_day_off` (
  `day_off_id` int(11) NOT NULL,
  `dermatologist_id` int(11) NOT NULL,
  `off_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dermatologist_day_off`
--

INSERT INTO `dermatologist_day_off` (`day_off_id`, `dermatologist_id`, `off_date`, `reason`, `created_at`) VALUES
(2, 1, '2025-07-03', 'test', '2025-06-20 11:50:12'),
(3, 1, '2025-06-27', 'test', '2025-06-20 11:50:48'),
(4, 1, '2025-07-15', '', '2025-07-02 11:25:42'),
(5, 1, '2025-07-25', '', '2025-07-02 11:26:18'),
(6, 1, '2025-08-19', 'test', '2025-08-17 16:22:03'),
(7, 1, '2025-08-20', NULL, '2025-08-17 16:36:01');

-- --------------------------------------------------------

--
-- Table structure for table `dermatologist_schedules`
--

CREATE TABLE `dermatologist_schedules` (
  `schedule_id` int(11) NOT NULL,
  `dermatologist_id` int(11) NOT NULL,
  `day_of_week` enum('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `education_bookmarks`
--

CREATE TABLE `education_bookmarks` (
  `bookmark_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `url` varchar(1024) NOT NULL,
  `summary` text DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'article',
  `tags_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags_json`)),
  `source` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `education_bookmarks`
--

INSERT INTO `education_bookmarks` (`bookmark_id`, `user_id`, `title`, `url`, `summary`, `type`, `tags_json`, `source`, `created_at`) VALUES
(3, 2, 'Acne - Diagnosis and treatment', 'https://www.mayoclinic.org/diseases-conditions/acne/diagnosis-treatment/drc-20368048', 'Information on how acne is diagnosed and treated, including over-the-counter and prescription medications.', 'article', '[\"acne\",\"diagnosis\",\"treatment\",\"medication\",\"skincare\"]', 'Mayo Clinic', '2025-09-18 19:10:52');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_role` enum('user','dermatologist') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `receiver_role` enum('user','dermatologist') NOT NULL,
  `message_text` text NOT NULL,
  `attachment_url` varchar(255) DEFAULT NULL,
  `attachment_type` varchar(50) DEFAULT NULL,
  `timestamp` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `edited_at` datetime(6) DEFAULT NULL,
  `deleted_at` datetime(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `sender_role`, `receiver_id`, `receiver_role`, `message_text`, `attachment_url`, `attachment_type`, `timestamp`, `is_read`, `edited_at`, `deleted_at`) VALUES
(42, 2, 'user', 1, 'dermatologist', 'Hello Dr. Isabella Reyes, this is an automatic notification that I have booked a new appointment for your review. The requested schedule is on September 13, 2025 at 8:00 AM. Thank you.', NULL, NULL, '2025-09-12 21:18:41.347235', 1, NULL, NULL),
(43, 2, 'user', 1, 'dermatologist', 'test', NULL, NULL, '2025-09-12 21:50:20.603502', 1, NULL, NULL),
(44, 2, 'user', 1, 'dermatologist', 'test', NULL, NULL, '2025-09-12 22:41:14.777516', 1, NULL, NULL),
(45, 1, 'dermatologist', 2, '', 'test', NULL, NULL, '2025-10-02 01:09:28.377016', 0, NULL, NULL),
(46, 1, 'user', 1, 'dermatologist', 'hello', NULL, NULL, '2025-10-02 01:09:42.821041', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `prescription_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `dermatologist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `patient_name` varchar(255) NOT NULL,
  `prescription_date` date NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `medications` longtext NOT NULL,
  `instructions` text DEFAULT NULL,
  `prescription_file_path` varchar(500) DEFAULT NULL,
  `is_downloaded` tinyint(1) DEFAULT 0,
  `download_count` int(11) DEFAULT 0,
  `last_downloaded_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','expired','cancelled') DEFAULT 'active',
  `expires_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`prescription_id`, `appointment_id`, `dermatologist_id`, `user_id`, `patient_name`, `prescription_date`, `diagnosis`, `medications`, `instructions`, `prescription_file_path`, `is_downloaded`, `download_count`, `last_downloaded_at`, `notes`, `status`, `expires_at`, `created_at`, `updated_at`) VALUES
(6, 3, 1, 2, 'Princess Elfa', '2025-10-02', 'test', '[{\"name\":\"test\",\"dosage\":\"test\",\"frequency\":\"test\",\"duration\":\"test\",\"instructions\":\"test\"}]', 'test', NULL, 0, 0, NULL, '', 'active', '2025-10-02', '2025-10-01 17:37:59', '2025-10-01 17:37:59');

-- --------------------------------------------------------

--
-- Table structure for table `prescription_downloads`
--

CREATE TABLE `prescription_downloads` (
  `download_id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `download_type` enum('view','download','print') DEFAULT 'view',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `downloaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescription_medications`
--

CREATE TABLE `prescription_medications` (
  `medication_id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `medication_name` varchar(255) NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `frequency` varchar(100) NOT NULL,
  `duration` varchar(100) NOT NULL,
  `instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `progress_updates`
--

CREATE TABLE `progress_updates` (
  `update_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `update_date` datetime NOT NULL DEFAULT current_timestamp(),
  `user_notes` text DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `ai_analysis` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `progress_updates`
--

INSERT INTO `progress_updates` (`update_id`, `user_id`, `appointment_id`, `update_date`, `user_notes`, `image_path`, `ai_analysis`) VALUES
(1, 2, 1, '2025-06-21 21:54:07', 'the redness has gone down but its still a little bit itchy ', 'progress_6856b97f630ed0.85911228.jpg', 'Compared to the original photo, the new photo shows a noticeable reduction in redness, which aligns with your observation that the redness has gone down. The inflammation appears to have subsided somewhat as well. This improvement is likely due to the consistent use of Clindamycin and Tretinoin as prescribed by the doctor for your Acne Vulgaris. Remember to continue using a gentle cleanser, moisturizer, and sunscreen as part of your skincare routine. While there\'s still some itchiness, the visual improvement is a great sign! Keep following your treatment plan, and you\'ll continue to see positive results.'),
(3, 2, 1, '2025-06-22 15:36:41', 'the itchy is now gone too.', 'progress_6857b289a02793.02658700.jpg', 'Compared to your previous progress photo, the new photo shows a slight increase in redness and minor blemishes. However, in comparing the new photo to the original photo, the overall condition appears significantly improved since the beginning of your treatment. You originally reported \'test\' as your symptom and the doctor prescribed Clindamycin and Tretinoin. You also noted that the itchiness is now gone! Keep following your dermatologist\'s instructions regarding the Clindamycin solution, Tretinoin cream, gentle cleanser, non-comedogenic moisturizer, and daily sunscreen. Keep up the great work, and remember to follow up with your dermatologist in 6-8 weeks!');

-- --------------------------------------------------------

--
-- Table structure for table `saved_routines`
--

CREATE TABLE `saved_routines` (
  `routine_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `routine_html` text NOT NULL,
  `language` varchar(5) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `saved_routines`
--

INSERT INTO `saved_routines` (`routine_id`, `user_id`, `appointment_id`, `routine_html`, `language`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '<p>Here\'s a simple, step-by-step daily skincare routine based on your dermatologist\'s notes to help manage your moderate acne vulgaris. Consistency is key!</p><h5>Morning Routine ☀️</h5><ol><li><strong>Cleanse:</strong> Use a gentle, non-soap cleanser to wash your face.</li><li><strong>Treat (Topical):</strong> Apply Clindamycin 1% Solution to the affected areas.</li><li><strong>Moisturize:</strong> Apply a non-comedogenic, oil-free moisturizer.</li><li><strong>Protect:</strong> Finish your routine by applying a broad-spectrum sunscreen with SPF 30 or higher. This is crucial as Tretinoin increases your skin\'s sun sensitivity.</li></ol><h5>Evening Routine 🌙</h5><ol><li><strong>Cleanse:</strong> Use a gentle, non-soap cleanser to wash your face.</li><li><strong>Treat (Topical):</strong> Apply Clindamycin 1% Solution to the affected areas.</li><li><strong>Treat (Retinoid):</strong> Apply a pea-sized amount of Tretinoin 0.025% Cream to your entire face before bed. <em>For the first two weeks, start by applying this every other night.</em> After two weeks, if tolerated, you may apply it every night as directed by your doctor.</li></ol><p>Remember to follow up with your dermatologist in 6-8 weeks as scheduled!</p>', 'en', '2025-06-21 12:09:14', '2025-06-21 12:09:14');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `profile_picture_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `password`, `phone_number`, `date_of_birth`, `profile_picture_url`, `created_at`) VALUES
(1, 'Marc', 'Esteban', 'marcdelacruzesteban@gmail.com', '$2y$10$tFARs3NUmIRJ3ii/jYhOjOb72ZCo71Ya1TRYaP1T.6a.81D1jhIrm', '09690608014', '2002-11-04', 'uploads/picture/profile_68568d9e61815.jpg', '2025-06-18 14:23:03'),
(2, 'Princess', 'Elfa', 'princesselfa07@gmail.com', '$2y$10$q09R3CHEaHCObWlgyzx3g.KYWTutAuMgiR1.X/W5yTOXGbLQ3BqV6', '09690608014', '2003-10-07', 'uploads/picture/profile_685475630b725.jpg', '2025-06-18 14:27:25'),
(3, 'test', '', 'test_user@gmail.com', '$2y$10$XrO7uPOTmZ3W/104yQiv0uK8mwj9zEn6gbkvekemsCJBTGeewvdPW', NULL, NULL, NULL, '2025-08-23 17:05:40');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_skin_scans`
--
ALTER TABLE `ai_skin_scans`
  ADD PRIMARY KEY (`scan_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD UNIQUE KEY `unique_derm_date_time` (`dermatologist_id`,`appointment_date`,`appointment_time`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `dermatologist_id` (`dermatologist_id`);

--
-- Indexes for table `appointment_history`
--
ALTER TABLE `appointment_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `action_type` (`action_type`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `consents`
--
ALTER TABLE `consents`
  ADD PRIMARY KEY (`consent_id`),
  ADD KEY `idx_user_appt` (`user_id`,`appointment_id`),
  ADD KEY `idx_type` (`type`);

--
-- Indexes for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  ADD PRIMARY KEY (`participant_id`),
  ADD UNIQUE KEY `user_derm_convo` (`user_id`,`dermatologist_id`);

--
-- Indexes for table `dermatologists`
--
ALTER TABLE `dermatologists`
  ADD PRIMARY KEY (`dermatologist_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `license_number` (`license_number`);

--
-- Indexes for table `dermatologist_day_off`
--
ALTER TABLE `dermatologist_day_off`
  ADD PRIMARY KEY (`day_off_id`),
  ADD UNIQUE KEY `unique_day_off` (`dermatologist_id`,`off_date`);

--
-- Indexes for table `dermatologist_schedules`
--
ALTER TABLE `dermatologist_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `unique_schedule` (`dermatologist_id`,`day_of_week`);

--
-- Indexes for table `education_bookmarks`
--
ALTER TABLE `education_bookmarks`
  ADD PRIMARY KEY (`bookmark_id`),
  ADD UNIQUE KEY `uniq_user_title_url` (`user_id`,`title`,`url`) USING HASH,
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `conversation_idx` (`sender_id`,`receiver_id`,`is_read`),
  ADD KEY `receiver_lookup_idx` (`receiver_id`,`is_read`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`prescription_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `dermatologist_id` (`dermatologist_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `prescription_date` (`prescription_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `prescription_downloads`
--
ALTER TABLE `prescription_downloads`
  ADD PRIMARY KEY (`download_id`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `downloaded_at` (`downloaded_at`);

--
-- Indexes for table `prescription_medications`
--
ALTER TABLE `prescription_medications`
  ADD PRIMARY KEY (`medication_id`),
  ADD KEY `prescription_id` (`prescription_id`);

--
-- Indexes for table `progress_updates`
--
ALTER TABLE `progress_updates`
  ADD PRIMARY KEY (`update_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `saved_routines`
--
ALTER TABLE `saved_routines`
  ADD PRIMARY KEY (`routine_id`),
  ADD UNIQUE KEY `user_appointment` (`user_id`,`appointment_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_skin_scans`
--
ALTER TABLE `ai_skin_scans`
  MODIFY `scan_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `appointment_history`
--
ALTER TABLE `appointment_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `consents`
--
ALTER TABLE `consents`
  MODIFY `consent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  MODIFY `participant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `dermatologists`
--
ALTER TABLE `dermatologists`
  MODIFY `dermatologist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `dermatologist_day_off`
--
ALTER TABLE `dermatologist_day_off`
  MODIFY `day_off_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `dermatologist_schedules`
--
ALTER TABLE `dermatologist_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `education_bookmarks`
--
ALTER TABLE `education_bookmarks`
  MODIFY `bookmark_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `prescription_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `prescription_downloads`
--
ALTER TABLE `prescription_downloads`
  MODIFY `download_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescription_medications`
--
ALTER TABLE `prescription_medications`
  MODIFY `medication_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `progress_updates`
--
ALTER TABLE `progress_updates`
  MODIFY `update_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `saved_routines`
--
ALTER TABLE `saved_routines`
  MODIFY `routine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_skin_scans`
--
ALTER TABLE `ai_skin_scans`
  ADD CONSTRAINT `ai_skin_scans_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`dermatologist_id`) REFERENCES `dermatologists` (`dermatologist_id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_history`
--
ALTER TABLE `appointment_history`
  ADD CONSTRAINT `appointment_history_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE;

--
-- Constraints for table `dermatologist_day_off`
--
ALTER TABLE `dermatologist_day_off`
  ADD CONSTRAINT `dermatologist_day_off_ibfk_1` FOREIGN KEY (`dermatologist_id`) REFERENCES `dermatologists` (`dermatologist_id`) ON DELETE CASCADE;

--
-- Constraints for table `dermatologist_schedules`
--
ALTER TABLE `dermatologist_schedules`
  ADD CONSTRAINT `dermatologist_schedules_ibfk_1` FOREIGN KEY (`dermatologist_id`) REFERENCES `dermatologists` (`dermatologist_id`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`dermatologist_id`) REFERENCES `dermatologists` (`dermatologist_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `prescription_downloads`
--
ALTER TABLE `prescription_downloads`
  ADD CONSTRAINT `prescription_downloads_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`prescription_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescription_downloads_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `prescription_medications`
--
ALTER TABLE `prescription_medications`
  ADD CONSTRAINT `prescription_medications_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`prescription_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
