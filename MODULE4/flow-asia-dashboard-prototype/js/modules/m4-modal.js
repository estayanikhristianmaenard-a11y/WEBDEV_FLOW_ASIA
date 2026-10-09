export function initModal() {
  document.getElementById('modal-close-btn').addEventListener('click', closeModal);
  document.getElementById('modal-dismiss-btn').addEventListener('click', closeModal);
  document.getElementById('day-modal').addEventListener('click', event => {
    if (event.target.id === 'day-modal') closeModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeModal();
  });
}

export function openDayModal(day, data) {
  const modal = document.getElementById('day-modal');
  document.getElementById('modal-date-title').textContent = `October ${day}, 2026`;

  if (!data) {
    document.getElementById('modal-shift').textContent = '09:00 AM - 06:00 PM';
    document.getElementById('modal-status').textContent = 'Present';
    document.getElementById('modal-time').textContent = '08:55 AM / 06:02 PM';
    document.getElementById('modal-hours').textContent = '8.0 hrs';
    document.getElementById('modal-late').textContent = '0 Mins';
    document.getElementById('modal-ot').textContent = '0.0 hrs';
    document.getElementById('modal-cr-box').innerHTML = `
      <div class="cr-badge-box cr3-report">
        <span>📝 Daily Progress Report Submitted</span>
        <button class="btn btn-secondary report-btn" type="button">View Report</button>
      </div>
    `;
    document.querySelector('.report-btn').addEventListener('click', () => alert('Viewing Progress Report...'));
  } else {
    document.getElementById('modal-shift').textContent = data.shift || 'N/A';
    document.getElementById('modal-status').textContent = data.name;
    document.getElementById('modal-time').textContent = data.time || 'N/A';
    document.getElementById('modal-hours').textContent = data.type === 'P' || data.type === 'L' ? '8.0 hrs' : '0.0 hrs';
    document.getElementById('modal-late').textContent = `${data.late || 0} Mins`;
    document.getElementById('modal-ot').textContent = `${data.ot || 0} hrs`;

    let crContent = '';
    if (data.type === 'INV') {
      crContent += `
        <div class="cr-badge-box cr1-invalid">
          <strong>⚠️ CR1: Punch Status Invalid</strong>
          <div style="font-size:0.75rem; margin-top:4px;">${data.rejectionReason}</div>
        </div>
      `;
    }
    if (data.report) {
      crContent += `
        <div class="cr-badge-box cr3-report" style="margin-top:8px;">
          <span>📝 Daily Progress Report Submitted</span>
          <button class="btn btn-secondary report-btn" type="button">View Report</button>
        </div>
      `;
    }

    document.getElementById('modal-cr-box').innerHTML = crContent;
    const reportButton = document.querySelector('.report-btn');
    if (reportButton) reportButton.addEventListener('click', () => alert(`Viewing Progress Report for Oct ${day}...`));
  }

  modal.classList.add('open');
  document.getElementById('modal-close-btn').focus();
}

function closeModal() {
  document.getElementById('day-modal').classList.remove('open');
}
