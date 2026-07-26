# Tubİsg - Geliştirme Günlüğü (Development Log)

Tüm geliştirmeler, eklenen/güncellenen dosyalar mevcuttur.

---

## 📌 Mevcut Durum Özeti (Current Status)
- **Tarih**: 26 Temmuz 2026
- **Aşama**: Birim Bazlı İSG Risk Analiz ve Denetim Matrisi (5x5 L-Tipi Risk Skoru $R = O \times Ş$) Mimarisi ve Resmi 12 Sütunlu Form Raporlaması Tamamlandı.
- **Aktif Sürüm**: v3.0.0

---

## 📅 Geliştirme Adımları

### 🟢 Adım 1: İSG Risk Grupları Yönetimi (`risk_groups.php`)
- [x] Ergonomik, Biyolojik, Fiziksel, Kimyasal, Psikososyal vb. İSG risk gruplarını yönetmek için `risk_groups` veritabanı tablosu ve yönetim paneli oluşturuldu.
- [x] Sol navigasyon menüsüne "Risk Grupları" bağlantısı eklendi.

### 🟢 Adım 2: Tehlike & Risk Bankası Editörü (`survey_edit.php`)
- [x] Soru tanımlama ekranı genişletilerek sorular Risk Gruplarına bağlandı.
- [x] Her soruya özel **Tehlike Kaynağı**, **Tehlike**, **Etkilenme (Yaşanabilecek Riskler)** ve **Etkilenenler** alanları tanımlanabilir hale getirildi.

### 🟢 Adım 3: Saha Risk Denetimi & 5x5 Matrix Doldurma (`audit_fill.php`)
- [x] Saha denetiminde sorular risk grupları altında kategorize edildi.
- [x] *Evet*, *Hayır*, *Kısmen*, *Muaf* cevap seçenekleri sunuldu.
- [x] "Hayır" veya "Kısmen" tıklandığında **Mevcut Durum Açıklaması**, **Olasılık ($1-5$)**, **Şiddet ($1-5$)**, **Alınacak Önlemler**, **Sorumlu** ve **Termin/Süre** dinamik kartı aktifleştirildi.
- [x] Anlık Canlı $R = O \times Ş$ Risk Skoru rozeti (Kabul edilebilir, Önemli, Dikkate Değer, Kabul Edilemez) hesaplaması entegre edildi.

### 🟢 Adım 4: Resmi İSG Risk Analiz Formu Çıktısı (`audit_detail.php` & `export.php`)
- [x] Denetim detayı ve PDF, Excel, Word indirme motorları kullanıcının yüklediği resmi **"Birim Bazlı Risk Analiz Formu"** tablosuna (12 sütunlu tam matris) uygun olarak yenilendi.

---

## 🔍 Sonraki Yapay Zeka Devralma Talimatı
Tüm geliştirmeler tamamlanmış olup 0 hata ile yayındadır.
