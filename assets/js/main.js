/**
 * Tubİsg - Main Application JavaScript File
 * Interactive UX, Dynamic Wizard, SweetAlert2 Confirm Interceptor & Risk Matrix Calculator
 */

document.addEventListener('DOMContentLoaded', function () {
  
  // 1. Auto-hide alerts after 5 seconds
  const flashAlerts = document.querySelectorAll('.alert-dismissible');
  flashAlerts.forEach(function (alert) {
    setTimeout(function () {
      const bsAlert = new bootstrap.Alert(alert);
      if (bsAlert) bsAlert.close();
    }, 5000);
  });

  // 2. Sidebar Mobile Toggle
  const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
  const sidebar = document.querySelector('.sidebar');
  if (sidebarToggleBtn && sidebar) {
    sidebarToggleBtn.addEventListener('click', function () {
      sidebar.classList.toggle('show');
    });
  }

  // 3. Dynamic Question Builder for survey_edit.php
  const addQuestionBtn = document.getElementById('addQuestionBtn');
  if (addQuestionBtn) {
    initQuestionBuilder();
  }

  // 4. Quick AJAX Unit Creator
  const quickUnitForm = document.getElementById('quickUnitForm');
  if (quickUnitForm) {
    initQuickUnitCreator();
  }

  // 5. Interactive Visual Audit Wizard (audit_new.php)
  const wizardForm = document.getElementById('startAuditWizardForm');
  if (wizardForm) {
    initAuditWizard();
  }

  // 6. Global Modern SweetAlert2 Confirmation Dialog Handler
  initModernConfirmHandler();

});

/**
 * Global Modern SweetAlert2 Confirm Handler
 */
function initModernConfirmHandler() {
  document.addEventListener('submit', function (e) {
    const form = e.target;
    
    if (form.classList.contains('confirm-delete-form') || form.hasAttribute('data-confirm-title')) {
      if (form.dataset.confirmed === 'true') {
        return true;
      }
      
      e.preventDefault();
      
      const title = form.dataset.confirmTitle || 'Silme Onayı';
      const text = form.dataset.confirmText || 'Bu kaydı silmek istediğinize emin misiniz? Bu işlem geri alınamaz.';
      
      if (window.Swal) {
        Swal.fire({
          title: title,
          text: text,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#ef4444',
          cancelButtonColor: '#64748b',
          confirmButtonText: '<i class="bi bi-trash-fill me-1"></i> Evet, Sil!',
          cancelButtonText: 'Vazgeç',
          reverseButtons: true,
          customClass: {
            popup: 'swal2-custom-popup',
            confirmButton: 'btn btn-danger font-weight-bold px-4 py-2 me-2',
            cancelButton: 'btn btn-secondary font-weight-bold px-4 py-2'
          },
          buttonsStyling: false
        }).then((result) => {
          if (result.isConfirmed) {
            form.dataset.confirmed = 'true';
            form.submit();
          }
        });
      } else {
        if (confirm(text)) {
          form.dataset.confirmed = 'true';
          form.submit();
        }
      }
    }
  });

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-confirm-btn="true"]');
    if (btn) {
      if (btn.dataset.confirmed === 'true') {
        return true;
      }
      
      e.preventDefault();
      
      const title = btn.dataset.confirmTitle || 'İşlem Onayı';
      const text = btn.dataset.confirmText || 'Bu işlemi gerçekleştirmek istediğinize emin misiniz?';
      
      if (window.Swal) {
        Swal.fire({
          title: title,
          text: text,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#ef4444',
          cancelButtonColor: '#64748b',
          confirmButtonText: 'Evet, Devam Et',
          cancelButtonText: 'Vazgeç',
          reverseButtons: true,
          customClass: {
            popup: 'swal2-custom-popup',
            confirmButton: 'btn btn-danger font-weight-bold px-4 py-2 me-2',
            cancelButton: 'btn btn-secondary font-weight-bold px-4 py-2'
          },
          buttonsStyling: false
        }).then((result) => {
          if (result.isConfirmed) {
            btn.dataset.confirmed = 'true';
            if (btn.tagName === 'A') {
              window.location.href = btn.href;
            } else if (btn.type === 'submit' && btn.form) {
              btn.form.submit();
            }
          }
        });
      } else {
        if (confirm(text)) {
          btn.dataset.confirmed = 'true';
          if (btn.tagName === 'A') {
            window.location.href = btn.href;
          } else if (btn.type === 'submit' && btn.form) {
            btn.form.submit();
          }
        }
      }
    }
  });
}

