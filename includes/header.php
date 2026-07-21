<?php
/**
 * Tubİsg - Responsive Application Header & Navigation Layout
 */
require_once __DIR__ . '/auth.php';

$user = get_current_user_data();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | Tubİsg' : 'Tubİsg - Saha İSG Anket & Denetim Sistemi'; ?></title>
  
  <!-- Bootstrap 5 CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
  
  <!-- Custom Modern CSS System -->
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="<?php echo is_logged_in() ? 'logged-in-mobile' : ''; ?>">

<div class="app-wrapper">

  <!-- Desktop & Tablet Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <a href="dashboard.php" class="brand-logo">
        <i class="bi bi-shield-check text-success fs-3"></i>
        <span>Tub<span class="text-success">İsg</span></span>
        <span class="brand-badge">Saha v1.0</span>
      </a>
    </div>

    <div class="sidebar-nav">
      <div class="nav-category">Ana Menü</div>
      <a href="dashboard.php" class="nav-link-custom <?php echo ($currentPage == 'dashboard.php' || $currentPage == 'index.php') ? 'active' : ''; ?>">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Kontrol Paneli</span>
      </a>

      <?php if (has_permission('audit_conduct')): ?>
      <a href="audit_new.php" class="nav-link-custom <?php echo ($currentPage == 'audit_new.php' || $currentPage == 'audit_fill.php') ? 'active' : ''; ?>">
        <i class="bi bi-plus-circle-fill text-success"></i>
        <span>Yeni Saha Denetimi</span>
      </a>
      <?php endif; ?>

      <?php if (has_permission('audit_view')): ?>
      <a href="audits_list.php" class="nav-link-custom <?php echo ($currentPage == 'audits_list.php' || $currentPage == 'audit_detail.php') ? 'active' : ''; ?>">
        <i class="bi bi-clipboard2-data-fill"></i>
        <span>Denetim Raporları</span>
      </a>
      <?php endif; ?>

      <div class="nav-category">Yönetim & Tanımlar</div>

      <?php if (has_permission('surveys_manage')): ?>
      <a href="survey_templates.php" class="nav-link-custom <?php echo ($currentPage == 'survey_templates.php' || $currentPage == 'survey_edit.php') ? 'active' : ''; ?>">
        <i class="bi bi-journal-text"></i>
        <span>Anket Profilleri</span>
      </a>
      <?php endif; ?>

      <?php if (has_permission('units_manage')): ?>
      <a href="units.php" class="nav-link-custom <?php echo $currentPage == 'units.php' ? 'active' : ''; ?>">
        <i class="bi bi-building-gear"></i>
        <span>Birim Tanımları</span>
      </a>
      <?php endif; ?>

      <?php if (has_permission('users_manage')): ?>
      <div class="nav-category">Sistem & Yetki</div>
      <a href="users.php" class="nav-link-custom <?php echo $currentPage == 'users.php' ? 'active' : ''; ?>">
        <i class="bi bi-people-fill"></i>
        <span>Kullanıcı Yönetimi</span>
      </a>
      <a href="roles.php" class="nav-link-custom <?php echo $currentPage == 'roles.php' ? 'active' : ''; ?>">
        <i class="bi bi-shield-lock-fill"></i>
        <span>Rol & Yetkiler</span>
      </a>
      <?php endif; ?>

      <div class="nav-category">Oturum</div>
      <a href="logout.php" class="nav-link-custom text-danger">
        <i class="bi bi-box-arrow-right"></i>
        <span>Çıkış Yap</span>
      </a>
    </div>
  </aside>

  <!-- Main Content Wrapper -->
  <main class="main-content">
    
    <!-- Top Navbar -->
    <header class="top-navbar">
      <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggleBtn" class="btn btn-light d-lg-none border-0 fs-4 p-1">
          <i class="bi bi-list"></i>
        </button>
        <h5 class="m-0 fw-bold d-none d-sm-block text-secondary fs-6">
          <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'İSG Saha Platformu'; ?>
        </h5>
      </div>

      <div class="d-flex align-items-center gap-3">
        <?php if ($user): ?>
        <div class="user-profile-badge">
          <div class="avatar-circle">
            <?php echo mb_substr($user['name_surname'], 0, 1, 'UTF-8'); ?>
          </div>
          <div class="d-none d-md-block text-start">
            <div class="fw-bold fs-7 leading-tight"><?php echo htmlspecialchars($user['name_surname']); ?></div>
            <div class="text-muted fs-8" style="font-size:0.7rem;"><?php echo htmlspecialchars($user['role_name']); ?></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </header>

    <!-- Page Container -->
    <div class="page-container">
      <?php display_flash(); ?>
