import { mockEmployees } from '../data/mock-employees.js';
import { store } from '../core/state.js';

let requests = [
  { id: 1, name: 'Juan Dela Cruz', details: 'Vacation leave, Oct 14-15' },
  { id: 2, name: 'Ana Lim', details: 'Overtime, 2.5 hours, Oct 12' }
];

export function initMIS() {
  renderRequests();

  document.getElementById('hr-search-btn').addEventListener('click', inspectEmployee);
  document.getElementById('hr-search-input').addEventListener('keydown', event => {
    if (event.key === 'Enter') inspectEmployee();
  });

  document.getElementById('clear-inspection-btn').addEventListener('click', () => {
    store.setInspectedEmployee(null);
    document.getElementById('hr-inspection-banner').classList.add('hidden');
    document.getElementById('hr-search-input').value = '';
  });
}

function inspectEmployee() {
  const val = document.getElementById('hr-search-input').value.trim().toLowerCase();
  const found = mockEmployees.find(employee => employee.name.toLowerCase().includes(val));
  if (found) {
    store.setInspectedEmployee(found.id);
    document.getElementById('inspected-emp-name').textContent = `${found.name} (${found.title})`;
    document.getElementById('hr-inspection-banner').classList.remove('hidden');
  } else {
    alert('Employee not found in database roster.');
  }
}

function renderRequests() {
  const container = document.getElementById('hr-requests-list');
  document.getElementById('hr-pending-count').textContent = requests.length;
  container.innerHTML = '';

  if (requests.length === 0) {
    container.innerHTML = '<div style="font-size:0.85rem; color:var(--ink-muted);">No pending requests requiring review.</div>';
    return;
  }

  requests.forEach(req => {
    const item = document.createElement('div');
    item.className = 'req-item';
    item.innerHTML = `
      <div class="req-info">
        <div>${req.name}</div>
        <div>${req.details}</div>
      </div>
      <div class="req-actions">
        <button class="btn btn-primary" type="button" style="padding:4px 12px; font-size:0.75rem;">Approve</button>
        <button class="btn btn-secondary" type="button" style="padding:4px 12px; font-size:0.75rem;">Decline</button>
      </div>
    `;

    const buttons = item.querySelectorAll('button');
    buttons[0].addEventListener('click', () => resolveRequest(req.id));
    buttons[1].addEventListener('click', () => resolveRequest(req.id));
    container.appendChild(item);
  });
}

function resolveRequest(id) {
  requests = requests.filter(request => request.id !== id);
  renderRequests();
}
