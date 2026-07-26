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

  // 3. 9-Step Survey Item Wizard Modal Handler for survey_edit.php
  initItemWizardModal();

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
 * 9-Step Interactive Survey Item Wizard Modal in survey_edit.php
 */
function initItemWizardModal() {
  const wizardModalEl = document.getElementById('wizardAddRiskItemModal');
  if (!wizardModalEl) return;

  let currentStep = 1;
  const totalSteps = 9;

  const stepTitles = [
    'Adım 1 / 9: Risk Grubu',
    'Adım 2 / 9: Tehlike Kaynağı',
    'Adım 3 / 9: Tehlike',
    'Adım 4 / 9: Etkilenme (Riskler)',
    'Adım 5 / 9: Etkilenen Gruplar',
    'Adım 6 / 9: Mevcut Durum',
    'Adım 7 / 9: Olasılık & Şiddet',
    'Adım 8 / 9: Alınacak Önlemler',
    'Adım 9 / 9: Sorumlu & Süre'
  ];

  const prevBtn = document.getElementById('wizPrevBtn');
  const nextBtn = document.getElementById('wizNextBtn');
  const badge = document.getElementById('itemWizardStepBadge');
  const progressBar = document.getElementById('itemWizardProgressBar');

  wizardModalEl.addEventListener('click', function(e) {
    const rgCard = e.target.closest('.wiz-rg-card');
    if (rgCard) {
      wizardModalEl.querySelectorAll('.wiz-rg-card').forEach(c => c.classList.remove('border-success', 'bg-light'));
      rgCard.classList.add('border-success', 'bg-light');

      document.getElementById('wiz_risk_group_id').value = rgCard.dataset.rgid;
      document.getElementById('wiz_risk_group_name').value = rgCard.dataset.rgname;

      goToStep(2);
    }
  });

  document.querySelectorAll('.onchange-wiz-calc').forEach(sel => {
    sel.addEventListener('change', updateWizRiskCalc);
  });

  function updateWizRiskCalc() {
    const p = parseInt(document.getElementById('wiz_probability').value) || 1;
    const s = parseInt(document.getElementById('wiz_severity').value) || 1;
    const r = p * s;

    let cat = 'Kabul Edilebilir Risk';
    let color = '#10b981';
    if (r >= 16) { cat = 'Kabul Edilemez Risk'; color = '#ef4444'; }
    else if (r >= 10) { cat = 'Dikkate Değer Risk'; color = '#f59e0b'; }
    else if (r >= 6) { cat = 'Önemli Risk'; color = '#06b6d4'; }

    const resDiv = document.getElementById('wiz_risk_result');
    if (resDiv) {
      resDiv.style.color = color;
      resDiv.textContent = `R = ${r} (${cat})`;
    }
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', function() {
      if (currentStep < totalSteps) {
        goToStep(currentStep + 1);
      } else {
        finishWizardAndAddItem();
      }
    });
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', function() {
      if (currentStep > 1) {
        goToStep(currentStep - 1);
      }
    });
  }

  window.goToStep = goToStep;

  function goToStep(stepNum) {
    currentStep = stepNum;

    for (let i = 1; i <= totalSteps; i++) {
      const stepEl = document.getElementById('itemStep' + i);
      if (stepEl) {
        if (i === currentStep) {
          stepEl.classList.remove('d-none');
        } else {
          stepEl.classList.add('d-none');
        }
      }
    }

    if (badge) badge.textContent = stepTitles[currentStep - 1];
    if (progressBar) progressBar.style.width = Math.round((currentStep / totalSteps) * 100) + '%';

    if (currentStep > 1) {
      if (prevBtn) prevBtn.classList.remove('d-none');
    } else {
      if (prevBtn) prevBtn.classList.add('d-none');
    }

    if (nextBtn) {
      if (currentStep === totalSteps) {
        nextBtn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Sihirbazı Tamamla ve Ekle';
        nextBtn.className = 'btn btn-primary font-weight-bold px-4 shadow';
      } else {
        nextBtn.innerHTML = 'Sonraki Adım <i class="bi bi-arrow-right"></i>';
        nextBtn.className = 'btn btn-success font-weight-bold px-4';
      }
    }
  }

  function finishWizardAndAddItem() {
    const container = document.getElementById('questionsContainer');
    let qIndex = document.querySelectorAll('.question-builder-card').length + 1;

    const warningAlert = container.querySelector('.alert-warning');
    if (warningAlert) {
      warningAlert.remove();
    }

    const rgId = document.getElementById('wiz_risk_group_id').value || 0;
    const rgName = document.getElementById('wiz_risk_group_name').value || 'Genel Riskler';
    const hazardSource = document.getElementById('wiz_hazard_source').value || '';
    const hazardName = document.getElementById('wiz_hazard_name').value || '';
    const affectedRisk = document.getElementById('wiz_affected_risk').value || '';
    const affectedPeople = document.getElementById('wiz_affected_people').value || '';
    const currentStatus = document.getElementById('wiz_current_status').value || '';
    let questionText = document.getElementById('wiz_question_text').value || '';
    if (!questionText) {
      questionText = hazardName ? hazardName : 'Saha Risk Denetim Maddesi';
    }

    const prob = parseInt(document.getElementById('wiz_probability').value) || 2;
    const sev = parseInt(document.getElementById('wiz_severity').value) || 3;
    const riskVal = prob * sev;

    let category = 'Kabul Edilebilir Risk';
    let badgeBg = 'bg-success';
    if (riskVal >= 16) { category = 'Kabul Edilemez Risk'; badgeBg = 'bg-danger'; }
    else if (riskVal >= 10) { category = 'Dikkate Değer Risk'; badgeBg = 'bg-warning text-dark'; }
    else if (riskVal >= 6) { category = 'Önemli Risk'; badgeBg = 'bg-info text-dark'; }

    const actionPlan = document.getElementById('wiz_action_plan').value || '';
    const responsible = document.getElementById('wiz_responsible').value || '';
    const deadline = document.getElementById('wiz_deadline').value || '';

    const riskGroups = window.riskGroupsData || [];
    let riskGroupOptionsHtml = '<option value="0">-- Risk Grubu Seçin --</option>';
    riskGroups.forEach(function(rg) {
      const isSel = (rg.id == rgId) ? 'selected' : '';
      riskGroupOptionsHtml += `<option value="${rg.id}" ${isSel}>${rg.group_name}</option>`;
    });

    const qCard = document.createElement('div');
    qCard.className = 'custom-card question-builder-card mb-4 border-2';
    qCard.setAttribute('data-qindex', qIndex);

    qCard.innerHTML = `
      <div class="custom-card-header bg-dark text-white p-3 rounded-top d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-warning text-dark rounded-circle" style="width:28px; height:28px; display:flex; align-items:center; justify-content:center;">${qIndex}</span>
          <h6 class="m-0 font-weight-bold text-white">Yeni Risk Satırı #${qIndex}</h6>
          <span class="badge bg-secondary ms-2">${rgName}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge ${badgeBg}">R = ${riskVal} (${category})</span>
          <button type="button" class="btn btn-sm btn-outline-danger text-white remove-question-btn">
            <i class="bi bi-trash"></i> Sil
          </button>
        </div>
      </div>

      <div class="p-3">
        <div class="row g-3 mb-3 bg-light p-2 rounded-3 border">
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-dark"><i class="bi bi-diagram-3-fill text-warning me-1"></i> 1. Risk Grubu</label>
            <select name="new_questions[${qIndex}][risk_group_id]" class="form-select form-select-sm fw-bold">
              ${riskGroupOptionsHtml}
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-dark">2. Tehlike Kaynağı (Kütüphaneden)</label>
            <input type="text" name="new_questions[${qIndex}][hazard_source]" list="hazard_sources_list" class="form-control form-control-sm" value="${hazardSource}">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-dark">3. Tehlike (Kütüphaneden)</label>
            <input type="text" name="new_questions[${qIndex}][hazard_name]" list="hazards_list" class="form-control form-control-sm" value="${hazardName}">
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6">
            <label class="form-label fw-bold fs-8 text-muted">4. Etkilenme (Yaşanabilecek Riskler)</label>
            <input type="text" name="new_questions[${qIndex}][affected_risk]" class="form-control form-control-sm" value="${affectedRisk}">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label fw-bold fs-8 text-muted">5. Etkilenenler (Kütüphaneden)</label>
            <input type="text" name="new_questions[${qIndex}][affected_people]" list="affected_list" class="form-control form-control-sm" value="${affectedPeople}">
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6">
            <label class="form-label fw-bold fs-8 text-muted">6. Mevcut Durum / Saha Tespiti</label>
            <input type="text" name="new_questions[${qIndex}][current_status]" class="form-control form-control-sm" value="${currentStatus}">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label fw-bold fs-8 text-muted">Saha Denetim Sorusu Metni</label>
            <input type="text" name="new_questions[${qIndex}][question_text]" class="form-control form-control-sm" value="${questionText}">
          </div>
        </div>

        <div class="row g-3 mb-3 p-2 rounded-3 bg-light border border-info">
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-dark">7. Olasılık ($O: 1-5$)</label>
            <select name="new_questions[${qIndex}][default_probability]" class="form-select form-select-sm">
              <option value="1" ${prob == 1 ? 'selected' : ''}>1 - Çok Küçük</option>
              <option value="2" ${prob == 2 ? 'selected' : ''}>2 - Küçük</option>
              <option value="3" ${prob == 3 ? 'selected' : ''}>3 - Orta</option>
              <option value="4" ${prob == 4 ? 'selected' : ''}>4 - Yüksek</option>
              <option value="5" ${prob == 5 ? 'selected' : ''}>5 - Çok Yüksek</option>
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-dark">8. Şiddet ($Ş: 1-5$)</label>
            <select name="new_questions[${qIndex}][default_severity]" class="form-select form-select-sm">
              <option value="1" ${sev == 1 ? 'selected' : ''}>1 - Çok Hafif</option>
              <option value="2" ${sev == 2 ? 'selected' : ''}>2 - Hafif</option>
              <option value="3" ${sev == 3 ? 'selected' : ''}>3 - Ciddi</option>
              <option value="4" ${sev == 4 ? 'selected' : ''}>4 - Çok Ciddi</option>
              <option value="5" ${sev == 5 ? 'selected' : ''}>5 - Felaket</option>
            </select>
          </div>

          <div class="col-12 col-md-4 d-flex align-items-center">
            <div class="w-100 text-center">
              <div class="text-muted fs-8 fw-bold">9. Risk Derecesi ($R = O \\times Ş$)</div>
              <div class="fs-5 fw-extrabold text-primary">${riskVal}</div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12 col-md-5">
            <label class="form-label fw-bold fs-8 text-dark">10. Alınacak Önlemler / İyileştirmeler (Kütüphaneden)</label>
            <input type="text" name="new_questions[${qIndex}][default_action_plan]" list="recommendations_list" class="form-control form-control-sm" value="${actionPlan}">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-dark">11. Sorumlu Birim (Kütüphaneden)</label>
            <input type="text" name="new_questions[${qIndex}][default_responsible]" list="responsibles_list" class="form-control form-control-sm" value="${responsible}">
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label fw-bold fs-8 text-dark">12. Başlama / Süre</label>
            <input type="text" name="new_questions[${qIndex}][default_deadline]" class="form-control form-control-sm" value="${deadline}">
          </div>
        </div>

      </div>
    `;

    container.appendChild(qCard);

    const removeQBtn = qCard.querySelector('.remove-question-btn');
    if (removeQBtn) {
      removeQBtn.addEventListener('click', function () {
        qCard.remove();
      });
    }

    const bsModal = bootstrap.Modal.getInstance(wizardModalEl);
    if (bsModal) bsModal.hide();

    goToStep(1);

    if (window.Swal) {
      Swal.fire({
        icon: 'success',
        title: 'Risk Maddesi Eklendi',
        text: 'Seçimleriniz 12 sütunlu risk matrisine başarıyla eklendi.',
        timer: 2000,
        showConfirmButton: false
      });
    }
  }
}

