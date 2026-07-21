# Tubİsg - Geliştirme Günlüğü (Development Log)

Tüm geliştirmeler, eklenen/güncellenen dosyalar mevcuttur.

---

## 📌 Mevcut Durum Özeti (Current Status)
- **Tarih**: 21 Temmuz 2026
- **Aşama**: Kullanıcı, Anket Profili ve Birim Silme İşlemlerindeki Yabancı Anahtar (FK 1451) Hataları Çözüldü, Tüm İşlemler Sistem Loglarına Bağlanarak GitHub'a Gönderildi.
- **Aktif Sürüm**: v1.7.0

---

## 📅 Geliştirme Adımları

### 🟢 Adım 1: Yabancı Anahtar (FK 1451) Silme Hatalarının Çözümü (`users.php`, `survey_templates.php`, `units.php`)
- [x] Ekran görüntülerinde tespit edilen `SQLSTATE[23000]: 1451 Cannot delete or update a parent row` hataları giderildi.
- [x] Bir kullanıcı, anket profili veya birim silinirken ilişkili denetimlerin sırasıyla temizlenmesi ve ardından ana kaydın pürüzsüzce silinmesi sağlandı.

### 🟢 Adım 2: Kapsamlı İşlem Loglama (`log_action`)
- [x] Kullanıcı ekleme, kullanıcı silme, pasife alma, anket şablonu silme, soruları güncelleme, birim ekleme/silme gibi tüm yönetimsel işlemler kullanıcı adı ve detay bilgisiyle `system_logs` veritabanına loglanmaktadır.

---

## 🔍 Sonraki Yapay Zeka Devralma Talimatı
Tüm geliştirmeler tamamlanmış olup 0 hata ile yayındadır.
