-- Tubİsg Database Schema & Initial Data
-- Workspaces / MAMP / MySQL utf8mb4

CREATE DATABASE IF NOT EXISTS `tubisg_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tubisg_db`;

-- 1. Rol ve Yetkiler Tablosu
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL,
  `permissions` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Kullanıcılar Tablosu
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL,
  `permissions` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `name_surname` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NULL,
  `role_id` INT NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Birimler Tablosu (Faturalama, Ameliyathane, Depo vb.)
CREATE TABLE IF NOT EXISTS `units` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `unit_name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Anket Profilleri / Şablonları (Hastane İSG, Fabrika İSG vb.)
CREATE TABLE IF NOT EXISTS `survey_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `category` VARCHAR(50) DEFAULT 'Genel',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Anket Soruları Tablosu
CREATE TABLE IF NOT EXISTS `survey_questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `question_text` TEXT NOT NULL,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`template_id`) REFERENCES `survey_templates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Soru Seçenekleri ve Puan Tablosu
CREATE TABLE IF NOT EXISTS `question_options` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question_id` INT NOT NULL,
  `option_text` VARCHAR(255) NOT NULL,
  `points` INT NOT NULL DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`question_id`) REFERENCES `survey_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Sahada Yapılan Denetimler Tablosu
CREATE TABLE IF NOT EXISTS `audits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `unit_id` INT NOT NULL,
  `auditor_id` INT NOT NULL,
  `total_score` INT NOT NULL DEFAULT 0,
  `max_possible_score` INT NOT NULL DEFAULT 0,
  `percentage_score` DECIMAL(5,2) DEFAULT 0.00,
  `status` ENUM('Devam Ediyor', 'Tamamlandı') DEFAULT 'Tamamlandı',
  `notes` TEXT NULL,
  `audit_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`template_id`) REFERENCES `survey_templates`(`id`),
  FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`),
  FOREIGN KEY (`auditor_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Denetimde Seçilen Cevaplar Tablosu
CREATE TABLE IF NOT EXISTS `audit_answers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `audit_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `option_id` INT NOT NULL,
  `points_awarded` INT NOT NULL,
  FOREIGN KEY (`audit_id`) REFERENCES `audits`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `survey_questions`(`id`),
  FOREIGN KEY (`option_id`) REFERENCES `question_options`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Sistem İşlem Logları Tablosu
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `username` VARCHAR(50) NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan Verilerin Eklenmesi

-- 1. Varsayılan Roller
INSERT INTO `roles` (`id`, `role_name`, `description`, `permissions`) VALUES
(1, 'Süper Yönetici', 'Sistemdeki tüm yetkilere sınırsız erişim', '{"surveys_manage":true,"units_manage":true,"audit_conduct":true,"audit_view":true,"audit_delete":true,"reports_export":true,"users_manage":true,"logs_view":true}'),
(2, 'İSG Denetçisi', 'Saha denetimleri gerçekleştirme ve kendi raporlarını görüntüleme', '{"surveys_manage":false,"units_manage":true,"audit_conduct":true,"audit_view":true,"audit_delete":false,"reports_export":true,"users_manage":false,"logs_view":false}'),
(3, 'Birim Sorumlusu', 'Yalnızca denetim raporlarını görüntüleme ve çıktı alma', '{"surveys_manage":false,"units_manage":false,"audit_conduct":false,"audit_view":true,"audit_delete":false,"reports_export":true,"users_manage":false,"logs_view":false}')
ON DUPLICATE KEY UPDATE `permissions` = VALUES(`permissions`);

-- 2. Varsayılan Kullanıcılar (Parola: admin123)
INSERT INTO `users` (`id`, `username`, `password`, `name_surname`, `email`, `role_id`, `is_active`) VALUES
(1, 'admin', '$2y$10$Xh9faN5jPdk/rVrPF4WrcOZ7/0RyQBiwc.5qL7Cw5uGLTNbk0W18u', 'Tuba BAL', 'admin@tubisg.com', 1, 1),
(2, 'denetci', '$2y$10$Xh9faN5jPdk/rVrPF4WrcOZ7/0RyQBiwc.5qL7Cw5uGLTNbk0W18u', 'Saha Denetçisi Ahmet', 'ahmet@tubisg.com', 2, 1)
ON DUPLICATE KEY UPDATE `id` = `id`;
