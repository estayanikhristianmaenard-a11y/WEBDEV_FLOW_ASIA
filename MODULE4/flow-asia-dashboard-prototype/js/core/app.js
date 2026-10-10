import { store } from './state.js';
import { mockEmployees } from '../data/mock-employees.js';
import { icon, initIcons } from './icons.js';
import { initTheme } from './theme.js';
import { renderCalendar } from '../modules/m4-calendar.js';
import { initModal } from '../modules/m4-modal.js';
import { initMIS } from '../modules/m11-mis.js';
import { initCharts } from '../modules/m11-charts.js';

document.addEventListener('DOMContentLoaded', () => {
  initIcons();
  initTheme();
  initModal();
  if (window.flowAsiaBackend) {
    import('../modules/m4-backend.js').then(module => module.initBackendDashboard()).catch(error => {
      const message = document.createElement('p');
      message.setAttribute('role', 'alert');
      message.textContent = `Dashboard could not load: ${error.message}`;
      document.querySelector('main').prepend(message);
    });
    return;
  }
  initMIS();
  if (typeof Chart !== 'undefined') initCharts();
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
      roleBtn.innerHTML = `${icon('switch')} Switch to Staff View`;
    } else {
      hrView.classList.add('hidden');
      staffView.classList.remove('hidden');
      badge.textContent = 'STAFF VIEW';
      title.textContent = 'Staff Schedule Dashboard';
      sub.textContent = 'Personal monthly attendance, schedule & leave tracking';
      userName.textContent = 'Maria Santos';
      userRole.textContent = 'Creative Designer';
      avatar.textContent = 'MS';
      roleBtn.innerHTML = `${icon('switch')} Switch to HR Admin`;
    }

    const inspecting = state.role === 'HR_ADMIN' && Boolean(state.inspectedEmployeeId);
    document.getElementById('calendar-inspection-header').classList.toggle('hidden', !inspecting);
    document.getElementById('hr-inspection-banner').classList.toggle('hidden', !inspecting);
    document.getElementById('personal-quick-actions').hidden = inspecting;
    if (inspecting) {
      const employee = mockEmployees.find(item => item.id === state.inspectedEmployeeId);
      document.getElementById('calendar-employee-name').textContent = employee.name;
      document.getElementById('calendar-employee-meta').textContent = `${employee.id} · ${employee.department} · ${employee.title}`;
      document.getElementById('inspected-emp-name').textContent = employee.name;
      renderCalendar(state.inspectedEmployeeId);
      staffView.classList.remove('hidden');
    } else {
      renderCalendar('EMP-001');
    }
  });
});
