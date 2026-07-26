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

-- 4. Risk Grupları Tablosu (Ergonomik, Biyolojik, Fiziksel vb.)
CREATE TABLE IF NOT EXISTS `risk_groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `sort_order` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Genel Cevap Seçenekleri Tablosu
CREATE TABLE IF NOT EXISTS `global_options` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `option_text` VARCHAR(255) NOT NULL,
  `trigger_action` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. İSG Tanımlama Kütüphaneleri Tablosu
CREATE TABLE IF NOT EXISTS `risk_libraries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category` ENUM('hazard_source', 'hazard_name', 'affected_people', 'responsible_person', 'action_recommendation') NOT NULL,
  `item_text` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Anket Profilleri / Şablonları (Hastane İSG, Nükleer Tıp İSG vb.)
CREATE TABLE IF NOT EXISTS `survey_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `category` VARCHAR(50) DEFAULT 'Genel',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Anket Soruları / Resmi İSG Risk Matrisi Satırları (12 Sütunlu Form Yapısı)
CREATE TABLE IF NOT EXISTS `survey_questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `risk_group_id` INT NULL,
  `question_text` TEXT NULL,
  `hazard_source` VARCHAR(255) NULL,
  `hazard_name` VARCHAR(255) NULL,
  `affected_risk` TEXT NULL,
  `affected_people` VARCHAR(255) NULL,
  `current_status` TEXT NULL,
  `default_probability` INT DEFAULT 2,
  `default_severity` INT DEFAULT 3,
  `default_action_plan` TEXT NULL,
  `default_responsible` VARCHAR(255) NULL,
  `default_deadline` VARCHAR(100) NULL,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`template_id`) REFERENCES `survey_templates`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`risk_group_id`) REFERENCES `risk_groups`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Soru Seçenekleri ve Puan Tablosu
CREATE TABLE IF NOT EXISTS `question_options` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question_id` INT NOT NULL,
  `option_text` VARCHAR(255) NOT NULL,
  `points` INT NOT NULL DEFAULT 0,
  `trigger_action` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`question_id`) REFERENCES `survey_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Sahada Yapılan Denetimler Tablosu
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

-- 11. Denetimde Seçilen Cevaplar & Risk Analiz Detayları Tablosu
CREATE TABLE IF NOT EXISTS `audit_answers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `audit_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `option_id` INT NULL,
  `answer_option` VARCHAR(50) NULL,
  `points_awarded` INT NOT NULL DEFAULT 0,
  `current_status` TEXT NULL,
  `probability` INT DEFAULT 1,
  `severity` INT DEFAULT 1,
  `risk_score` INT DEFAULT 1,
  `action_plan` TEXT NULL,
  `responsible_person` VARCHAR(255) NULL,
  `deadline` VARCHAR(100) NULL,
  FOREIGN KEY (`audit_id`) REFERENCES `audits`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `survey_questions`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Sistem İşlem Logları Tablosu
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

-- Varsayılan Veriler

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

-- 3. Varsayılan Genel Cevap Seçenekleri
INSERT INTO `global_options` (`id`, `option_text`, `trigger_action`, `sort_order`, `is_active`) VALUES
(1, 'Evet (Uygun)', 0, 1, 1),
(2, 'Hayır (Uygun Değil)', 1, 2, 1),
(3, 'Kısmen (Kısmen Uygun)', 1, 3, 1),
(4, 'Denetim Dışı / Muaf', 0, 4, 1)
ON DUPLICATE KEY UPDATE `option_text` = VALUES(`option_text`), `trigger_action` = VALUES(`trigger_action`);

-- 4. Varsayılan İSG Risk Grupları
INSERT INTO `risk_groups` (`id`, `group_name`, `description`, `sort_order`) VALUES
(1, 'Biyolojik Riskler', 'Enfeksiyon, pis su bulaşması, bulaşıcı biyolojik etkenler', 1),
(2, 'Ergonomik Riskler', 'Ekranlı araçlar, ayakta kalma, ağır kaldırma, kas-iskelet yükü', 2),
(3, 'Fiziksel Riskler', 'Gürültü, aydınlatma, havalandırma, kaygan zemin, yüksekten düşme', 3),
(4, 'Kimyasal Riskler', 'Tıbbi atıklar, dezenfektanlar, tehlikeli kimyasal maruziyeti', 4),
(5, 'Psikososyal Riskler', 'Vardiyalı çalışma, iş stresi, aşırı iş yükü', 5),
(6, 'Genel Saha & Hijyen Riskleri', 'Yangın tesisatı, acil çıkışlar, ilk yardım ve genel temizlik', 6)
ON DUPLICATE KEY UPDATE `group_name` = VALUES(`group_name`);

-- 5. Varsayılan İSG Kütüphane Öğeleri
INSERT INTO `risk_libraries` (`category`, `item_text`) VALUES
('hazard_source', 'Lavabo, Wc tavanı'),
('hazard_source', 'Ekranlı Araçlar (Bilgisayar vb.)'),
('hazard_source', 'Çalışma alanı'),
('hazard_source', 'Tıbbi atık alanı ve jeneratör dairesi'),
('hazard_name', 'Enfeksiyon'),
('hazard_name', 'Uzun süre sabit oturma'),
('hazard_name', 'Ayakta kalma'),
('hazard_name', 'Kaygan zemin ve kimyasal maruziyeti'),
('affected_people', 'Çalışanlar(Doktor, Hemşire, Sağ. Tek. hasta bakıcı, temizlik çalışanı vd.)Hasta ve hasta yakını'),
('responsible_person', 'Tekn. Hiz. Yön.'),
('responsible_person', 'Mali Hiz. Yön.'),
('responsible_person', 'Çalışan'),
('responsible_person', 'İSG Birimi'),
('action_recommendation', 'Lavabo(wc) tavanlarda gerekli yalıtımın sağlanması'),
('action_recommendation', 'Çalışma araları verilmeli, boyun egzersizleri yapılmalı, ortopedik Mouse pedleri kullanılmalı'),
('action_recommendation', 'Kısa mola ve dinlenmeler yapılmalı, Uzun süre ayakta kalınmamalı, egzersiz ve ara dinlenmeler verilmeli')
ON DUPLICATE KEY UPDATE `item_text` = VALUES(`item_text`);
