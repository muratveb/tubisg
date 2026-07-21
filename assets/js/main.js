/**
 * Tubİsg - Main Interactive Script
 * Real-time Score Calculator, Mobile Touch Handler, Dynamic Forms & AJAX Unit Creator
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

  // 2. Audit Fill Page: Touch Option Card Click & Real-time Live Score Calculation
  const auditForm = document.getElementById('auditFillForm');
  if (auditForm) {
    initAuditFormCalculator();
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

});

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

      // Sorunun olası maks pozitif puanı
      allOptionsInQuestion.forEach(optCard => {
        const pts = parseInt(optCard.dataset.points) || 0;
        if (pts > qMaxPoints) {
          qMaxPoints = pts;
        }
      });
      maxPossible += qMaxPoints;

      // Seçili seçeneklerin puan toplamı
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

  // Kart Tıklama ve Seçim Olayı
  optionCards.forEach(card => {
    card.addEventListener('click', function (e) {
      const checkbox = this.querySelector('.option-checkbox');
      const checkIcon = this.querySelector('.check-icon');
      if (!checkbox) return;

      // Checkbox durumunu tersine çevir
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
        if (confirm('Bu soruyu silmek istediğinize emin misiniz?')) {
          qCard.remove();
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
          const unitSelect = document.getElementById('unit_id');
          if (unitSelect) {
            const opt = document.createElement('option');
            opt.value = data.unit.id;
            opt.textContent = data.unit.unit_name;
            opt.selected = true;
            unitSelect.appendChild(opt);
          }

          form.reset();
          if (modalElem && window.bootstrap) {
            const modalInstance = bootstrap.Modal.getInstance(modalElem);
            if (modalInstance) modalInstance.hide();
          }
          alert('Birim başarıyla eklendi ve seçildi: ' + data.unit.unit_name);
        } else {
          alert('Hata: ' + (data.message || 'Birim eklenemedi.'));
        }
      })
      .catch(err => {
        console.error(err);
        alert('Bir hata oluştu.');
      });
  });
}
