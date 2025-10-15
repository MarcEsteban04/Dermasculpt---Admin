-- Update the inquiry_replies table to only handle dermatologist replies
-- Patient replies will come from Gmail API

-- Drop the existing table and recreate with proper structure
DROP TABLE IF EXISTS `inquiry_replies`;

CREATE TABLE `inquiry_replies` (
  `reply_id` int(11) NOT NULL AUTO_INCREMENT,
  `original_message_id` int(11) NOT NULL,
  `dermatologist_id` int(11) NOT NULL,
  `reply_message` text NOT NULL,
  `email_message_id` varchar(255) DEFAULT NULL, -- Gmail message ID for tracking
  `email_thread_id` varchar(255) DEFAULT NULL, -- Gmail thread ID for threading
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`reply_id`),
  KEY `idx_original_message` (`original_message_id`),
  KEY `idx_dermatologist` (`dermatologist_id`),
  KEY `idx_email_message_id` (`email_message_id`),
  KEY `idx_email_thread_id` (`email_thread_id`),
  CONSTRAINT `fk_inquiry_replies_message` FOREIGN KEY (`original_message_id`) REFERENCES `contact_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inquiry_replies_dermatologist` FOREIGN KEY (`dermatologist_id`) REFERENCES `dermatologists` (`dermatologist_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add a field to contact_messages to store the email thread ID for Gmail tracking
ALTER TABLE `contact_messages` 
ADD COLUMN `email_thread_id` varchar(255) DEFAULT NULL AFTER `user_agent`,
ADD INDEX `idx_email_thread_id` (`email_thread_id`);
