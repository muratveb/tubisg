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
 * Dynamic 12-Column İSG Risk Matrix Builder for survey_edit.php
 */
function initQuestionBuilder() {
  const container = document.getElementById('questionsContainer');
  const addQuestionBtn = document.getElementById('addQuestionBtn');
  let qIndex = document.querySelectorAll('.question-builder-card').length;

  const riskGroups = window.riskGroupsData || [];

  addQuestionBtn.addEventListener('click', function () {
    qIndex++;
    
    const warningAlert = container.querySelector('.alert-warning');
    if (warningAlert) {
      warningAlert.remove();
    }

    const preSelectedRgId = window.selectedModalRiskGroupId || 0;
    const preSelectedRgName = window.selectedModalRiskGroupName || 'Genel Riskler';

    let riskGroupOptionsHtml = '<option value="0">-- Pop-up veya Listeden Seçin --</option>';
    riskGroups.forEach(function(rg) {
      const isSel = (rg.id == preSelectedRgId) ? 'selected' : '';
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
          <span class="badge bg-secondary ms-2">${preSelectedRgName}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-info text-dark" id="new_calc_badge_${qIndex}">R = 6 (Önemli Risk)</span>
          <button type="button" class="btn btn-sm btn-outline-danger text-white remove-question-btn">
            <i class="bi bi-trash"></i> Sil
          </button>
        </div>
      </div>

      <div class="p-3">
        <!-- 1. SÜTUN: RİSK GRUBU SEÇİMİ -->
        <div class="row g-3 mb-3 bg-light p-2 rounded-3 border">
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-dark"><i class="bi bi-diagram-3-fill text-warning me-1"></i> 1. Risk Grubu</label>
            <select name="new_questions[${qIndex}][risk_group_id]" class="form-select form-select-sm fw-bold">
              ${riskGroupOptionsHtml}
            </select>
          </div>

          <!-- 2. SÜTUN: TEHLİKE KAYNAĞI -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-dark">2. Tehlike Kaynağı (Kütüphaneden)</label>
            <input type="text" name="new_questions[${qIndex}][hazard_source]" list="hazard_sources_list" class="form-control form-control-sm" placeholder="Örn: Lavabo, Wc tavanı">
          </div>

          <!-- 3. SÜTUN: TEHLİKE -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-dark">3. Tehlike (Kütüphaneden)</label>
            <input type="text" name="new_questions[${qIndex}][hazard_name]" list="hazards_list" class="form-control form-control-sm" placeholder="Örn: Enfeksiyon, Kaygan zemin">
          </div>
        </div>

        <!-- 4. ETKİLENME VE 5. ETKİLENENLER -->
        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6">
            <label class="form-label fw-bold fs-8 text-muted">4. Etkilenme (Yaşanabilecek Riskler)</label>
            <input type="text" name="new_questions[${qIndex}][affected_risk]" class="form-control form-control-sm" placeholder="Örn: Pis su bulaşma, enfeksiyon maruziyeti">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label fw-bold fs-8 text-muted">5. Etkilenenler (Kütüphaneden)</label>
            <input type="text" name="new_questions[${qIndex}][affected_people]" list="affected_list" class="form-control form-control-sm" placeholder="Örn: Çalışanlar(Doktor, Hemşire), Hasta ve yakını">
          </div>
        </div>

        <!-- 6. MEVCUT DURUM VE KONTROL SORUSU -->
        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6">
            <label class="form-label fw-bold fs-8 text-muted">6. Mevcut Durum / Saha Tespiti</label>
            <input type="text" name="new_questions[${qIndex}][current_status]" class="form-control form-control-sm" placeholder="Örn: Lavabolar tavanda su akıntısı mevcut">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label fw-bold fs-8 text-muted">Saha Denetim Sorusu Metni</label>
            <input type="text" name="new_questions[${qIndex}][question_text]" class="form-control form-control-sm" placeholder="Örn: WC tavanında su sızıntısı var mı?">
          </div>
        </div>

        <!-- 7. OLASILIK, 8. ŞİDDET VE 9. RİSK DERECESİ -->
        <div class="row g-3 mb-3 p-2 rounded-3 bg-light border border-info">
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-dark">7. Olasılık ($O: 1-5$)</label>
            <select name="new_questions[${qIndex}][default_probability]" class="form-select form-select-sm new-risk-calc" id="new_prob_${qIndex}" data-newqindex="${qIndex}">
              <option value="1">1 - Çok Küçük</option>
              <option value="2" selected>2 - Küçük</option>
              <option value="3">3 - Orta</option>
              <option value="4">4 - Yüksek</option>
              <option value="5">5 - Çok Yüksek</option>
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-dark">8. Şiddet ($Ş: 1-5$)</label>
            <select name="new_questions[${qIndex}][default_severity]" class="form-select form-select-sm new-risk-calc" id="new_sev_${qIndex}" data-newqindex="${qIndex}">
              <option value="1">1 - Çok Hafif</option>
              <option value="2">2 - Hafif</option>
              <option value="3" selected>3 - Ciddi</option>
              <option value="4">4 - Çok Ciddi</option>
              <option value="5">5 - Felaket</option>
            </select>
          </div>

          <div class="col-12 col-md-4 d-flex align-items-center">
            <div class="w-100 text-center">
              <div class="text-muted fs-8 fw-bold">9. Risk Derecesi ($R = O \\times Ş$)</div>
              <div class="fs-5 fw-extrabold text-primary" id="new_risk_val_${qIndex}">6</div>
            </div>
          </div>
        </div>

        <!-- 10. ALINACAK ÖNLEMLER, 11. SORUMLU VE 12. SÜRE -->
        <div class="row g-3">
          <div class="col-12 col-md-5">
            <label class="form-label fw-bold fs-8 text-dark">10. Alınacak Önlemler / İyileştirmeler (Kütüphaneden)</label>
            <input type="text" name="new_questions[${qIndex}][default_action_plan]" list="recommendations_list" class="form-control form-control-sm" placeholder="Örn: Lavabo (WC) tavanlarında gerekli yalıtımın sağlanması">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label fw-bold fs-8 text-dark">11. Sorumlu Birim (Kütüphaneden)</label>
            <input type="text" name="new_questions[${qIndex}][default_responsible]" list="responsibles_list" class="form-control form-control-sm" placeholder="Örn: Tekn. Hiz. Yön.">
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label fw-bold fs-8 text-dark">12. Başlama / Süre</label>
            <input type="text" name="new_questions[${qIndex}][default_deadline]" class="form-control form-control-sm" placeholder="Örn: 6 Ay, Sürekli">
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
        qCard.remove();
      });
    }

    qCard.querySelectorAll('.new-risk-calc').forEach(select => {
      select.addEventListener('change', function() {
        const idx = this.dataset.newqindex;
        const probSelect = document.getElementById('new_prob_' + idx);
        const sevSelect = document.getElementById('new_sev_' + idx);
        const valDiv = document.getElementById('new_risk_val_' + idx);
        const badgeSpan = document.getElementById('new_calc_badge_' + idx);

        if (probSelect && sevSelect && valDiv && badgeSpan) {
          const p = parseInt(probSelect.value) || 1;
          const s = parseInt(sevSelect.value) || 1;
          const r = p * s;

          valDiv.textContent = r;

          let category = 'Kabul Edilebilir Risk';
          let badgeBg = 'bg-success';
          if (r >= 16) { category = 'Kabul Edilemez Risk'; badgeBg = 'bg-danger'; }
          else if (r >= 10) { category = 'Dikkate Değer Risk'; badgeBg = 'bg-warning text-dark'; }
          else if (r >= 6) { category = 'Önemli Risk'; badgeBg = 'bg-info text-dark'; }

          badgeSpan.className = 'badge ' + badgeBg;
          badgeSpan.textContent = `R = ${r} (${category})`;
        }
      });
    });
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

  templateCards.forEach(card => {
    card.addEventListener('click', function () {
      templateCards.forEach(c => c.classList.remove('selected', 'border-success', 'shadow-md'));
      this.classList.add('selected', 'border-success', 'shadow-md');
      
      const tId = this.dataset.templateId;
      selectedTemplateInput.value = tId;
      checkWizardReady();
    });
  });

  unitCards.forEach(card => {
    card.addEventListener('click', function () {
      unitCards.forEach(c => c.classList.remove('selected', 'border-success', 'shadow-md'));
      this.classList.add('selected', 'border-success', 'shadow-md');
      
      const uId = this.dataset.unitId;
      selectedUnitInput.value = uId;
      checkWizardReady();
    });
  });

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
