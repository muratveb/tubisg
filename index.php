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
      background: radial-gradient(circle at 50% 30%, #1e293b 0%, #0f172a 70%);
      color: #ffffff;
      padding: 90px 20px 70px 20px;
      border-radius: 0 0 32px 32px;
      box-shadow: 0 20px 50px rgba(15, 23, 42, 0.4);
      position: relative;
      overflow: hidden;
    }
    .landing-hero::before {
      content: '';
      position: absolute;
      top: -50%;
      left: 50%;
      transform: translateX(-50%);
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0) 70%);
      pointer-events: none;
    }
    .hero-title {
      font-size: clamp(2rem, 5vw, 3.5rem);
      font-weight: 800;
      letter-spacing: -1px;
      line-height: 1.15;
      color: #ffffff;
    }
    .hero-title .text-highlight {
      background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .feature-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: var(--radius-lg);
      padding: 32px 24px;
      height: 100%;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.3s ease;
    }
    .feature-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 35px -10px rgba(5, 150, 105, 0.15);
      border-color: #10b981;
    }
    .feature-icon-box {
      width: 60px;
      height: 60px;
      border-radius: 16px;
      background: #ecfdf5;
      color: #059669;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.75rem;
      margin-bottom: 22px;
      box-shadow: 0 4px 12px rgba(5, 150, 105, 0.15);
    }
    .hero-pill-badge {
      background: rgba(16, 185, 129, 0.15);
      color: #34d399;
      border: 1px solid rgba(52, 211, 153, 0.3);
      padding: 8px 18px;
      border-radius: 9999px;
      font-weight: 700;
      font-size: 0.8rem;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .hero-stat-item {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      padding: 12px 20px;
      border-radius: 14px;
      font-size: 0.85rem;
      font-weight: 700;
      color: #e2e8f0;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
  </style>
</head>
<body class="d-flex flex-column min-vh-100">

<!-- Header Navigation (Modern Header) -->
<nav class="navbar navbar-dark py-3 px-3 shadow-sm sticky-top" style="background:#0f172a !important; border-bottom: 1px solid rgba(255,255,255,0.08);">
  <div class="container d-flex align-items-center justify-content-between">
    <a href="index.php" class="brand-logo text-nowrap">
      <i class="bi bi-shield-check brand-icon"></i>
      <span class="brand-name">Tub<span class="text-success">İsg</span></span>
      <span class="brand-badge ms-1">Sahadaki Güç</span>
    </a>

    <div class="d-flex align-items-center gap-2">
      <a href="login.php" class="btn btn-success font-weight-bold px-4 py-2 fs-7 rounded-pill shadow-sm text-nowrap">
        <i class="bi bi-shield-lock-fill"></i> Saha Portalına Giriş
      </a>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<section class="landing-hero text-center position-relative">
  <div class="container py-4 position-relative" style="z-index: 2;">
    
    <div class="mb-4">
      <span class="hero-pill-badge">
        <i class="bi bi-lightning-charge-fill text-warning"></i> DİJİTAL SAHA İSG DÖNÜŞÜMÜ
      </span>
    </div>

    <h1 class="hero-title mb-3">
      Saha İSG Denetimlerini Akıllı, <br class="d-none d-md-inline"> 
      <span class="text-highlight">Hızlı ve Mobil Yönetin</span>
    </h1>

    <p class="lead max-w-2xl mx-auto opacity-90 mb-4 fs-6 text-light" style="max-width: 720px;">
      Tubİsg ile saha çalışanlarınız dokunmatik cep telefonu ve tabletlerden online anket doldursun, canlı skor hesabı anında yapılsın, kurumsal PDF, Excel ve Word denetim karneleriniz saniyeler içinde hazırlansın.
    </p>

    <div class="d-flex flex-wrap justify-content-center gap-3 mb-5">
      <a href="login.php" class="btn btn-success btn-lg fw-bold py-3 px-5 shadow-lg rounded-pill">
        <i class="bi bi-shield-lock-fill me-1"></i> Saha Portalına Giriş Yap
      </a>
    </div>

    <!-- Quick Key Features Stat Pills -->
    <div class="d-flex flex-wrap align-items-center justify-content-center gap-2">
      <div class="hero-stat-item"><i class="bi bi-phone text-success"></i> %100 Mobil & Tablet Uyumlu</div>
      <div class="hero-stat-item"><i class="bi bi-sliders text-success"></i> Dinamik Anket Profilleri</div>
      <div class="hero-stat-item"><i class="bi bi-calculator text-success"></i> Anlık Canlı Skorlama</div>
      <div class="hero-stat-item"><i class="bi bi-file-earmark-pdf text-success"></i> PDF / Excel / Word Raporlama</div>
    </div>

  </div>
</section>

<!-- Features Grid -->
<div class="container my-5 flex-grow-1">
  <div class="text-center mb-5">
    <span class="badge bg-success-light text-success font-weight-bold px-3 py-2 rounded-pill fs-8 mb-2">GÜÇLÜ ÖZELLİKLER</span>
    <h2 class="fw-extrabold text-dark display-6">Neden Tubİsg Platformu?</h2>
    <p class="text-muted fs-7 max-w-xl mx-auto">İş Sağlığı ve Güvenliği profesyonelleri için uçtan uca pratik ve modern saha denetim çözümü</p>
  </div>

  <div class="row g-4 mb-5">
    <!-- Feature 1 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-phone"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">Mobil ve Tablet Uyumlu UX</h5>
        <p class="text-muted fs-7 m-0">Saha çalışanları dokunmatik telefon ve tabletlerden anketleri sorunsuz doldurur. Büyük temas alanlı butonlar ve hızlı etkileşimler ile sahadaki pratikliği artırır.</p>
      </div>
    </div>

    <!-- Feature 2 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-sliders"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">Dinamik Soru & Esnek Puanlama</h5>
        <p class="text-muted fs-7 m-0">Farklı tesisler (Hastane, Fabrika, Şantiye vb.) için özel anket profilleri tanımlayın. Her cevaba pozitif (+5, +10) veya negatif (-5, -10) puanlar atayın.</p>
      </div>
    </div>

    <!-- Feature 3 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-calculator"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">Canlı Skor ve Risk Analizi</h5>
        <p class="text-muted fs-7 m-0">Denetim esnasında ekranın altında anlık canlı skor rozeti ve yüzdesel uygunluk çubuğu hesaplanır. Tamamlandığında sahanın genel risk karnesi oluşur.</p>
      </div>
    </div>

    <!-- Feature 4 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-building-add"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">Birim Yönetimi & Hızlı Ekleme</h5>
        <p class="text-muted fs-7 m-0">Faturalama Birimi, Ameliyathane, Depo vb. alanları tanımlayın. Denetçi sahada izin dahilinde anında modal ile yeni birim ekleyip denetime devam eder.</p>
      </div>
    </div>

    <!-- Feature 5 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-shield-lock"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">Gelişmiş Rol Tabanlı Yetki (RBAC)</h5>
        <p class="text-muted fs-7 m-0">Yönetici paneli üzerinden kullanıcıların anket tanımlama, birim ekleme, denetim yapma veya rapor silme yetkilerini ayrı ayrı yönetin.</p>
      </div>
    </div>

    <!-- Feature 6 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-file-earmark-arrow-down"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">PDF, Excel ve Word Raporlama</h5>
        <p class="text-muted fs-7 m-0">Denetim sonuçlarını ve saha karnelerini tek tıkla PDF, Excel veya Word belgesi olarak indirin veya yazdırılabilir formatta çıktı alın.</p>
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
