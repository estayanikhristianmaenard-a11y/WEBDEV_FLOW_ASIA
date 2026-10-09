import { store } from './state.js';
import { initTheme } from './theme.js';
import { renderCalendar } from '../modules/m4-calendar.js';
import { initModal } from '../modules/m4-modal.js';
import { initMIS } from '../modules/m11-mis.js';
import { initCharts } from '../modules/m11-charts.js';

document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  initModal();
  initMIS();
  initCharts();
  renderCalendar('EMP-001');

  const roleBtn = document.getElementById('role-toggle-btn');
  roleBtn.addEventListener('click', () => {
    const current = store.getState().role;
    store.setRole(current === 'STAFF' ? 'HR_ADMIN' : 'STAFF');
  });

  document.getElementById('export-pdf-btn').addEventListener('click', () => {
    window.print();
  });
  document.getElementById('leave-request-btn').addEventListener('click', () => {
    alert('Leave request form is not connected in this prototype.');
  });

  store.subscribe(state => {
    const staffView = document.getElementById('staff-dashboard-view');
    const hrView = document.getElementById('hr-dashboard-view');
    const badge = document.getElementById('role-badge');
    const title = document.getElementById('header-dashboard-title');
    const sub = document.getElementById('header-dashboard-sub');
    const userName = document.getElementById('user-display-name');
    const userRole = document.getElementById('user-display-role');
    const avatar = document.getElementById('user-avatar');

    if (state.role === 'HR_ADMIN') {
      staffView.classList.add('hidden');
      hrView.classList.remove('hidden');
      badge.textContent = 'HR ADMIN VIEW';
      title.textContent = 'HR Management Dashboard';
      sub.textContent = 'Company-wide attendance metrics, approvals & MIS forecasts';
      userName.textContent = 'HR Manager';
      userRole.textContent = 'People Operations';
      avatar.textContent = 'HR';
      roleBtn.innerHTML = '🔄 Switch to Staff View';
    } else {
      hrView.classList.add('hidden');
      staffView.classList.remove('hidden');
      badge.textContent = 'STAFF VIEW';
      title.textContent = 'Staff Schedule Dashboard';
      sub.textContent = 'Personal monthly attendance, schedule & leave tracking';
      userName.textContent = 'Maria Santos';
      userRole.textContent = 'Creative Designer';
      avatar.textContent = 'MS';
      roleBtn.innerHTML = '🔄 Switch to HR Admin';
    }

    if (state.inspectedEmployeeId) {
      renderCalendar(state.inspectedEmployeeId);
      staffView.classList.remove('hidden');
    } else {
      renderCalendar('EMP-001');
    }
  });
});
