-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 15, 2025 at 10:35 AM
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
(20, 2, 'Princess Elfa', 'jijebip930@lorkex.com', '09934528204', 1, '2025-10-18', '07:00:00', 'Consultation - New Patient', 'Scheduled', 1, 'test', 'uploads/appointments/appt_68ef3206011ba4.30401452.jpg', NULL, '2025-10-15 05:32:54', '2025-10-15 06:31:56'),
(22, 1, 'Marc Esteban', 'marcdelacruzesteban@gmail.com', '09934528204', 1, '2025-10-17', '11:00:00', 'test', 'Scheduled', 0, NULL, NULL, 'test', '2025-10-15 07:33:28', '2025-10-15 08:27:26'),
(23, 1, 'test', 'marcdelacruzesteban@gmail.com', '09934528204', 1, '2025-10-15', '14:30:00', 'test', 'Completed', 0, NULL, NULL, 'test', '2025-10-15 08:20:11', '2025-10-15 08:27:52');

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

--
-- Dumping data for table `appointment_history`
--

INSERT INTO `appointment_history` (`history_id`, `appointment_id`, `old_status`, `new_status`, `old_date`, `new_date`, `old_time`, `new_time`, `action_type`, `performed_by`, `performed_by_role`, `notes`, `created_at`) VALUES
(4, 20, 'Pending', 'Scheduled', NULL, NULL, NULL, NULL, 'accept', 'Maria Lourdes Santos', 'dermatologist', 'Appointment accepted by dermatologist', '2025-10-15 06:26:26'),
(5, 20, 'Pending', 'Scheduled', NULL, NULL, NULL, NULL, 'accept', 'Maria Lourdes Santos', 'dermatologist', 'Appointment accepted by dermatologist and confirmation message sent to patient', '2025-10-15 06:29:11'),
(6, 20, 'Pending', 'Scheduled', NULL, NULL, NULL, NULL, 'accept', 'Maria Lourdes Santos', 'dermatologist', 'Appointment accepted by dermatologist and confirmation message sent to patient', '2025-10-15 06:31:48'),
(12, 22, 'Pending', 'Scheduled', NULL, NULL, NULL, NULL, 'accept', 'Maria Lourdes Santos', 'dermatologist', 'Appointment accepted by dermatologist and email notification sent', '2025-10-15 07:59:22'),
(13, 22, 'Scheduled', 'Cancelled', NULL, NULL, NULL, NULL, 'cancel', 'Maria Lourdes Santos', 'dermatologist', 'Appointment cancelled by dermatologist and email notification sent', '2025-10-15 08:00:38'),
(14, 22, 'Scheduled', 'Completed', NULL, NULL, NULL, NULL, 'status_change', 'Maria Lourdes Santos', 'dermatologist', 'Appointment marked as completed by dermatologist and email notification sent', '2025-10-15 08:01:27'),
(15, 22, 'Pending', 'Scheduled', NULL, NULL, NULL, NULL, 'accept', 'Maria Lourdes Santos', 'dermatologist', 'Appointment accepted by dermatologist and email notification sent', '2025-10-15 08:09:13'),
(16, 22, 'Pending', 'Scheduled', NULL, NULL, NULL, NULL, 'accept', 'Maria Lourdes Santos', 'dermatologist', 'Appointment accepted by dermatologist and email notification sent', '2025-10-15 08:15:01'),
(17, 22, 'Pending', 'Scheduled', NULL, NULL, NULL, NULL, 'accept', 'Maria Lourdes Santos', 'dermatologist', 'Appointment accepted by dermatologist and email notification sent', '2025-10-15 08:17:59'),
(18, 22, 'Pending', 'Scheduled', NULL, NULL, NULL, NULL, 'accept', 'Maria Lourdes Santos', 'dermatologist', 'Appointment accepted by dermatologist and email notification sent', '2025-10-15 08:21:53'),
(19, 23, 'Pending', 'Scheduled', NULL, NULL, NULL, NULL, 'accept', 'Maria Lourdes Santos', 'dermatologist', 'Appointment accepted by dermatologist and email notification sent', '2025-10-15 08:23:01'),
(20, 23, 'Pending', 'Scheduled', NULL, NULL, NULL, NULL, 'accept', 'Maria Lourdes Santos', 'dermatologist', 'Appointment accepted by dermatologist and email notification sent', '2025-10-15 08:26:16'),
(21, 23, 'Scheduled', 'Cancelled', NULL, NULL, NULL, NULL, 'cancel', 'Maria Lourdes Santos', 'dermatologist', 'Appointment cancelled by dermatologist and email notification sent', '2025-10-15 08:27:37'),
(22, 23, 'Scheduled', 'Completed', NULL, NULL, NULL, NULL, 'status_change', 'Maria Lourdes Santos', 'dermatologist', 'Appointment marked as completed by dermatologist and email notification sent', '2025-10-15 08:27:56');

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
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','replied') DEFAULT 'unread',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `phone_number`, `message`, `status`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 'Marc Esteban', 'marcdelacruzesteban@gmail.com', '09934528204', 'testtestasdasdadsasddadasdasdsadsadasds', 'unread', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-15 08:34:37', '2025-10-15 08:34:37');

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
(1, 'Maria Lourdes', 'Santos', 'jijebip930@lorkex.com', '$2y$10$OkDBW0xnIPdvbSvS/4h6m.BO22t75Jz1Djgl8G8J6PkWPquwwo3vu', 'Clinical Dermatology', 'LICENSE-001', 'Dr. Reyes is a board-certified dermatologist specializing in the diagnosis and treatment of skin cancers, acne, and psoriasis.', 'uploads/profiles/profile_1_1751454747.jpg', '2025-06-18 18:06:43');

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
(7, 1, '2025-08-20', NULL, '2025-08-17 16:36:01'),
(8, 1, '2025-10-16', 'test', '2025-10-15 06:01:37'),
(9, 1, '2025-10-22', 'test', '2025-10-15 06:01:47'),
(10, 1, '2025-10-19', NULL, '2025-10-15 06:02:12'),
(11, 1, '2025-10-24', 'test', '2025-10-15 06:33:12'),
(12, 1, '2025-10-20', NULL, '2025-10-15 06:33:23');

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
(22, 2, 'Acne', 'https://www.mayoclinic.org/diseases-conditions/acne/symptoms-causes/syc-20368047', 'Overview of acne, including causes, symptoms, and treatment options. Covers different types of acne and factors that can worsen it.', 'article', '[\"acne\",\"causes\",\"symptoms\",\"treatment\"]', 'mayoclinic.org', '2025-10-15 05:34:24');

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
(60, 1, 'dermatologist', 1, 'user', 'Good news! Your dermatology appointment for Marc Esteban has been confirmed and scheduled for October 15, 2025 at 11:00 AM. Please arrive 15 minutes early for check-in. If you need to make any changes, please contact us as soon as possible. We look forward to seeing you!', NULL, NULL, '2025-10-15 01:17:54.714398', 1, NULL, NULL),
(61, 1, 'dermatologist', 1, 'user', 'Good news! Your dermatology appointment for Marc Esteban has been confirmed and scheduled for October 15, 2025 at 11:00 AM. Please arrive 15 minutes early for check-in. If you need to make any changes, please contact us as soon as possible. We look forward to seeing you!', NULL, NULL, '2025-10-15 01:21:50.390498', 1, NULL, NULL),
(62, 1, 'dermatologist', 1, 'user', 'Good news! Your dermatology appointment for test has been confirmed and scheduled for October 15, 2025 at 2:30 PM. Please arrive 15 minutes early for check-in. If you need to make any changes, please contact us as soon as possible. We look forward to seeing you!', NULL, NULL, '2025-10-15 01:22:58.004389', 1, NULL, NULL),
(63, 1, 'dermatologist', 1, 'user', 'Good news! Your dermatology appointment for test has been confirmed and scheduled for October 15, 2025 at 2:30 PM. Please arrive 15 minutes early for check-in. If you need to make any changes, please contact us as soon as possible. We look forward to seeing you!', NULL, NULL, '2025-10-15 01:26:12.965336', 1, NULL, NULL),
(64, 1, 'dermatologist', 1, 'user', 'Your appointment has been rescheduled from October 15, 2025 at 11:00 AM to October 17, 2025 at 11:00 AM. You will receive an email confirmation with the new details. Please arrive 15 minutes early. If you need to make further changes, please contact us as soon as possible.', NULL, NULL, '2025-10-15 01:27:26.126417', 1, NULL, NULL),
(65, 1, 'dermatologist', 1, 'user', 'We regret to inform you that your appointment on October 15, 2025 at 2:30 PM has been cancelled. You will receive an email confirmation. If you would like to reschedule, please contact our office. We apologize for any inconvenience.', NULL, NULL, '2025-10-15 01:27:34.308476', 1, NULL, NULL),
(66, 1, 'dermatologist', 1, 'user', 'Thank you for visiting DermaSculpt! Your appointment on October 15, 2025 at 2:30 PM has been completed. Please follow any post-treatment instructions provided. If you have any questions or need to schedule a follow-up, please contact us. We appreciate your trust in our care!', NULL, NULL, '2025-10-15 01:27:52.847000', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `otp_codes`
--

CREATE TABLE `otp_codes` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_codes`
--

INSERT INTO `otp_codes` (`id`, `email`, `otp_code`, `created_at`, `expires_at`, `is_used`, `attempts`) VALUES
(4, 'princesselfa07@gmail.com', '734143', '2025-10-15 00:54:40', '2025-10-15 10:09:40', 1, 0);

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
-- Table structure for table `skin_analysis`
--

CREATE TABLE `skin_analysis` (
  `analysis_id` int(11) NOT NULL,
  `dermatologist_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `patient_name` varchar(255) DEFAULT NULL,
  `patient_age` int(3) DEFAULT NULL,
  `patient_gender` enum('Male','Female','Other') DEFAULT NULL,
  `image_path` varchar(500) NOT NULL,
  `image_filename` varchar(255) NOT NULL,
  `analysis_prompt` text DEFAULT NULL,
  `ai_diagnosis` longtext DEFAULT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `detected_conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detected_conditions`)),
  `recommendations` longtext DEFAULT NULL,
  `dermatologist_notes` text DEFAULT NULL,
  `dermatologist_diagnosis` text DEFAULT NULL,
  `status` enum('pending','reviewed','confirmed','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `skin_analysis`
--

INSERT INTO `skin_analysis` (`analysis_id`, `dermatologist_id`, `appointment_id`, `patient_name`, `patient_age`, `patient_gender`, `image_path`, `image_filename`, `analysis_prompt`, `ai_diagnosis`, `confidence_score`, `detected_conditions`, `recommendations`, `dermatologist_notes`, `dermatologist_diagnosis`, `status`, `created_at`, `updated_at`) VALUES
(5, 1, 20, 'Princess Elfa', 22, 'Female', '../uploads/skin_analysis/appointment_analysis_20_68ef400292fa7.jpg', 'appointment_analysis_20_68ef400292fa7.jpg', '', 'Vitiligo', 95.00, '[{\"condition\":\"Pityriasis Alba\",\"probability\":3,\"rationale\":\"Pityriasis alba can present with hypopigmented patches, but typically has fine scaling and is more common in children. The distribution and complete depigmentation seen in the image make this less likely.\"},{\"condition\":\"Post-inflammatory Hypopigmentation\",\"probability\":2,\"rationale\":\"While possible, the distinct borders and complete lack of pigment suggest vitiligo over post-inflammatory hypopigmentation. A history of prior inflammation would be needed to support this diagnosis.\"}]', '**Visual Findings:**\nMultiple well-defined, depigmented (white) macules and patches are observed on the dorsal aspect of both hands and fingers. The distribution appears somewhat symmetric. The lesions are achromic, meaning there is a complete absence of pigment within the affected areas. No scaling, crusting, or other secondary changes are noted.\n\n**Clinical Recommendations:**\nA Wood\'s lamp examination can be used to accentuate the depigmentation and confirm the diagnosis. Consider referral to a dermatologist for further evaluation and management. Treatment options may include topical corticosteroids, topical calcineurin inhibitors, phototherapy, or depigmentation therapy for widespread disease. Sun protection is crucial to prevent sunburn in the depigmented areas.\n\n**Red Flags:**\nNone apparent in the image. However, it\'s important to rule out associated autoimmune conditions, which are more common in patients with vitiligo.\n\n**Patient Education:**\nVitiligo is a chronic skin condition characterized by loss of pigment in patches. It is not contagious. The exact cause is unknown, but it is thought to be an autoimmune disorder. Sun protection is essential to prevent sunburn and further damage to the depigmented skin. Psychological support may be beneficial, as vitiligo can affect self-esteem.\n\n**Follow-up:**\nFollow-up with a dermatologist is recommended to monitor the progression of the disease and adjust treatment as needed. The frequency of follow-up will depend on the severity of the condition and the chosen treatment approach. A reasonable initial follow-up would be in 3-6 months.\n\n', NULL, NULL, 'pending', '2025-10-15 06:32:40', '2025-10-15 06:32:40');

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
(2, 'Princess', 'Elfa', 'jijebip930@lorkex.com', '$2y$12$qEoJ8KDRMgxwObhye0T0iuJU6puO66ny5cW5VESSt6sFADbi2FGpa', '09934528204', '2003-10-07', 'uploads/picture/profile_685475630b725.jpg', '2025-06-18 14:27:25'),
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
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_email` (`email`);

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
-- Indexes for table `otp_codes`
--
ALTER TABLE `otp_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_otp` (`email`,`otp_code`),
  ADD KEY `idx_expires_at` (`expires_at`);

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
-- Indexes for table `skin_analysis`
--
ALTER TABLE `skin_analysis`
  ADD PRIMARY KEY (`analysis_id`),
  ADD KEY `dermatologist_id` (`dermatologist_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_derm_status` (`dermatologist_id`,`status`),
  ADD KEY `idx_created_date` (`created_at`),
  ADD KEY `fk_skin_analysis_appointment` (`appointment_id`);

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
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `appointment_history`
--
ALTER TABLE `appointment_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `consents`
--
ALTER TABLE `consents`
  MODIFY `consent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `day_off_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `dermatologist_schedules`
--
ALTER TABLE `dermatologist_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `education_bookmarks`
--
ALTER TABLE `education_bookmarks`
  MODIFY `bookmark_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `otp_codes`
--
ALTER TABLE `otp_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `progress_updates`
--
ALTER TABLE `progress_updates`
  MODIFY `update_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `saved_routines`
--
ALTER TABLE `saved_routines`
  MODIFY `routine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `skin_analysis`
--
ALTER TABLE `skin_analysis`
  MODIFY `analysis_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
-- Constraints for table `skin_analysis`
--
ALTER TABLE `skin_analysis`
  ADD CONSTRAINT `fk_skin_analysis_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `skin_analysis_ibfk_1` FOREIGN KEY (`dermatologist_id`) REFERENCES `dermatologists` (`dermatologist_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