/**
 * Dynamic İSG Risk Question & Option Builder for survey_edit.php
 */
function initQuestionBuilder() {
  const container = document.getElementById('questionsContainer');
  const addQuestionBtn = document.getElementById('addQuestionBtn');
  let qIndex = document.querySelectorAll('.question-builder-card').length;

  const riskGroups = window.riskGroupsData || [];
  let riskGroupOptionsHtml = '<option value="0">-- Risk Grubu Seçin --</option>';
  riskGroups.forEach(function(rg) {
    riskGroupOptionsHtml += `<option value="${rg.id}">${rg.group_name}</option>`;
  });

  addQuestionBtn.addEventListener('click', function () {
    qIndex++;
    
    // Eğer uyarı mesajı varsa kaldır
    const warningAlert = container.querySelector('.alert-warning');
    if (warningAlert) {
      warningAlert.remove();
    }

    const qCard = document.createElement('div');
    qCard.className = 'custom-card question-builder-card mb-4';
    qCard.setAttribute('data-qindex', qIndex);

    qCard.innerHTML = `
      <div class="custom-card-header bg-light p-3 rounded-top">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-primary rounded-circle" style="width:28px; height:28px; display:flex; align-items:center; justify-content:center;">${qIndex}</span>
          <h6 class="m-0 font-weight-bold">Yeni Risk Sorusu #${qIndex}</h6>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger remove-question-btn">
          <i class="bi bi-trash"></i> Soruyu Sil
        </button>
      </div>

      <div class="p-3">
        <!-- Risk Grubu, Tehlike Kaynağı ve Tehlike Row -->
        <div class="row g-3 mb-3">
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-muted"><i class="bi bi-exclamation-triangle"></i> Risk Grubu</label>
            <select name="new_questions[${qIndex}][risk_group_id]" class="form-select form-select-sm">
              ${riskGroupOptionsHtml}
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-muted">Tehlike Kaynağı (Kütüphaneden Seçilebilir)</label>
            <input type="text" name="new_questions[${qIndex}][hazard_source]" list="hazard_sources_list" class="form-control form-control-sm" placeholder="Seçin veya yazın...">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-muted">Tehlike Metni (Kütüphaneden Seçilebilir)</label>
            <input type="text" name="new_questions[${qIndex}][hazard_name]" list="hazards_list" class="form-control form-control-sm" placeholder="Seçin veya yazın...">
          </div>
        </div>

        <!-- Etkilenme ve Etkilenenler Row -->
        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6">
            <label class="form-label fw-bold fs-8 text-muted">Etkilenme (Yaşanabilecek Riskler)</label>
            <input type="text" name="new_questions[${qIndex}][affected_risk]" class="form-control form-control-sm" placeholder="Örn: Pis su bulaşma, Kas-iskelet hast.">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label fw-bold fs-8 text-muted">Etkilenenler (Kütüphaneden Seçilebilir)</label>
            <input type="text" name="new_questions[${qIndex}][affected_people]" list="affected_list" class="form-control form-control-sm" placeholder="Seçin veya yazın...">
          </div>
        </div>

        <!-- Soru Metni -->
        <div class="mb-3">
          <label class="form-label fw-bold">Kontrol / Denetim Sorusu Metni</label>
          <input type="text" name="new_questions[${qIndex}][text]" class="form-control" placeholder="Örn: Sahada kişisel koruyucu donanım (baret, eldiven) kullanılıyor mu?" required>
        </div>

        <!-- Cevap Seçenekleri Header -->
        <div class="mb-2 d-flex align-items-center justify-content-between">
          <label class="form-label fw-bold m-0 fs-8 text-muted"><i class="bi bi-list-check"></i> Cevap Seçenekleri & Tetikleyiciler</label>
          <button type="button" class="btn btn-sm btn-outline-success add-option-btn">
            <i class="bi bi-plus-lg"></i> Seçenek Ekle
          </button>
        </div>

        <div class="options-list-container">
          <!-- Varsayılan Şık 1 -->
          <div class="row g-2 mb-2 option-row align-items-center">
            <div class="col-7">
              <input type="text" name="new_questions[${qIndex}][options][0][text]" class="form-control form-control-sm" value="Evet (Uygun)" required>
            </div>
            <div class="col-4">
              <div class="form-check form-switch pt-1">
                <input class="form-check-input" type="checkbox" name="new_questions[${qIndex}][options][0][trigger_action]" value="1">
                <label class="form-check-label fs-8 fw-bold text-danger">İSG Önlem Kartı Açsın</label>
              </div>
            </div>
            <div class="col-1 text-end">
              <button type="button" class="btn btn-sm btn-link text-danger remove-option-btn p-0"><i class="bi bi-x-circle-fill fs-5"></i></button>
            </div>
          </div>

          <!-- Varsayılan Şık 2 -->
          <div class="row g-2 mb-2 option-row align-items-center">
            <div class="col-7">
              <input type="text" name="new_questions[${qIndex}][options][1][text]" class="form-control form-control-sm" value="Hayır (Uygun Değil)" required>
            </div>
            <div class="col-4">
              <div class="form-check form-switch pt-1">
                <input class="form-check-input" type="checkbox" name="new_questions[${qIndex}][options][1][trigger_action]" value="1" checked>
                <label class="form-check-label fs-8 fw-bold text-danger">İSG Önlem Kartı Açsın</label>
              </div>
            </div>
            <div class="col-1 text-end">
              <button type="button" class="btn btn-sm btn-link text-danger remove-option-btn p-0"><i class="bi bi-x-circle-fill fs-5"></i></button>
            </div>
          </div>

          <!-- Varsayılan Şık 3 -->
          <div class="row g-2 mb-2 option-row align-items-center">
            <div class="col-7">
              <input type="text" name="new_questions[${qIndex}][options][2][text]" class="form-control form-control-sm" value="Kısmen (Kısmen Uygun)" required>
            </div>
            <div class="col-4">
              <div class="form-check form-switch pt-1">
                <input class="form-check-input" type="checkbox" name="new_questions[${qIndex}][options][2][trigger_action]" value="1" checked>
                <label class="form-check-label fs-8 fw-bold text-danger">İSG Önlem Kartı Açsın</label>
              </div>
            </div>
            <div class="col-1 text-end">
              <button type="button" class="btn btn-sm btn-link text-danger remove-option-btn p-0"><i class="bi bi-x-circle-fill fs-5"></i></button>
            </div>
          </div>

          <!-- Varsayılan Şık 4 -->
          <div class="row g-2 mb-2 option-row align-items-center">
            <div class="col-7">
              <input type="text" name="new_questions[${qIndex}][options][3][text]" class="form-control form-control-sm" value="Denetim Dışı / Muaf" required>
            </div>
            <div class="col-4">
              <div class="form-check form-switch pt-1">
                <input class="form-check-input" type="checkbox" name="new_questions[${qIndex}][options][3][trigger_action]" value="1">
                <label class="form-check-label fs-8 fw-bold text-danger">İSG Önlem Kartı Açsın</label>
              </div>
            </div>
            <div class="col-1 text-end">
              <button type="button" class="btn btn-sm btn-link text-danger remove-option-btn p-0"><i class="bi bi-x-circle-fill fs-5"></i></button>
            </div>
          </div>
        </div>
      </div>
    `;

    container.appendChild(qCard);
    bindQuestionEvents(qCard);
  });

  document.querySelectorAll('.question-builder-card').forEach(bindQuestionEvents);

  function bindQuestionEvents(qCard) {
    const removeQBtn = qCard.querySelector('.remove-question-btn');
    if (removeQBtn) {
      removeQBtn.addEventListener('click', function () {
        if (window.Swal) {
          Swal.fire({
            title: 'Soruyu Sil',
            text: 'Bu soruyu ve seçeneklerini kaldırmak istediğinize emin misiniz?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Evet, Sil!',
            cancelButtonText: 'Vazgeç',
            reverseButtons: true,
            customClass: {
              popup: 'swal2-custom-popup',
              confirmButton: 'btn btn-danger font-weight-bold px-4 py-2 me-2',
              cancelButton: 'btn btn-secondary font-weight-bold px-4 py-2'
            },
            buttonsStyling: false
          }).then((res) => {
            if (res.isConfirmed) {
              qCard.remove();
            }
          });
        } else {
          if (confirm('Bu soruyu silmek istediğinize emin misiniz?')) {
            qCard.remove();
          }
        }
      });
    }

    const addOptBtn = qCard.querySelector('.add-option-btn');
    if (addOptBtn) {
      addOptBtn.addEventListener('click', function () {
        const optionsContainer = qCard.querySelector('.options-list-container');
        const currentQIndex = qCard.getAttribute('data-qindex');
        const optCount = optionsContainer.querySelectorAll('.option-row').length;

        const optRow = document.createElement('div');
        optRow.className = 'row g-2 mb-2 option-row align-items-center';
        optRow.innerHTML = `
          <div class="col-7">
            <input type="text" name="new_questions[${currentQIndex}][options][${optCount}][text]" class="form-control form-control-sm" placeholder="Seçenek metni" required>
          </div>
          <div class="col-4">
            <div class="form-check form-switch pt-1">
              <input class="form-check-input" type="checkbox" name="new_questions[${currentQIndex}][options][${optCount}][trigger_action]" value="1" checked>
              <label class="form-check-label fs-8 fw-bold text-danger">İSG Önlem Kartı Açsın</label>
            </div>
          </div>
          <div class="col-1 text-end">
            <button type="button" class="btn btn-sm btn-link text-danger remove-option-btn p-0"><i class="bi bi-x-circle-fill fs-5"></i></button>
          </div>
        `;

        optionsContainer.appendChild(optRow);
        bindOptionRemove(optRow);
      });
    }

    qCard.querySelectorAll('.option-row').forEach(bindOptionRemove);
  }

  function bindOptionRemove(optRow) {
    const removeOptBtn = optRow.querySelector('.remove-option-btn');
    if (removeOptBtn) {
      removeOptBtn.addEventListener('click', function () {
        optRow.remove();
      });
    }
  }
}

