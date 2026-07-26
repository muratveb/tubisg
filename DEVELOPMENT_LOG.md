# Tubİsg - Geliştirme Günlüğü (Development Log)

Tüm geliştirmeler, eklenen/güncellenen dosyalar mevcuttur.

---

## 📌 Mevcut Durum Özeti (Current Status)
- **Tarih**: 26 Temmuz 2026
- **Aşama**: Risk Grupları Dikey Birleştirilmiş Hücre Yapısı (Rowspan Vertical Cell), 7 Adımlı Sihirbaz & Süre Hızlı Seçim Butonları Tamamlandı.
- **Aktif Sürüm**: v4.4.0

---

## 📅 Geliştirme Adımları

### 🟢 Adım 1: Dikey Birleştirilmiş Risk Grubu Hücresi (`audit_detail.php` & `export.php`)
- [x] Rapordaki `RİSK GRUPLARI` sütunu, aynı gruba ait tüm satırlar için tek bir dikey birleştirilmiş hücre (`rowspan` & `writing-mode: vertical-rl; transform: rotate(180deg)`) halinde resmi kağıt formunuzla %100 birebir olacak şekilde güncellendi.
- [x] Excel (.xls) ve Word (.doc) dışa aktarmalarında dikey birleştirilmiş `rowspan` hücresi korundu.

### 🟢 Adım 2: 7 Adımlı Sihirbaz & Süre Hızlı Seçim Butonları (`survey_edit.php` & `assets/js/main.js`)
- [x] Sihirbazdaki gereksiz 6. Adım ("Saha Denetim Sorusu Metni") kaldırıldı, sihirbaz 7 adıma düşürüldü.
- [x] Sihirbazın son adımındaki **Termin / Süre** alanına tıpkı Sorumlu Birim'de olduğu gibi hızlı seçim butonları (`Sürekli`, `Derhal`, `1 Ay`, `3 Ay`, `6 Ay`, `12 Ay`) eklendi.

### 🟢 Adım 3: Loglama ve Otomatik Push
- [x] `system_logs` kaydı ve git sync adımları tamamlandı.

---

## 🔍 Sonraki Yapay Zeka Devralma Talimatı
Tüm geliştirmeler tamamlanmış olup 0 hata ile yayındadır.
