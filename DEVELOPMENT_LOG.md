# Tubİsg - Geliştirme Günlüğü (Development Log)

Tüm geliştirmeler, eklenen/güncellenen dosyalar mevcuttur.

---

## 📌 Mevcut Durum Özeti (Current Status)
- **Tarih**: 26 Temmuz 2026
- **Aşama**: Sayısal Puanlama Sisteminin Kaldırılması, İSG Uzmanı Risk Skoru ($R = O \times Ş$) Mimarisinin Tam Geçişi ve Anket Editörüne Kütüphaneden Doğrudan Seçim & Hızlı Öğe Ekleme Modalinin Entegre Edilmesi Tamamlandı.
- **Aktif Sürüm**: v3.2.0

---

## 📅 Geliştirme Adımları

### 🟢 Adım 1: Eski Sayısal Puanlama Sisteminin Temizlenmesi
- [x] Soru seçeneklerindeki sayısal puan girişleri (0, 5, 10 vb.) ve genelleştirilmiş yüzde skor gösterimleri kaldırıldı.
- [x] Kontrol paneli, denetim listesi ve raporlar tamamen İSG Uzmanı tarafından verilen **Olasılık ($O: 1-5$)** ve **Şiddet ($Ş: 1-5$)** ile hesaplanan **Risk Derecesi ($R = O \times Ş$)** odaklı yapıya geçirildi.

### 🟢 Adım 2: Anket Editöründe Kütüphane Seçimi & Hızlı Ekleme (`survey_edit.php`)
- [x] Soru oluştururken/düzenlerken **Tehlike Kaynağı**, **Tehlike Metni**, **Etkilenen Gruplar** alanları kütüphaneden doğrudan seçilebilir yapıldı.
- [x] Anket editörü ekranından ayrılmadan kütüphaneye yeni öge eklemeyi sağlayan hızlı modal formu eklendi.

### 🟢 Adım 3: İşlem Logları & Otomatik Öğrenme
- [x] Kütüphaneye eklenen her öge ve anket değişikliği `system_logs` tablosuna `log_action()` ile kaydedildi.
- [x] Sahada ilk kez yazılan yeni bir önlem veya sorumlu sisteme kaydedilerek kütüphanenin kendini sürekli güncel tutması sağlandı.

---

## 🔍 Sonraki Yapay Zeka Devralma Talimatı
Tüm geliştirmeler tamamlanmış olup 0 hata ile yayındadır.