/**
 * Quick AJAX Unit Creator
 */
function initQuickUnitCreator() {
  const quickUnitForm = document.getElementById('quickUnitForm');
  quickUnitForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(quickUnitForm);

    fetch('units.php', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const select = document.getElementById('unitSelect');
        if (select) {
          const opt = document.createElement('option');
          opt.value = data.unit.id;
          opt.textContent = data.unit.unit_name;
          opt.selected = true;
          select.appendChild(opt);
        }

        const modalEl = document.getElementById('quickAddUnitModal');
        if (modalEl) {
          const modal = bootstrap.Modal.getInstance(modalEl);
          if (modal) modal.hide();
        }

        if (window.Swal) {
          Swal.fire({
            icon: 'success',
            title: 'Başarılı!',
            text: 'Yeni birim başarıyla eklendi ve seçildi.',
            timer: 2000,
            showConfirmButton: false
          });
        }
        quickUnitForm.reset();
      } else {
        alert(data.message || 'Birim eklenirken bir hata oluştu.');
      }
    })
    .catch(err => {
      console.error(err);
      alert('İstek gönderilirken hata oluştu.');
    });
  });
}

/**
 * Interactive Visual Audit Wizard in audit_new.php
 */
function initAuditWizard() {
  const templateCards = document.querySelectorAll('.template-card-choice');
  const unitCards = document.querySelectorAll('.unit-card-choice');
  const selectedTemplateInput = document.getElementById('selectedTemplateInput');
  const selectedUnitInput = document.getElementById('selectedUnitInput');
  const startBtn = document.getElementById('startAuditSubmitBtn');
  const unitSearchInput = document.getElementById('unitSearchInput');

  // 1. Template Card Select
  templateCards.forEach(card => {
    card.addEventListener('click', function () {
      templateCards.forEach(c => c.classList.remove('selected', 'border-success', 'shadow-md'));
      this.classList.add('selected', 'border-success', 'shadow-md');
      
      const tId = this.dataset.templateId;
      selectedTemplateInput.value = tId;
      checkWizardReady();
    });
  });

  // 2. Unit Card Select
  unitCards.forEach(card => {
    card.addEventListener('click', function () {
      unitCards.forEach(c => c.classList.remove('selected', 'border-success', 'shadow-md'));
      this.classList.add('selected', 'border-success', 'shadow-md');
      
      const uId = this.dataset.unitId;
      selectedUnitInput.value = uId;
      checkWizardReady();
    });
  });

  // 3. Live Unit Search
  if (unitSearchInput) {
    unitSearchInput.addEventListener('input', function () {
      const q = this.value.toLowerCase().trim();
      unitCards.forEach(card => {
        const name = card.dataset.unitName.toLowerCase();
        if (name.includes(q)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    });
  }

  function checkWizardReady() {
    if (selectedTemplateInput.value > 0 && selectedUnitInput.value > 0) {
      startBtn.disabled = false;
      startBtn.classList.remove('btn-secondary');
      startBtn.classList.add('btn-success', 'shadow-lg');
    }
  }
}
