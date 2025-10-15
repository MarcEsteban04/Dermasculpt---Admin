-- Add skin analysis table to dermasculpt_db
-- This table stores AI-powered skin analysis results for dermatologists

CREATE TABLE `skin_analysis` (
  `analysis_id` int(11) NOT NULL AUTO_INCREMENT,
  `dermatologist_id` int(11) NOT NULL,
  `patient_name` varchar(255) DEFAULT NULL,
  `patient_age` int(3) DEFAULT NULL,
  `patient_gender` enum('Male','Female','Other') DEFAULT NULL,
  `image_path` varchar(500) NOT NULL,
  `image_filename` varchar(255) NOT NULL,
  `analysis_prompt` text DEFAULT NULL,
  `ai_diagnosis` longtext DEFAULT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `detected_conditions` json DEFAULT NULL,
  `recommendations` longtext DEFAULT NULL,
  `dermatologist_notes` text DEFAULT NULL,
  `dermatologist_diagnosis` text DEFAULT NULL,
  `status` enum('pending','reviewed','confirmed','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`analysis_id`),
  KEY `dermatologist_id` (`dermatologist_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `skin_analysis_ibfk_1` FOREIGN KEY (`dermatologist_id`) REFERENCES `dermatologists` (`dermatologist_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add indexes for better performance
CREATE INDEX `idx_derm_status` ON `skin_analysis` (`dermatologist_id`, `status`);
CREATE INDEX `idx_created_date` ON `skin_analysis` (`created_at` DESC);
