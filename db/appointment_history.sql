-- Appointment History Table for Audit Log
-- Run this SQL to add appointment history tracking

CREATE TABLE `appointment_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `action_type` (`action_type`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `appointment_history_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
