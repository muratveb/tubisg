<?php
/**
 * Tubİsg - Modern Tanıtıcı Ana Sayfa (Public Landing Page)
 */
require_once __DIR__ . '/includes/auth.php';

// Eğer kullanıcı giriş yapmışsa doğrudan Kontrol Paneline yönlendir
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$pageTitle = 'Tubİsg - Saha İş Sağlığı ve Güvenliği Platformu';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Tubİsg - Saha İş Sağlığı ve Güvenliği Denetim Portalı</title>
  
  <!-- Bootstrap 5 CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
  
  <!-- Custom Modern CSS System -->
  <link href="assets/css/style.css" rel="stylesheet">
  
  <style>
    .landing-hero {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 65%, #059669 100%);
      color: #ffffff;
      padding: 48px 20px 48px 20px;
      border-radius: 0 0 24px 24px;
      box-shadow: 0 16px 36px rgba(15, 23, 42, 0.35);
      position: relative;
    }
    .landing-hero-title {
      font-size: clamp(1.75rem, 4.5vw, 2.8rem);
      font-weight: 800;
      letter-spacing: -0.5px;
      line-height: 1.2;
      color: #ffffff;
    }
    .feature-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: var(--radius-lg);
      padding: 24px 20px;
      height: 100%;
      box-shadow: 0 6px 16px -4px rgba(0, 0, 0, 0.05);
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }
    .feature-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 16px 28px -6px rgba(5, 150, 105, 0.15);
      border-color: #10b981;
    }
    .feature-icon-box {
      width: 52px;
      height: 52px;
      border-radius: 14px;
      background: #ecfdf5;
      color: #059669;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 16px;
      box-shadow: 0 4px 10px rgba(5, 150, 105, 0.12);
    }
    .hero-stat-pill {
      background: rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(6px);
      border: 1px solid rgba(255, 255, 255, 0.15);
      padding: 8px 16px;
      border-radius: 9999px;
      font-size: 0.8rem;
      font-weight: 700;
      color: #f1f5f9;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
  </style>
</head>
<body class="d-flex flex-column min-vh-100">

<!-- Header Navigation (Mobilde Tek Satırda Kalan Esnek Header) -->
<nav class="navbar navbar-dark py-2 px-3 sticky-top" style="background:#0f172a !important; border-bottom: 1px solid rgba(255,255,255,0.08);">
  <div class="container d-flex align-items-center justify-content-between flex-nowrap">
    <a href="index.php" class="brand-logo text-nowrap me-2">
      <i class="bi bi-shield-check brand-icon fs-3"></i>
      <span class="brand-name fs-5">Tub<span class="text-success">İsg</span></span>
      <span class="brand-badge ms-1 d-none d-sm-inline">Sahadaki Güç</span>
    </a>

    <div class="d-flex align-items-center flex-shrink-0">
      <a href="login.php" class="btn btn-success font-weight-bold px-3 py-1-5 fs-7 rounded-pill shadow-sm text-nowrap">
        <i class="bi bi-shield-lock-fill"></i> Giriş Yap
      </a>
    </div>
  </div>
</nav>

<!-- Hero Section (Kompakt ve Şık Görünüm) -->
<section class="landing-hero text-center">
  <div class="container">
    
    <div class="mb-3">
      <span class="badge bg-success-light text-success border border-success font-weight-bold px-3 py-2 rounded-pill fs-8">
        <i class="bi bi-lightning-charge-fill text-warning"></i> DİJİTAL SAHA İSG DÖNÜŞÜMÜ
      </span>
    </div>

    <h1 class="landing-hero-title mb-3">
      Saha İSG Denetimlerini Akıllı, <br class="d-none d-md-inline"> 
      <span class="text-success" style="color: #34d399 !important;">Hızlı ve Mobil Yönetin</span>
    </h1>

    <p class="text-light opacity-90 mx-auto mb-4 fs-6" style="max-width: 680px; font-size: 0.95rem;">
      Tubİsg ile saha çalışanlarınız dokunmatik cep telefonu ve tabletlerden online anket doldursun, canlı skor hesabı anında yapılsın, kurumsal PDF, Excel ve Word denetim karneleriniz saniyeler içinde hazırlansın.
    </p>

    <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
      <a href="login.php" class="btn btn-success btn-lg fw-bold px-4 py-3 rounded-pill shadow-lg fs-6">
        <i class="bi bi-shield-lock-fill me-1"></i> Saha Portalına Giriş Yap
      </a>
    </div>

    <!-- Hızlı Özellik Hapları -->
    <div class="d-flex flex-wrap align-items-center justify-content-center gap-2">
      <div class="hero-stat-pill"><i class="bi bi-phone text-success"></i> %100 Mobil & Tablet Uyumlu</div>
      <div class="hero-stat-pill"><i class="bi bi-sliders text-success"></i> Dinamik Anket Profilleri</div>
      <div class="hero-stat-pill"><i class="bi bi-calculator text-success"></i> Anlık Canlı Skorlama</div>
      <div class="hero-stat-pill"><i class="bi bi-file-earmark-pdf text-success"></i> PDF / Excel Raporlama</div>
    </div>

  </div>
</section>

<!-- Features Grid -->
<div class="container my-5 flex-grow-1">
  <div class="text-center mb-4">
    <span class="badge bg-success-light text-success font-weight-bold px-3 py-2 rounded-pill fs-8 mb-2">GÜÇLÜ ÖZELLİKLER</span>
    <h2 class="fw-extrabold text-dark h3 m-0">Neden Tubİsg Platformu?</h2>
    <p class="text-muted fs-7 max-w-xl mx-auto m-0 mt-1">İş Sağlığı ve Güvenliği profesyonelleri için uçtan uca pratik ve modern saha denetim çözümü</p>
  </div>

  <div class="row g-3 mb-4">
    <!-- Feature 1 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-phone"></i>
        </div>
        <h6 class="fw-bold text-dark mb-2 fs-6">Mobil ve Tablet Uyumlu UX</h6>
        <p class="text-muted fs-8 m-0">Saha çalışanları dokunmatik telefon ve tabletlerden anketleri sorunsuz doldurur. Büyük temas alanlı butonlar ile sahadaki pratikliği artırır.</p>
      </div>
    </div>

    <!-- Feature 2 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-sliders"></i>
        </div>
        <h6 class="fw-bold text-dark mb-2 fs-6">Dinamik Soru & Esnek Puanlama</h6>
        <p class="text-muted fs-8 m-0">Farklı tesisler (Hastane, Fabrika, Şantiye vb.) için özel anket profilleri tanımlayın. Her cevaba pozitif (+5, +10) veya negatif (-5, -10) puanlar atayın.</p>
      </div>
    </div>

    <!-- Feature 3 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-calculator"></i>
        </div>
        <h6 class="fw-bold text-dark mb-2 fs-6">Canlı Skor ve Risk Analizi</h6>
        <p class="text-muted fs-8 m-0">Denetim esnasında ekranın altında anlık canlı skor rozeti ve yüzdesel uygunluk çubuğu hesaplanır. Tamamlandığında sahanın genel risk karnesi oluşur.</p>
      </div>
    </div>

    <!-- Feature 4 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-building-add"></i>
        </div>
        <h6 class="fw-bold text-dark mb-2 fs-6">Birim Yönetimi & Hızlı Ekleme</h6>
        <p class="text-muted fs-8 m-0">Faturalama Birimi, Ameliyathane, Depo vb. alanları tanımlayın. Denetçi sahada izin dahilinde anında modal ile yeni birim ekleyip denetime devam eder.</p>
      </div>
    </div>

    <!-- Feature 5 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-shield-lock"></i>
        </div>
        <h6 class="fw-bold text-dark mb-2 fs-6">Gelişmiş Rol Tabanlı Yetki (RBAC)</h6>
        <p class="text-muted fs-8 m-0">Yönetici paneli üzerinden kullanıcıların anket tanımlama, birim ekleme, denetim yapma veya rapor silme yetkilerini ayrı ayrı yönetin.</p>
      </div>
    </div>

    <!-- Feature 6 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-file-earmark-arrow-down"></i>
        </div>
        <h6 class="fw-bold text-dark mb-2 fs-6">PDF, Excel ve Word Raporlama</h6>
        <p class="text-muted fs-8 m-0">Denetim sonuçlarını ve saha karnelerini tek tıkla PDF, Excel veya Word belgesi olarak indirin veya yazdırılabilir formatta çıktı alın.</p>
      </div>
    </div>
  </div>
</div>

<!-- Global Fixed Footer Bar -->
<footer class="global-footer-bar mt-auto">
  <div class="container-fluid">
    © <?php echo date('Y'); ?> TUBİSG <span style="color: #ffffff; font-weight: 800; font-size: 0.85rem; margin-left: 3px;">Sahadaki Güç</span> &nbsp;|&nbsp; Powered By <a href="https://www.muratyalcin.com.tr" target="_blank" rel="noopener noreferrer" class="footer-author-link">Murat Yalçın</a>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
