# Tubİsg - Geliştirme Günlüğü (Development Log)

Tüm geliştirmeler, eklenen/güncellenen dosyalar mevcuttur.

---

## 📌 Mevcut Durum Özeti (Current Status)
- **Tarih**: 26 Temmuz 2026
- **Aşama**: Kurum Kartlarından Doğrudan Filtreli Denetim Raporları Ekranına (`audits_list.php?institution_id=X`) Bağlantı Özelliği Tamamlandı.
- **Aktif Sürüm**: v5.8.0

---

## 📅 Geliştirme Adımları

### 🟢 Adım 1: Kurum Bazlı Denetim Filtreleme ([audits_list.php](file:///Applications/MAMP/htdocs/tubisg/audits_list.php))
- [x] `audits_list.php` sayfasına `institution_id` parametresi ile otomatik kurum filtreleme mantığı ve üst kısma bilgilendirici kırmızı **FİLTRELENEN KURUM** bildirim kartı eklendi.
- [x] Filtreler arasına **Kurum Seçim Dropdown** menüsü entegre edildi.

### 🟢 Adım 2: Kurum Kartı Tıklama Bağlantısı ([institutions.php](file:///Applications/MAMP/htdocs/tubisg/institutions.php))
- [x] Kurum kartlarındaki **Denetim Sayısı Pili** (`[ 📋 X Denetim → ]`) ve **Kurum Başlığı/Avatarı** doğrudan ilgili kuruma ait denetim raporlarını açacak şekilde `audits_list.php?institution_id=X` bağlantısıyla ilişkilendirildi.

---

## 🔍 Sonraki Yapay Zeka Devralma Talimatı
Tüm geliştirmeler tamamlanmış olup 0 hata ile yayındadır.