/**
 * Quick AJAX Unit Creator
 */
function initQuickUnitCreator() {
  const quickUnitForm = document.getElementById('quickUnitForm');
  if (!quickUnitForm) return;

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
 * Interactive Visual Audit Wizard in audit_new.php (Fixed Selection Handler)
 */
function initAuditWizard() {
  const wizardForm = document.getElementById('startAuditWizardForm');
  if (!wizardForm) return;

  const selectedTemplateInput = document.getElementById('selectedTemplateInput');
  const selectedUnitInput = document.getElementById('selectedUnitInput');
  const startBtn = document.getElementById('startAuditSubmitBtn');
  const summaryDiv = document.getElementById('wizardSelectionSummary');

  let selectedTemplateName = '';
  let selectedUnitName = '';

  // Event Delegation for Template Cards Click
  wizardForm.addEventListener('click', function(e) {
    const tCard = e.target.closest('.template-card');
    if (tCard) {
      document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
      tCard.classList.add('selected');

      const tId = tCard.dataset.id;
      selectedTemplateInput.value = tId;

      const titleEl = tCard.querySelector('.wizard-card-title');
      selectedTemplateName = titleEl ? titleEl.textContent.trim() : 'Anket Profili';

      checkWizardReady();
    }

    const uCard = e.target.closest('.unit-card');
    if (uCard) {
      document.querySelectorAll('.unit-card').forEach(c => c.classList.remove('selected'));
      uCard.classList.add('selected');

      const uId = uCard.dataset.id;
      selectedUnitInput.value = uId;

      const titleEl = uCard.querySelector('.wizard-card-title');
      selectedUnitName = titleEl ? titleEl.textContent.trim() : 'Birim';

      checkWizardReady();
    }
  });

  function checkWizardReady() {
    const tVal = selectedTemplateInput ? selectedTemplateInput.value : '';
    const uVal = selectedUnitInput ? selectedUnitInput.value : '';

    if (tVal && uVal && parseInt(tVal) > 0 && parseInt(uVal) > 0) {
      if (startBtn) {
        startBtn.classList.remove('disabled');
        startBtn.removeAttribute('disabled');
      }
      if (summaryDiv) {
        summaryDiv.innerHTML = `<span class="text-primary fw-bold">${selectedTemplateName}</span> &rarr; <span class="text-success fw-bold">${selectedUnitName}</span>`;
      }
    } else {
      if (startBtn) {
        startBtn.classList.add('disabled');
      }
      if (summaryDiv) {
        if (parseInt(tVal) > 0) {
          summaryDiv.innerHTML = `<span class="text-primary fw-bold">${selectedTemplateName}</span> &rarr; <span class="text-muted">Birim seçiniz</span>`;
        } else if (parseInt(uVal) > 0) {
          summaryDiv.innerHTML = `<span class="text-muted">Anket seçiniz</span> &rarr; <span class="text-success fw-bold">${selectedUnitName}</span>`;
        } else {
          summaryDiv.textContent = 'Lütfen yukarıdan Anket Profili ve Birim seçin';
        }
      }
    }
  }

  // Pre-select if values exist in inputs
  if (selectedTemplateInput && selectedTemplateInput.value > 0) {
    const activeTCard = document.querySelector(`.template-card[data-id="${selectedTemplateInput.value}"]`);
    if (activeTCard) {
      activeTCard.classList.add('selected');
      const titleEl = activeTCard.querySelector('.wizard-card-title');
      selectedTemplateName = titleEl ? titleEl.textContent.trim() : 'Anket Profili';
    }
  }
  if (selectedUnitInput && selectedUnitInput.value > 0) {
    const activeUCard = document.querySelector(`.unit-card[data-id="${selectedUnitInput.value}"]`);
    if (activeUCard) {
      activeUCard.classList.add('selected');
      const titleEl = activeUCard.querySelector('.wizard-card-title');
      selectedUnitName = titleEl ? titleEl.textContent.trim() : 'Birim';
    }
  }

  checkWizardReady();
}
