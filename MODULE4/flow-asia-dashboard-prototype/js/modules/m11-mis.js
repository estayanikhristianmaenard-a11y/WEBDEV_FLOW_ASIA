import { mockEmployees } from '../data/mock-employees.js';
import { store } from '../core/state.js';

let requests = [
  { id: 1, name: 'Juan Dela Cruz', details: 'Vacation leave, Oct 14-15' },
  { id: 2, name: 'Ana Lim', details: 'Overtime, 2.5 hours, Oct 12' }
];

export function initMIS() {
  renderRequests();

  initEmployeeSearch();

  document.getElementById('clear-inspection-btn').addEventListener('click', () => {
    store.setInspectedEmployee(null);
    document.getElementById('hr-inspection-banner').classList.add('hidden');
    document.getElementById('hr-search-input').value = '';
  });
}

function initEmployeeSearch() {
  const input = document.getElementById('hr-search-input');
  const list = document.createElement('div');
  list.id = 'employee-search-results'; list.setAttribute('role', 'listbox');
  input.closest('.hr-search-bar').after(list);
  input.placeholder = 'Search name, ID or department';
  input.setAttribute('aria-label', input.placeholder);
  input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
  input.setAttribute('aria-controls', list.id); input.setAttribute('aria-expanded', 'false');
  let matches = [], active = -1, timer;
  function clear() {
    clearTimeout(timer); matches = []; active = -1; list.replaceChildren();
    input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant');
  }
  function select(employee) {
    clear(); input.value = employee.name; store.setInspectedEmployee(employee.id);
  }
  function search() {
    clear();
    const term = input.value.trim().toLowerCase();
    if (term.length < 2 || store.getState().role !== 'HR_ADMIN') return;
    matches = mockEmployees.filter(employee => term.split(/\s+/).every(word =>
      `${employee.name} ${employee.id} ${employee.department}`.toLowerCase().includes(word))).slice(0, 10);
    if (!matches.length) {
      const text = document.createElement('p'); text.className = 'muted'; text.textContent = 'No matching employees.'; list.append(text);
    }
    matches.forEach((employee, index) => {
      const option = document.createElement('button'); option.type = 'button'; option.className = 'employee-search-option';
      option.id = `employee-result-${index}`; option.setAttribute('role', 'option'); option.setAttribute('aria-selected', 'false');
      const name = document.createElement('span'); name.className = 'employee-search-name'; name.textContent = employee.name;
      const meta = document.createElement('span'); meta.className = 'employee-search-meta'; meta.textContent = `${employee.id} · ${employee.department}`;
      option.append(name, meta); option.addEventListener('click', () => select(employee)); list.append(option);
    });
    input.setAttribute('aria-expanded', String(matches.length > 0));
  }
  input.addEventListener('input', () => { clear(); timer = setTimeout(search, 275); });
  document.getElementById('hr-search-btn').addEventListener('click', search);
  document.getElementById('clear-inspection-btn').addEventListener('click', clear);
  store.subscribe(state => { if (state.role !== 'HR_ADMIN') clear(); });
  input.addEventListener('keydown', event => {
    if (event.key === 'Escape') clear();
    if (['ArrowDown', 'ArrowUp'].includes(event.key) && matches.length) {
      event.preventDefault();
      active = active < 0 ? (event.key === 'ArrowDown' ? 0 : matches.length - 1)
        : (active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length;
      [...list.children].forEach((option, index) => option.setAttribute('aria-selected', String(index === active)));
      input.setAttribute('aria-activedescendant', `employee-result-${active}`);
      list.children[active].scrollIntoView({ block: 'nearest' });
    }
    if (event.key === 'Enter') {
      event.preventDefault(); if (active >= 0 && matches[active]) select(matches[active]); else search();
    }
  });
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
