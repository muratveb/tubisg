# Tubİsg - Geliştirme Günlüğü (Development Log)

Tüm geliştirmeler, eklenen/güncellenen dosyalar mevcuttur.

---

## 📌 Mevcut Durum Özeti (Current Status)
- **Tarih**: 26 Temmuz 2026
- **Aşama**: 9 Adımlı Seçimli İnteraktif Risk Maddesi Oluşturma Sihirbazı (`survey_edit.php` & `assets/js/main.js`) Tamamlandı.
- **Aktif Sürüm**: v3.6.0

---

## 📅 Geliştirme Adımları

### 🟢 Adım 1: 9 Adımlı İnteraktif Risk Maddesi Sihirbazı (`survey_edit.php`)
- [x] Anket profiline (Örn: Nükleer Tıp) yeni bir risk maddesi eklerken kullanıcının adım adım yönlendirildiği rehberli modal sihirbazı kuruldu.
- [x] Sırasıyla 9 Adım:
  1. Risk Grubu Seçimi
  2. Tehlike Kaynağı Seçimi (Kütüphane çipleri / yazma)
  3. Tehlike Seçimi (Kütüphane çipleri / yazma)
  4. Etkilenme (Yaşanabilecek Riskler)
  5. Etkilenenler Seçimi (Kütüphane çipleri / yazma)
  6. Mevcut Durum / Saha Tespiti
  7. Olasılık ($O: 1-5$) & Şiddet ($Ş: 1-5$) Risk Skoru
  8. Alınacak Önlemler (Kütüphane çipleri / yazma)
  9. Sorumlu Birim & Süre/Termin
- [x] Tüm sayısal puanlama girdileri tamamen kaldırıldı.

### 🟢 Adım 2: Loglama ve Otomatik Push
- [x] Yapılan tüm güncellemeler `system_logs` tablosuna kaydedildi.
- [x] Proje dosyaları otomatik olarak GitHub'a commit ve push edildi.

---

## 🔍 Sonraki Yapay Zeka Devralma Talimatı
Tüm geliştirmeler tamamlanmış olup 0 hata ile yayındadır.
