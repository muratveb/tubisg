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

-- Varsayılan Verilerin Eklenmesi

-- 1. Varsayılan Roller
INSERT INTO `roles` (`id`, `role_name`, `description`, `permissions`) VALUES
(1, 'Süper Yönetici', 'Sistemdeki tüm yetkilere sınırsız erişim', '{"surveys_manage":true,"units_manage":true,"audit_conduct":true,"audit_view":true,"reports_export":true,"users_manage":true}'),
(2, 'İSG Denetçisi / Saha Elemanı', 'Sahada denetim başlatma, doldurma ve raporları inceleme yetkisi', '{"surveys_manage":false,"units_manage":true,"audit_conduct":true,"audit_view":true,"reports_export":true,"users_manage":false}'),
(3, 'Birim Yöneticisi', 'Sadece denetim raporlarını ve birim puanlarını görüntüleme', '{"surveys_manage":false,"units_manage":false,"audit_conduct":false,"audit_view":true,"reports_export":true,"users_manage":false}');

-- 2. Varsayılan Kullanıcılar (Kullanıcı adları: admin ve denetci / Parolalar: admin123)
INSERT INTO `users` (`id`, `username`, `password`, `name_surname`, `email`, `role_id`, `is_active`) VALUES
(1, 'admin', '$2y$10$Xh9faN5jPdk/rVrPF4WrcOZ7/0RyQBiwc.5qL7Cw5uGLTNbk0W18u', 'Sistem Yöneticisi', 'admin@tubisg.com', 1, 1),
(2, 'denetci', '$2y$10$Xh9faN5jPdk/rVrPF4WrcOZ7/0RyQBiwc.5qL7Cw5uGLTNbk0W18u', 'Ahmet Yılmaz (Saha Denetçisi)', 'ahmet@tubisg.com', 2, 1);

-- 3. Örnek Birimler
INSERT INTO `units` (`id`, `unit_name`, `description`) VALUES
(1, 'Faturalama Birimi', 'İdari bina 2. kat ana faturalama ve muhasebe servisi'),
(2, 'Ameliyathane A Blok', 'Hastane ana bina 1. kat cerrahi müdahale alanları'),
(3, 'Genel Depo / Lojistik', 'Saha malzeme depolama ve sevkiyat alanı'),
(4, 'Teknik Servis Atölyesi', 'Bakım-onarım ve elektrik panolarının yer aldığı alan');

-- 4. Örnek Anket Şablonu (Hastane İSG)
INSERT INTO `survey_templates` (`id`, `title`, `description`, `category`, `is_active`, `created_by`) VALUES
(1, 'Hastane İSG Saha Denetimi', 'Sağlık tesislerinde çalışan koruyucu donanım ve saha risk denetim anketi', 'Sağlık Tesisleri', 1, 1),
(2, 'Şantiye & Depo Saha Denetimi', 'Genel saha, baret, iş ayakkabısı ve istifleme denetimi', 'Saha / Lojistik', 1, 1);

-- 5. Örnek Sorular (Hastane İSG için)
INSERT INTO `survey_questions` (`id`, `template_id`, `question_text`, `sort_order`) VALUES
(1, 1, 'Sahada eleman eldiven kullanıyor mu? Kullanıyorsa hangi renk/tür kullanıyor?', 1),
(2, 1, 'Saha çalışanı baret ve çene koruması takıyor mu?', 2),
(3, 1, 'Çalışma alanındaki acil çıkış ve İSG ikaz levhaları uygun mu?', 3);

-- 6. Örnek Seçenekler ve Pozitif / Negatif Puanlar
INSERT INTO `question_options` (`id`, `question_id`, `option_text`, `points`, `sort_order`) VALUES
-- Soru 1 Seçenekleri
(1, 1, 'Hayır, Eldiven kullanılmıyor', -5, 1),
(2, 1, 'Evet, Kırmızı eldiven kullanılıyor (Standart Korumalı)', 5, 2),
(3, 1, 'Evet, Sarı eldiven kullanılıyor (Yüksek Nitril Korumalı)', 10, 3),
(4, 1, 'Eldivenler delik, yıpranmış veya kirli', -10, 4),

-- Soru 2 Seçenekleri
(5, 2, 'Hayır, Baret kullanılmıyor', -10, 1),
(6, 2, 'Evet, Çene bantlı TS-EN 397 Baret takılıyor', 10, 2),
(7, 2, 'Baret takılı fakat çene bandı bağlanmamış', 2, 3),

-- Soru 3 Seçenekleri
(8, 3, 'Levhalar eksik veya görünmüyor', -5, 1),
(9, 3, 'Tüm İSG ikaz levhaları eksiksiz ve görünür durumda', 10, 2),
(10, 3, 'Acil yönlendirme armatürleri ve yangın tüpleri kontrol kartlı', 5, 3);
