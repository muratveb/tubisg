<?php
/**
 * Tubİsg - Tanıtıcı Ana Sayfa & Oturum Kontrolü
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
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #059669 100%);
      color: #ffffff;
      padding: 80px 20px;
      border-radius: 0 0 30px 30px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }
    .feature-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: var(--radius-lg);
      padding: 30px 24px;
      height: 100%;
      box-shadow: var(--shadow-md);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .feature-card:hover {
      transform: translateY(-6px);
      box-shadow: var(--shadow-lg);
      border-color: #10b981;
    }
    .feature-icon-box {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      background: var(--primary-light);
      color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      margin-bottom: 20px;
    }
  </style>
</head>
<body class="d-flex flex-column min-vh-100 pb-5">

<!-- Header Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3 px-3 shadow-sm sticky-top" style="background:#0f172a !important;">
  <div class="container">
    <a href="index.php" class="brand-logo me-auto">
      <i class="bi bi-shield-check text-success fs-3"></i>
      <span>Tub<span class="text-success">İsg</span></span>
      <span class="brand-badge">Saha v1.0</span>
    </a>

    <div class="d-flex align-items-center">
      <a href="login.php" class="btn btn-success font-weight-bold px-4 py-2 fs-7 rounded-pill shadow-sm">
        <i class="bi bi-shield-lock-fill"></i> Saha Portalına Giriş
      </a>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<section class="landing-hero text-center position-relative overflow-hidden mb-5">
  <div class="container py-4">
    <span class="badge bg-success text-white px-3 py-2 rounded-pill font-weight-bold mb-3 fs-7">
      ⚡ DİJİTAL SAHA İSG DÖNÜŞÜMÜ
    </span>
    <h1 class="display-4 fw-extrabold mb-3">Saha İSG Denetimlerini Akıllı, Hızlı ve Mobil Yönetin</h1>
    <p class="lead max-w-2xl mx-auto opacity-90 mb-4 fs-6">
      Tubİsg ile saha çalışanlarınız telefon ve tabletten online anket doldursun, pozitif ve negatif seçenek puanlaması anında hesaplansın, kurumsal PDF, Excel ve Word raporlarınız saniyeler içinde hazır olsun.
    </p>

    <div class="d-flex flex-wrap justify-content-center gap-3">
      <a href="login.php" class="btn btn-light text-success btn-lg fw-bold py-3 px-5 shadow-lg rounded-pill">
        <i class="bi bi-shield-lock-fill fs-5"></i> Saha Portalına Giriş
      </a>
    </div>
  </div>
</section>

<!-- Features Grid -->
<div class="container my-4 flex-grow-1">
  <div class="text-center mb-5">
    <h2 class="fw-extrabold text-dark">Neden Tubİsg?</h2>
    <p class="text-muted fs-7">İş Sağlığı ve Güvenliği profesyonelleri için uçtan uca saha denetim çözümü</p>
  </div>

  <div class="row g-4 mb-5">
    <!-- Feature 1 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-phone"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">Mobil ve Tablet Uyumlu UX</h5>
        <p class="text-muted fs-7">Saha çalışanları dokunmatik telefon ve tabletlerden anketleri sorunsuz doldurur. Büyük butonlar ve hızlı etkileşimler ile sahadaki pratikliği artırır.</p>
      </div>
    </div>

    <!-- Feature 2 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-sliders"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">Dinamik Soru & Esnek Puanlama</h5>
        <p class="text-muted fs-7">Farklı tesisler (Hastane, Fabrika, Şantiye) için anket profilleri tanımlayın. Her seçeneğe pozitif (+5, +10) veya negatif (-5, -10) puanlar atayın.</p>
      </div>
    </div>

    <!-- Feature 3 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-calculator"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">Canlı Skor ve Risk Analizi</h5>
        <p class="text-muted fs-7">Denetim esnasında ekranın altında anlık canlı skor rozeti ve yüzdesel uygunluk çubuğu hesaplanır. Tamamlandığında sahanın genel risk karnesi oluşur.</p>
      </div>
    </div>

    <!-- Feature 4 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-building-add"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">Birim Yönetimi & Hızlı Ekleme</h5>
        <p class="text-muted fs-7">Faturalama Birimi, Ameliyathane, Depo vb. tanımlayın. Denetçi sahada izin dahilinde anında modal ile yeni birim tanımlayıp denetime devam edebilir.</p>
      </div>
    </div>

    <!-- Feature 5 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-shield-lock"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">Gelişmiş Rol Tabanlı Yetki (RBAC)</h5>
        <p class="text-muted fs-7">Yönetici paneli üzerinden her kullanıcının anket tanımlama, birim ekleme, denetim yapma veya rapor indirme yetkilerini ayrı ayrı yönetin.</p>
      </div>
    </div>

    <!-- Feature 6 -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-file-earmark-arrow-down"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">PDF, Excel ve Word Raporlama</h5>
        <p class="text-muted fs-7">Denetim sonuçlarını ve saha karnelerini tek tıkla PDF, Excel veya Word belgesi olarak indirin veya yazdırılabilir formatta çıktı alın.</p>
      </div>
    </div>
  </div>
</div>

<!-- Global Fixed Footer Bar -->
<footer class="global-footer-bar">
  <div class="container-fluid">
    © <?php echo date('Y'); ?> TUBİSG <span style="color: #ffffff; font-weight: 800; font-size: 0.85rem; margin-left: 3px;">Sahadaki Güç</span> &nbsp;|&nbsp; Powered By <a href="https://www.muratyalcin.com.tr" target="_blank" rel="noopener noreferrer" class="footer-author-link">Murat Yalçın</a>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
