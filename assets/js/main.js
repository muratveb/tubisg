/**
 * Tubİsg - Main Interactive Script
 * Real-time Score Calculator, Auto-dismissing Alerts, Audit Wizard, Touch Handlers & SweetAlert2 Modals
 */

document.addEventListener('DOMContentLoaded', function () {

  // 1. Mobile Sidebar Toggle
  const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
  const sidebar = document.querySelector('.sidebar');
  if (sidebarToggleBtn && sidebar) {
    sidebarToggleBtn.addEventListener('click', function () {
      sidebar.classList.toggle('show');
    });
  }

  // 2. Auto-Dismissing Flash Notification Banners (4 saniye sonra otomatik kapanır)
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
      alert.style.opacity = '0';
      alert.style.transform = 'translateY(-10px)';
      setTimeout(() => {
        alert.remove();
      }, 500);
    }, 4000);
  });

  // 3. Audit Fill Page: Touch Option Card Click & Real-time Live Score Calculation
  const auditForm = document.getElementById('auditFillForm');
  if (auditForm) {
    initAuditFormCalculator();
  }

  // 4. Dynamic Question Builder for survey_edit.php
  const addQuestionBtn = document.getElementById('addQuestionBtn');
  if (addQuestionBtn) {
    initQuestionBuilder();
  }

  // 5. Quick AJAX Unit Creator
  const quickUnitForm = document.getElementById('quickUnitForm');
  if (quickUnitForm) {
    initQuickUnitCreator();
  }

  // 6. Interactive Visual Audit Wizard (audit_new.php)
  const wizardForm = document.getElementById('startAuditWizardForm');
  if (wizardForm) {
    initAuditWizard();
  }

  // 7. Global Modern SweetAlert2 Confirmation Dialog Handler
  initModernConfirmHandler();

});

/**
 * Global Modern SweetAlert2 Confirm Handler (Eski usul browser alert/confirm pencerelerini tamamen engeller)
 */
