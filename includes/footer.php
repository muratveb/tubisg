<?php
/**
 * Tubİsg - Application Footer & Mobile Navigation Layout
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
    </div> <!-- /.page-container -->

    <!-- Global Fixed Footer Bar (All pages, desktop & mobile) -->
    <footer class="global-footer-bar">
      <div class="container-fluid">
        © <?php echo date('Y'); ?> TUBİSG <span style="color: #ffffff; font-weight: 800; font-size: 0.85rem; margin-left: 3px;">Sahadaki Güç</span> &nbsp;|&nbsp; Powered By <a href="https://www.muratyalcin.com.tr" target="_blank" rel="noopener noreferrer" class="footer-author-link">Murat Yalçın</a>
      </div>
    </footer>

  </main> <!-- /.main-content -->
</div> <!-- /.app-wrapper -->

<!-- Mobile Touch Bottom Navigation Bar (visible < 992px for logged-in users) -->
<?php if (is_logged_in()): ?>
<nav class="mobile-bottom-nav">
  <a href="dashboard.php" class="bottom-nav-item <?php echo ($currentPage == 'dashboard.php' || $currentPage == 'index.php') ? 'active' : ''; ?>">
    <i class="bi bi-house-door-fill"></i>
    <span>Ana Sayfa</span>
  </a>

  <?php if (has_permission('audit_view')): ?>
  <a href="audits_list.php" class="bottom-nav-item <?php echo ($currentPage == 'audits_list.php' || $currentPage == 'audit_detail.php') ? 'active' : ''; ?>">
    <i class="bi bi-clipboard-check-fill"></i>
    <span>Denetimler</span>
  </a>
  <?php endif; ?>

  <?php if (has_permission('audit_conduct')): ?>
  <a href="audit_new.php" class="bottom-nav-item action-btn" title="Yeni Saha Denetimi">
    <i class="bi bi-plus-lg fs-3"></i>
  </a>
  <?php endif; ?>

  <?php if (has_permission('units_manage')): ?>
  <a href="units.php" class="bottom-nav-item <?php echo $currentPage == 'units.php' ? 'active' : ''; ?>">
    <i class="bi bi-building"></i>
    <span>Birimler</span>
  </a>
  <?php endif; ?>

  <?php if (has_permission('surveys_manage')): ?>
  <a href="survey_templates.php" class="bottom-nav-item <?php echo ($currentPage == 'survey_templates.php' || $currentPage == 'survey_edit.php') ? 'active' : ''; ?>">
    <i class="bi bi-sliders"></i>
    <span>Anketler</span>
  </a>
  <?php endif; ?>
</nav>
<?php endif; ?>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 (Modern Onay ve Uyarı İletişim Kutuları) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Tubİsg Main JS -->
<script src="assets/js/main.js"></script>
</body>
</html>
