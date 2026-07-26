# Tubİsg - Proje Teknik Dokümantasyonu & Mimarisi (AI Context File)

> **Bu dosya, Tubİsg projesinin mimarisini, veritabanı yapısını, iş kurallarını ve yetkilendirme modelini her yapay zekanın (LLM) kolayca anlayabileceği şekilde özetler.**

---

## 1. Proje Amacı ve Genel Özet
**Tubİsg**, İş Sağlığı ve Güvenliği (İSG) saha denetçilerinin mobil cihaz veya tablet üzerinden sahadaki birimlerde (örn. Faturalama Birimi, Depo, Ameliyathane vb.) resmi **"Birim Bazlı Risk Analiz Formu" (5x5 L-Tipi Risk Değerlendirme Matrisi)** standartlarında saha denetimlerini gerçekleştirmesini sağlayan dinamik bir PHP + MySQL web uygulamasıdır.

### Temel Yetenekler:
1. **İSG Risk Grupları Yönetimi (`risk_groups.php`)**: Ergonomik, Biyolojik, Fiziksel, Kimyasal, Psikososyal vb. risk grupları tanımlanabilir ve sorular bu gruplar altında organize edilir.
2. **Tehlike ve Risk Bankası Editörü (`survey_edit.php`)**: Her bir soru için *Tehlike Kaynağı*, *Tehlike*, *Etkilenme (Yaşanabilecek Riskler)* ve *Etkilenenler* tanımlanabilir.
3. **Cevap Seçenekleri & Otomatik Risk Matrisi ($R = O \times Ş$)**:
   - Denetçi sahada *Evet*, *Hayır*, *Kısmen*, *Muaf* butonları ile hızlı cevap verir.
   - "Hayır" veya "Kısmen" (olumsuz/riskli durum) seçildiğinde açılan kart ile:
     - **Mevcut Durum Açıklaması**
     - **Olasılık ($O: 1-5$)** ve **Şiddet ($Ş: 1-5$)** seçimi ile anlık **Risk Skoru ($R = O \times Ş$)** ve renkli risk düzeyi rozeti (*Kabul edilebilir*, *Önemli*, *Dikkate Değer*, *Kabul Edilemez*) hesaplanır.
     - **Alınacak Önlemler / İyileştirmeler**, **Sorumlu Birim** ve **Termin / Süre** kaydedilir.
4. **Resmi İSG Risk Analiz Formu Raporlama**: Yapılan denetimlerin detayı ve PDF / Excel / Word çıktıları resmi üniversite ve hastane İSG Birimi formatında 12 sütunlu tam matris olarak üretilir.
5. **Görsel Denetim Sihirbazı & Birim Yönetimi**: Denetim başlatılırken anket profilleri ve birimler etkileşimli görsel kartlarla seçilir.
6. **Dokunulmaz Master Admin Koruması**: `admin` (ID: 1) hesabı hiçbir yetkili tarafından silinemez, pasife alınamaz veya rolü değiştirilemez.

---

## 2. Veritabanı Şeması (MySQL)

```sql
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

-- 5. Anket Profilleri / Şablonları (Hastane İSG, Fabrika İSG vb.)
CREATE TABLE IF NOT EXISTS `survey_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `category` VARCHAR(50) DEFAULT 'Genel',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Anket Soruları Tablosu (Tehlike Kaynağı, Tehlike, Risk ve Etkilenenler Alanları ile)
CREATE TABLE IF NOT EXISTS `survey_questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `risk_group_id` INT NULL,
  `question_text` TEXT NOT NULL,
  `hazard_source` VARCHAR(255) NULL,
  `hazard_name` VARCHAR(255) NULL,
  `affected_risk` TEXT NULL,
  `affected_people` VARCHAR(255) NULL,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`template_id`) REFERENCES `survey_templates`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`risk_group_id`) REFERENCES `risk_groups`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Soru Seçenekleri ve Puan Tablosu
CREATE TABLE IF NOT EXISTS `question_options` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question_id` INT NOT NULL,
  `option_text` VARCHAR(255) NOT NULL,
  `points` INT NOT NULL DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`question_id`) REFERENCES `survey_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Sahada Yapılan Denetimler Tablosu
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

-- 9. Denetimde Seçilen Cevaplar & Risk Analiz Detayları Tablosu
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

-- 10. Sistem İşlem Logları Tablosu
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
```

---

## 3. Proje Dizini Yapısı

```
tubisg/
├── TUBISG_PROJECT_SPEC.md  # Yapay zekalar için ana teknik kılavuz
├── DEVELOPMENT_LOG.md      # Yapılan tüm güncellemeler ve durum takibi
├── database.sql            # Veritabanı tablo ve örnek veri kurulum dosyası
├── config/
│   └── db.php              # PDO Veritabanı bağlantısı ve auto-table init / migration
├── includes/
│   ├── auth.php            # Oturum, yetki ve log_action helper'ları
│   ├── header.php          # Üst menü, profil bağlantısı & sol navigasyon
│   └── footer.php          # Doğal sayfa sonu footer & mobil bottom nav
├── assets/
│   ├── css/
│   │   └── style.css       # Mobil öncelikli modern Glassmorphic CSS
│   └── js/
│       └── main.js         # Canlı hesaplayıcı, Otomatik Alert Kapatma & SweetAlert2 Modalları
├── index.php               # Tanıtıcı Ana Sayfa (Public Landing Page)
├── dashboard.php           # Giriş Yapmış Kullanıcı Kontrol Paneli
├── login.php               # Kullanıcı Girişi
├── logout.php              # Oturumu Kapat
├── profile.php             # Kullanıcı Profil & Şifre Güncelleme Ekranı
├── logs.php                # Sistem İşlem Logları Ekranı (Filtreli & Sayfalamalı)
├── risk_groups.php         # İSG Risk Grupları Tanımlama Paneli
├── survey_templates.php    # Anket Profilleri Listesi
├── survey_edit.php         # Anket Soruları, Tehlike & Risk Haritası Editörü
├── units.php               # Birim Yönetimi
├── audit_new.php           # Saha Denetim Görsel Sihirbazı
├── audit_fill.php          # Saha Denetim & 5x5 Risk Matrisi Doldurma Ekranı
├── audits_list.php         # Tamamlanan Denetimler Listesi (Sayfalamalı)
├── audit_detail.php        # Resmi İSG Birim Bazlı Risk Analiz Formu ve Karnesi
├── export.php              # Resmi Form Formatında PDF / Excel / Word İhracat Motoru
├── roles.php               # Rol & Yetki Tanımlama Paneli (RBAC + audit_delete & logs_view)
└── users.php               # Kullanıcı Hesapları Paneli (Master Admin Korumalı)
```