function initModernConfirmHandler() {
  document.addEventListener('submit', function (e) {
    const form = e.target;
    
    if (form.classList.contains('confirm-delete-form') || form.hasAttribute('data-confirm-title')) {
      if (form.dataset.confirmed === 'true') {
        return true; // Kullanıcı önceden onayladıysa formu gönder
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
            popup: 'rounded-4 shadow-lg border-0',
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
}

/**
 * Interactive Visual Audit Wizard (audit_new.php)
 */
function initAuditWizard() {
  const tplCards = document.querySelectorAll('.template-card');
  const unitCards = document.querySelectorAll('.unit-card');
  const tplInput = document.getElementById('selectedTemplateInput');
  const unitInput = document.getElementById('selectedUnitInput');
  const submitBtn = document.getElementById('startAuditSubmitBtn');
  const summaryText = document.getElementById('wizardSelectionSummary');

  let selectedTplTitle = '';
  let selectedUnitTitle = '';

  function updateWizardState() {
    const tplId = tplInput.value;
    const unitId = unitInput.value;

    if (tplId && unitId) {
      if (submitBtn) {
        submitBtn.classList.remove('disabled');
      }
      if (summaryText) {
        summaryText.innerHTML = `<span class="text-success">${selectedTplTitle}</span> &rarr; <span class="text-primary">${selectedUnitTitle}</span>`;
      }
    } else {
      if (submitBtn) {
        submitBtn.classList.add('disabled');
      }
      let missing = [];
      if (!tplId) missing.push('Anket Profili');
      if (!unitId) missing.push('Birim');
      if (summaryText) {
        summaryText.innerHTML = `Lütfen <strong class="text-danger">${missing.join(' ve ')}</strong> seçin`;
      }
    }
  }

  // Anket Profili Kart Seçimi
  tplCards.forEach(card => {
    if (card.classList.contains('selected')) {
      selectedTplTitle = card.querySelector('.wizard-card-title').textContent.trim();
    }

    card.addEventListener('click', function () {
      tplCards.forEach(c => c.classList.remove('selected'));
      this.classList.add('selected');
      const id = this.dataset.id;
      tplInput.value = id;
      selectedTplTitle = this.querySelector('.wizard-card-title').textContent.trim();
      updateWizardState();
    });
  });

  // Birim Kart Seçimi
  unitCards.forEach(card => {
    if (card.classList.contains('selected')) {
      selectedUnitTitle = card.querySelector('.wizard-card-title').textContent.trim();
    }

    card.addEventListener('click', function () {
      unitCards.forEach(c => c.classList.remove('selected'));
      this.classList.add('selected');
      const id = this.dataset.id;
      unitInput.value = id;
      selectedUnitTitle = this.querySelector('.wizard-card-title').textContent.trim();
      updateWizardState();
    });
  });

  updateWizardState();
}

/**
 * Audit Real-time Score Calculator & Touch Selection Handler
 */
function initAuditFormCalculator() {
  const optionCards = document.querySelectorAll('.option-item-card');
  const totalScoreElem = document.getElementById('liveTotalScore');
  const maxScoreElem = document.getElementById('liveMaxScore');
  const percentElem = document.getElementById('livePercentage');
  const progressBarFill = document.getElementById('scoreProgressFill');
  const scoreBadge = document.getElementById('scoreStatusBadge');

  function calculateScore() {
    let currentTotal = 0;
    let maxPossible = 0;

    const questionCards = document.querySelectorAll('.question-card');
    
    questionCards.forEach(qCard => {
      let qMaxPoints = 0;
      const checkboxes = qCard.querySelectorAll('.option-checkbox:checked');
      const allOptionsInQuestion = qCard.querySelectorAll('.option-item-card');

      allOptionsInQuestion.forEach(optCard => {
        const pts = parseInt(optCard.dataset.points) || 0;
        if (pts > qMaxPoints) {
          qMaxPoints = pts;
        }
      });
      maxPossible += qMaxPoints;

      checkboxes.forEach(cb => {
        const parentCard = cb.closest('.option-item-card');
        if (parentCard) {
          const pts = parseInt(parentCard.dataset.points) || 0;
          currentTotal += pts;
        }
      });
    });

    if (totalScoreElem) totalScoreElem.textContent = (currentTotal > 0 ? '+' : '') + currentTotal;
    if (maxScoreElem) maxScoreElem.textContent = maxPossible;

    let percentage = 0;
    if (maxPossible > 0) {
      percentage = Math.max(0, Math.min(100, Math.round((currentTotal / maxPossible) * 100)));
    } else if (currentTotal > 0) {
      percentage = 100;
    }

    if (percentElem) percentElem.textContent = '%' + percentage;
    if (progressBarFill) progressBarFill.style.width = percentage + '%';

    if (scoreBadge) {
      if (percentage >= 80) {
        scoreBadge.textContent = 'DÜŞÜK RİSK / UYGUN';
        scoreBadge.className = 'badge bg-success text-white';
        if (progressBarFill) progressBarFill.style.background = '#10b981';
      } else if (percentage >= 50) {
        scoreBadge.textContent = 'ORTA RİSK';
        scoreBadge.className = 'badge bg-warning text-dark';
        if (progressBarFill) progressBarFill.style.background = '#f59e0b';
      } else {
        scoreBadge.textContent = 'YÜKSEK RİSK!';
        scoreBadge.className = 'badge bg-danger text-white';
        if (progressBarFill) progressBarFill.style.background = '#ef4444';
      }
    }
  }

  optionCards.forEach(card => {
    card.addEventListener('click', function (e) {
      const checkbox = this.querySelector('.option-checkbox');
      const checkIcon = this.querySelector('.check-icon');
      if (!checkbox) return;

      checkbox.checked = !checkbox.checked;

      if (checkbox.checked) {
        this.classList.add('selected');
        if (checkIcon) checkIcon.classList.remove('d-none');
      } else {
        this.classList.remove('selected');
        if (checkIcon) checkIcon.classList.add('d-none');
      }

      calculateScore();
    });
  });

  calculateScore();
}

/**
 * Dynamic Question & Option Builder in survey_edit.php
 */
function initQuestionBuilder() {
  const container = document.getElementById('questionsContainer');
  const addQuestionBtn = document.getElementById('addQuestionBtn');
  let qIndex = document.querySelectorAll('.question-builder-card').length;

  addQuestionBtn.addEventListener('click', function () {
    qIndex++;
    const qCard = document.createElement('div');
    qCard.className = 'custom-card question-builder-card mb-4';
    qCard.setAttribute('data-qindex', qIndex);

    qCard.innerHTML = `
      <div class="custom-card-header bg-light p-3 rounded-top">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-primary rounded-circle" style="width:28px; height:28px; display:flex; align-items:center; justify-content:center;">${qIndex}</span>
          <h6 class="m-0 font-weight-bold">Yeni Soru ${qIndex}</h6>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger remove-question-btn">
          <i class="bi bi-trash"></i> Soruyu Sil
        </button>
      </div>
      <div class="p-3">
        <div class="mb-3">
          <label class="form-label fw-bold">Soru Metni</label>
          <input type="text" name="new_questions[${qIndex}][text]" class="form-control" placeholder="Örn: Sahada eldiven kullanılıyor mu?" required>
        </div>
        
        <div class="mb-2 d-flex align-items-center justify-content-between">
          <label class="form-label fw-bold m-0"><i class="bi bi-list-check"></i> Cevap Seçenekleri ve Puanları</label>
          <button type="button" class="btn btn-sm btn-outline-success add-option-btn">
            <i class="bi bi-plus-lg"></i> Seçenek Ekle
          </button>
        </div>
        
        <div class="options-list-container">
          <div class="row g-2 mb-2 option-row align-items-center">
            <div class="col-7">
              <input type="text" name="new_questions[${qIndex}][options][0][text]" class="form-control form-control-sm" placeholder="Seçenek metni (Örn: Hayır, kullanılmıyor)" required>
            </div>
            <div class="col-4">
              <input type="number" name="new_questions[${qIndex}][options][0][points]" class="form-control form-control-sm" placeholder="Puan (-5, 10 vb.)" value="-5" required>
            </div>
            <div class="col-1 text-end">
              <button type="button" class="btn btn-sm btn-link text-danger remove-option-btn p-0"><i class="bi bi-x-circle-fill"></i></button>
            </div>
          </div>
          <div class="row g-2 mb-2 option-row align-items-center">
            <div class="col-7">
              <input type="text" name="new_questions[${qIndex}][options][1][text]" class="form-control form-control-sm" placeholder="Seçenek metni (Örn: Evet, sarı eldiven kullanılıyor)" required>
            </div>
            <div class="col-4">
              <input type="number" name="new_questions[${qIndex}][options][1][points]" class="form-control form-control-sm" placeholder="Puan (-5, 10 vb.)" value="10" required>
            </div>
            <div class="col-1 text-end">
              <button type="button" class="btn btn-sm btn-link text-danger remove-option-btn p-0"><i class="bi bi-x-circle-fill"></i></button>
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
            buttonsStyling: false,
            customClass: {
              confirmButton: 'btn btn-danger font-weight-bold px-4 py-2 me-2',
              cancelButton: 'btn btn-secondary font-weight-bold px-4 py-2'
            }
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
        const optionsList = qCard.querySelector('.options-list-container');
        const qIdx = qCard.getAttribute('data-qindex');
        const optCount = optionsList.querySelectorAll('.option-row').length;

        const optRow = document.createElement('div');
        optRow.className = 'row g-2 mb-2 option-row align-items-center';
        optRow.innerHTML = `
          <div class="col-7">
            <input type="text" name="new_questions[${qIdx}][options][${optCount}][text]" class="form-control form-control-sm" placeholder="Yeni seçenek metni" required>
          </div>
          <div class="col-4">
            <input type="number" name="new_questions[${qIdx}][options][${optCount}][points]" class="form-control form-control-sm" placeholder="Puan" value="0" required>
          </div>
          <div class="col-1 text-end">
            <button type="button" class="btn btn-sm btn-link text-danger remove-option-btn p-0"><i class="bi bi-x-circle-fill"></i></button>
          </div>
        `;
        optionsList.appendChild(optRow);
        bindOptionEvents(optRow);
      });
    }

    qCard.querySelectorAll('.option-row').forEach(bindOptionEvents);
  }

  function bindOptionEvents(optRow) {
    const removeOptBtn = optRow.querySelector('.remove-option-btn');
    if (removeOptBtn) {
      removeOptBtn.addEventListener('click', function () {
        optRow.remove();
      });
    }
  }
}

/**
 * Quick AJAX Unit Creator Function
 */
function initQuickUnitCreator() {
  const form = document.getElementById('quickUnitForm');
  const modalElem = document.getElementById('quickUnitModal');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(form);

    fetch('units.php?action=quick_add', {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // 1. Yeni birimi sihirbaz kartlar konteynerine görsel kart olarak ekle
          const unitContainer = document.getElementById('unitCardsContainer');
          if (unitContainer) {
            const col = document.createElement('div');
            col.className = 'col-12 col-sm-6 unit-card-wrapper';
            col.setAttribute('data-name', data.unit.unit_name.toLowerCase());
            col.innerHTML = `
              <div class="wizard-select-card unit-card selected p-2 px-3" data-id="${data.unit.id}">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-building text-success fs-6"></i>
                  <h6 class="fw-bold text-dark m-0 wizard-card-title fs-7 text-truncate">${data.unit.unit_name}</h6>
                </div>
                <p class="text-muted fs-8 m-0 wizard-card-desc text-truncate" style="font-size:0.75rem;">${data.unit.description || 'Kayıtlı saha birimi.'}</p>
                <div class="wizard-card-check">
                  <i class="bi bi-check-circle-fill"></i>
                </div>
              </div>
            `;
            unitContainer.appendChild(col);
            
            // Diğer kartların seçimini kaldırıp bunu seçili yap
            document.querySelectorAll('.unit-card').forEach(c => c.classList.remove('selected'));
            col.querySelector('.unit-card').classList.add('selected');
            document.getElementById('selectedUnitInput').value = data.unit.id;
            
            // Re-init wizard
            initAuditWizard();
          }

          form.reset();
          if (modalElem && window.bootstrap) {
            const modalInstance = bootstrap.Modal.getInstance(modalElem);
            if (modalInstance) modalInstance.hide();
          }
        } else {
          if (window.Swal) {
            Swal.fire('Hata!', data.message || 'Birim eklenemedi.', 'error');
          } else {
            alert('Hata: ' + (data.message || 'Birim eklenemedi.'));
          }
        }
      })
      .catch(err => {
        console.error(err);
        if (window.Swal) {
          Swal.fire('Hata!', 'Bir bağlantı hatası oluştu.', 'error');
        } else {
          alert('Bir hata oluştu.');
        }
      });
  });
}
