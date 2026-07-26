# Tubİsg - Geliştirme Günlüğü (Development Log)

Tüm geliştirmeler, eklenen/güncellenen dosyalar mevcuttur.

---

## 📌 Mevcut Durum Özeti (Current Status)
- **Tarih**: 26 Temmuz 2026
- **Aşama**: Kağıt Belgenizdeki 12 Sütunlu Birebir Resmi "Birim Bazlı Risk Analiz Formu" Matris Editörü (`survey_edit.php`), Pop-Up Risk Grubu Seçimi ve Saha Denetim Mimarisi Tamamlandı.
- **Aktif Sürüm**: v3.5.0

---

## 📅 Geliştirme Adımları

### 🟢 Adım 1: Resmi 12 Sütunlu Matris Editörü (`survey_edit.php`)
- [x] Anket editörü ekranı eski soru tasarımından tamamen çıkarılarak yüklediğiniz kağıt formdaki 12 sütunlu birebir İSG Risk Analizi tablosuna dönüştürüldü.
- [x] Her risk satırında:
  1. Risk Grubu (Pop-Up Modal veya listeden seçim)
  2. Tehlike Kaynağı (Kütüphaneden önerili)
  3. Tehlike Metni (Kütüphaneden önerili)
  4. Etkilenme (Yaşanabilecek Riskler)
  5. Etkilenenler (Kütüphaneden önerili)
  6. Mevcut Durum / Saha Tespiti
  7. Olasılık ($O: 1-5$)
  8. Şiddet ($Ş: 1-5$)
  9. Risk Derecesi ($R = O \times Ş$) (Canlı renkli rozet hesaplama)
  10. Alınacak Önlemler / İyileştirmeler (Kütüphaneden önerili)
  11. Sorumlu Birim / Kişi (Kütüphaneden önerili)
  12. Başlama / Süre
  alanları eksiksiz kuruldu.

### 🟢 Adım 2: Pop-Up Modal ile Risk Grubu Seçimi
- [x] "Pop-Up Risk Grubu Seç" butonuna basıldığında açılan modern modal ile tanımlı risk gruplarının kartlar halinde seçilebilmesi sağlandı.

### 🟢 Adım 3: Loglama ve Otomatik Push
- [x] Tüm güncellemeler `system_logs` tablosuna kaydedildi.
- [x] Proje dosyaları otomatik olarak GitHub'a commit ve push edildi.

---

## 🔍 Sonraki Yapay Zeka Devralma Talimatı
Tüm geliştirmeler tamamlanmış olup 0 hata ile yayındadır.
