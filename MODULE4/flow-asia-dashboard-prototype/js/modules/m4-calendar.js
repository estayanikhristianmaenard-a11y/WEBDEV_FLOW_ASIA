import { mockAttendanceData } from '../data/mock-attendance.js';
import { openDayModal } from './m4-modal.js';

export function renderCalendar(empId = 'EMP-001') {
  const grid = document.getElementById('staff-calendar-grid');
  const headers = Array.from(grid.children).slice(0, 7);
  grid.innerHTML = '';
  headers.forEach(header => grid.appendChild(header));

  const empData = mockAttendanceData[empId];
  if (!empData) {
    ['present', 'late', 'absent', 'leave'].forEach(key => {
      document.getElementById(`kpi-${key}`).textContent = '—';
    });
    document.getElementById('leave-balance-used').textContent = 'No records';
    const message = document.createElement('p');
    message.className = 'calendar-empty-state muted';
    message.textContent = 'No attendance records available for this employee in October 2026.';
    grid.appendChild(message);
    return;
  }
  document.getElementById('kpi-present').textContent = empData.kpis.present;
  document.getElementById('kpi-late').textContent = empData.kpis.late;
  document.getElementById('kpi-absent').textContent = empData.kpis.absent;
  document.getElementById('kpi-leave').textContent = empData.kpis.leaveUsed;
  document.getElementById('leave-balance-used').textContent = `${empData.kpis.leaveUsed} days`;

  const emptyDays = 4;
  const totalDays = 31;

  for (let i = 0; i < emptyDays; i++) {
    const emptyCell = document.createElement('div');
    emptyCell.className = 'cell empty';
    emptyCell.setAttribute('aria-hidden', 'true');
    grid.appendChild(emptyCell);
  }

  for (let day = 1; day <= totalDays; day++) {
    const cell = document.createElement('button');
    const data = empData.calendar[day];
    const statusClass = data ? `s-${data.type}` : 's-P';
    const tagText = data ? data.name.toUpperCase() : 'PRESENT';

    cell.type = 'button';
    cell.className = `cell ${statusClass}`;
    cell.setAttribute('role', 'gridcell');
    cell.setAttribute('aria-label', `October ${day}: ${tagText}`);
    cell.innerHTML = `<span class="cell-num">${day}</span><span class="cell-tag">${tagText}</span>`;
    cell.addEventListener('click', () => openDayModal(day, data));
    grid.appendChild(cell);
  }
}
