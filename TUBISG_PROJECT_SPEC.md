# Tubİsg - Proje Teknik Dokümantasyonu & Mimarisi (AI Context File)

> **Bu dosya, Tubİsg projesinin mimarisini, veritabanı yapısını, iş kurallarını ve yetkilendirme modelini her yapay zekanın (LLM) kolayca anlayabileceği şekilde özetler.**

---

## 1. Proje Amacı ve Genel Özet
**Tubİsg**, İş Sağlığı ve Güvenliği (İSG) saha denetçilerinin mobil cihaz veya tablet üzerinden sahadaki birimlerde (örn. Faturalama Birimi, Depo, Ameliyathane vb.) İSG denetimlerini online yapabilmesini sağlayan dinamik bir PHP + MySQL web uygulamasıdır.

### Temel Yetenekler:
1. **Dinamik Anket & Profil Tanımlama**: Yönetici farklı ortamlar için (örn. "Hastane İSG", "Fabrika Saha İSG") sınırsız anket profili tanımlayabilir.
2. **Çoklu Seçenek ve Esnek Puanlama**: Sorular altında seçilebilecek seçenekler tanımlanır. Her seçeneğin pozitif (`+5`, `+10`) veya negatif (`-5`, `-10`) bir puanı olabilir. Kullanıcı bir soruda birden fazla seçenek işaretleyebilir.
3. **Görsel Denetim Sihirbazı & Birim Yönetimi**: Denetim başlatılırken anket profilleri ve birimler etkileşimli görsel kartlarla seçilir. Yetki dahilinde anında yeni birim tanımlanabilir.
4. **Kullanıcı Profil Düzenleme**: Kullanıcılar sağ üst profil resmine veya sol menüdeki "Profilim" bağlantısına tıklayarak Ad Soyad ve Şifre bilgilerini güncelleyebilir (Kullanıcı adı ve E-Posta değiştirilemez).
5. **Mobil / Tablet Öncelikli UX**: Dokunmatik ekranlar için özel tasarlanmış, büyük temas alanlı, canlı skor rozetli modern Glassmorphic arayüz.
6. **Otomatik Kapanan Bildirimler**: Sistem genelindeki bildirim bantları 4 saniye sonra yumuşak animasyonla kendiliğinden kaybolur.
7. **Gelişmiş RBAC Yetkilendirme**: Yönetici, Süper Yönetici haricindeki kullanıcıların neyi yapıp yapamayacağını yetki tablosundan yönetebilir.
8. **Raporlama ve Export**: Yapılan denetimlerin karnesi, genel puan ortalaması, PDF, Excel (.xls), Word (.doc) ve Yazdırılabilir çıktı alma desteği.

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
```

---

## 3. Proje Dizini Yapısı

```
tubisg/
├── TUBISG_PROJECT_SPEC.md  # Yapay zekalar için ana teknik kılavuz
├── DEVELOPMENT_LOG.md      # Yapılan tüm güncellemeler ve durum takibi
├── database.sql            # Veritabanı tablo ve örnek veri kurulum dosyası
├── config/
│   └── db.php              # PDO Veritabanı bağlantısı
├── includes/
│   ├── auth.php            # Oturum ve yetki kontrolleri
│   ├── header.php          # Üst menü, profil bağlantısı & sol navigasyon
│   └── footer.php          # Doğal sayfa sonu footer & mobil bottom nav
├── assets/
│   ├── css/
│   │   └── style.css       # Mobil öncelikli modern Glassmorphic CSS
│   └── js/
│       └── main.js         # Canlı hesaplayıcı, Otomatik Alert Kapatma & Görsel Sihirbaz
├── index.php               # Tanıtıcı Ana Sayfa (Public Landing Page)
├── dashboard.php           # Giriş Yapmış Kullanıcı Kontrol Paneli
├── login.php               # Kullanıcı Girişi
├── logout.php              # Oturumu Kapat
├── profile.php             # Kullanıcı Profil & Şifre Güncelleme Ekranı
├── survey_templates.php    # Anket Profilleri Listesi
├── survey_edit.php         # Anket Soruları & Seçenek Puan Editörü
├── units.php               # Birim Yönetimi
├── audit_new.php           # Saha Denetim Görsel Sihirbazı
├── audit_fill.php          # Saha Denetim Doldurma Ekranı
├── audits_list.php         # Tamamlanan Denetimler Listesi
├── audit_detail.php        # Denetim Detayı & Karnesi
├── export.php              # PDF / Excel / Word İhracat İşleyicisi
├── roles.php               # Rol & Yetki Tanımlama Paneli (RBAC)
└── users.php               # Kullanıcı Hesapları Paneli
```
