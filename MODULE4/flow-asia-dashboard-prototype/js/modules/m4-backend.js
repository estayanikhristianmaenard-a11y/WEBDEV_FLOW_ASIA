import { request } from '../core/api.js';
import { calendarDates, renderTeamCalendar, renderTeamLegend } from './m4-team-calendar.js';

const config = window.flowAsiaBackend;
const canManage = config.hrRoles.includes(config.user.role);
const canInspect = canManage || config.user.role === 'supervisor';
let month = config.month;
let team = null;
let employeeCalendar = null;
let employeeLoadVersion = 0;
let employeeLoadError = '';
let events = [];
let selectedId = null;
let loadVersion = 0;
let searchVersion = 0;
let searchAbort;
let results = [];
let activeResult = -1;
let searchTimer;
let calendarView = 'month';
let calendarWeek = 0;
const $ = id => document.getElementById(id);

function element(tag, text, className) {
  const node = document.createElement(tag);
  if (text !== undefined) node.textContent = text;
  if (className) node.className = className;
  return node;
}

function button(text, action, className = 'btn btn-secondary') {
  const node = element('button', text, className);
  node.type = 'button';
  node.addEventListener('click', action);
  return node;
}

function message(text) { $('backend-status').textContent = text; }

export async function initBackendDashboard() {
  const status = element('p', '', 'muted');
  status.id = 'backend-status';
  status.setAttribute('role', 'status');
  document.querySelector('main').prepend(status);
  const switcher = $('role-toggle-btn').closest('.role-switcher-card');
  switcher.remove();
  $('user-display-name').textContent = config.user.name;
  $('user-display-role').textContent = config.user.role.replaceAll('_', ' ');
  $('user-avatar').textContent = config.user.name.split(' ').map(part => part[0]).slice(0, 2).join('');
  $('role-badge').textContent = canManage ? 'HR VIEW' : canInspect ? 'SUPERVISOR VIEW' : 'STAFF VIEW';
  $('header-dashboard-title').textContent = canInspect ? 'Calendar Management Dashboard' : 'Schedule Dashboard';
  $('header-dashboard-sub').textContent = canInspect ? 'Employee calendars, team calendar and company events' : 'Company events and schedule';
  $('hr-dashboard-view').classList.toggle('hidden', !canInspect);
  $('personal-quick-actions').hidden = true;
  document.querySelector('.mis-grid').hidden = true;
  document.querySelector('.widget-panel .widget-box').hidden = false;
  $('export-pdf-btn').textContent = 'Export PDF';
  $('export-pdf-btn').addEventListener('click', exportPdf);
  document.querySelectorAll('#staff-dashboard-view .card-kpi .kpi-subtext').forEach(node => { node.textContent = 'Recorded days in selected month'; });
  const personalLegend = document.querySelector('.cal-container .lg');
  personalLegend.replaceChildren();
  Object.values(config.statuses).forEach(status => {
    const item = element('span', undefined, 'lg-item');
    item.append(element('span', status.code, `team-status team-status-${status.ui_class}`), element('span', status.label));
    personalLegend.append(item);
  });
  personalLegend.append(element('span', '— No record', 'lg-item'), element('span', '◆ Company event', 'lg-item'), element('span', 'R Report submitted', 'lg-item'));

  const oldMonthButton = document.querySelector('.cal-header button');
  const monthInput = element('input', undefined, 'btn btn-secondary');
  monthInput.id = 'employee-calendar-month';
  monthInput.type = 'month'; monthInput.value = month;
  monthInput.min = '0001-01'; monthInput.max = '9999-12';
  monthInput.setAttribute('aria-label', 'Calendar month');
  const monthControls = element('div', undefined, 'calendar-month-controls');
  const viewMode = element('select', undefined, 'btn btn-secondary');
  viewMode.id = 'employee-calendar-view'; viewMode.setAttribute('aria-label', 'Calendar view');
  viewMode.append(new Option('Month', 'month'), new Option('Week', 'week'));
  viewMode.addEventListener('change', () => { calendarView = viewMode.value; calendarWeek = 0; renderCalendar(); });
  function changeMonth(offset) {
    const [year, number] = month.split('-').map(Number);
    const total = year * 12 + number - 1 + offset;
    const nextYear = Math.floor(total / 12);
    if (nextYear < 1 || nextYear > 9999) return;
    monthInput.value = `${String(nextYear).padStart(4, '0')}-${String(total % 12 + 1).padStart(2, '0')}`;
    monthInput.dispatchEvent(new Event('change'));
  }
  function changePeriod(offset) {
    if (calendarView === 'week') {
      const offsetDays = new Date(`${month}-01T00:00:00Z`).getUTCDay();
      const weeks = Math.ceil((offsetDays + calendarDates(month).length) / 7);
      const nextWeek = calendarWeek + offset;
      if (nextWeek >= 0 && nextWeek < weeks) { calendarWeek = nextWeek; renderCalendar(); return; }
      changeMonth(offset);
      calendarWeek = offset < 0 ? -1 : 0;
      return;
    }
    changeMonth(offset);
  }
  const previousPeriod = button('Previous', () => changePeriod(-1)); previousPeriod.setAttribute('aria-label', 'Previous calendar period');
  const nextPeriod = button('Next', () => changePeriod(1)); nextPeriod.setAttribute('aria-label', 'Next calendar period');
  monthControls.append(previousPeriod, monthInput, nextPeriod, viewMode);
  oldMonthButton.replaceWith(monthControls);
  monthInput.addEventListener('change', () => {
    if (!/^\d{4}-\d{2}$/.test(monthInput.value) || !monthInput.validity.valid) return;
    month = monthInput.value;
    calendarWeek = 0;
    if ($('team-calendar-month')) $('team-calendar-month').value = month;
    refresh();
  });

  const teamCard = element('section', undefined, 'card');
  teamCard.id = 'team-calendar-card';
  teamCard.hidden = !canInspect;
  teamCard.append(element('h2', 'Team / department calendar', 'widget-title'));
  const teamControls = element('div', undefined, 'team-calendar-controls');
  const department = element('select', undefined, 'btn btn-secondary');
  department.id = 'team-department';
  department.setAttribute('aria-label', 'Filter department');
  department.append(new Option('All permitted departments', ''));
  department.addEventListener('change', renderTeam);
  const teamMonth = element('input', undefined, 'btn btn-secondary');
  teamMonth.id = 'team-calendar-month'; teamMonth.type = 'month'; teamMonth.value = month;
  teamMonth.min = monthInput.min; teamMonth.max = monthInput.max;
  teamMonth.setAttribute('aria-label', 'Team calendar month');
  teamMonth.addEventListener('change', () => {
    monthInput.value = teamMonth.value;
    monthInput.dispatchEvent(new Event('change'));
  });
  const previousTeamMonth = button('Previous', () => changeMonth(-1));
  previousTeamMonth.setAttribute('aria-label', 'Previous team month');
  const nextTeamMonth = button('Next', () => changeMonth(1));
  nextTeamMonth.setAttribute('aria-label', 'Next team month');
  teamControls.append(department, previousTeamMonth, teamMonth, nextTeamMonth);
  const calendar = element('div', undefined, 'team-calendar-scroll'); calendar.id = 'team-calendar';
  calendar.tabIndex = 0; calendar.setAttribute('role', 'region'); calendar.setAttribute('aria-label', 'Read-only team attendance calendar');
  const legend = element('div', undefined, 'lg'); legend.id = 'team-calendar-legend';
  legend.setAttribute('aria-label', 'Team calendar status legend');
  renderTeamLegend(legend, config.statuses);
  const indicators = element('div'); indicators.id = 'team-indicators';
  teamCard.append(teamControls, element('p', 'Select an employee or day to inspect their read-only calendar. Scroll across to see every date.', 'muted'), calendar, legend, indicators);
  $('hr-dashboard-view').insertBefore(teamCard, $('hr-requests-list').closest('.card'));
  document.querySelector('#hr-requests-list').previousElementSibling.textContent = 'Pending leave / OT · Read-only';
  const eventCard = element('section', undefined, 'widget-box');
  eventCard.append(element('h2', 'Company events', 'widget-title'));
  if (canManage) eventCard.append(button('Create event', () => editEvent(), 'btn btn-primary'));
  const list = element('div'); list.id = 'company-event-list'; eventCard.append(list);
  document.querySelector('.widget-panel').append(eventCard);
  const summaryCards = document.querySelector('#staff-dashboard-view .g4');
  for (const [id, label] of [['late-minutes', 'Total late minutes'], ['cutoff-ot', 'OT hours this cutoff']]) {
    const card = element('article', undefined, 'card card-kpi');
    const value = element('span', '—', 'kpi-num'); value.id = `kpi-${id}`;
    card.append(value, element('span', label, 'kpi-label'), element('span', 'From calendar source records', 'kpi-subtext'));
    summaryCards.append(card);
  }
  document.querySelector('#kpi-leave + .kpi-label').textContent = 'Leave days used';
  initSearch();
  $('clear-inspection-btn').addEventListener('click', () => {
    selectedId = null; $('hr-search-input').value = ''; loadEmployeeCalendar().catch(() => {});
  });
  await refresh();
}

async function refresh() {
  const version = ++loadVersion;
  const selectedMonth = month;
  team = null; events = [];
  renderCalendar();
  if (canInspect) {
    $('team-calendar').textContent = 'Loading team calendar…';
    $('team-calendar').setAttribute('aria-busy', 'true');
    $('team-indicators').textContent = 'Loading team…'; $('hr-requests-list').replaceChildren();
    document.querySelectorAll('#hr-dashboard-view .kpi-num').forEach(node => { node.textContent = '—'; });
  }
  $('company-event-list').textContent = 'Loading events…';
  message('Loading calendar…');
  const responses = await Promise.allSettled([
    canInspect ? request(`/hr/calendar/team?month=${selectedMonth}`) : Promise.resolve(null),
    loadEmployeeCalendar()
  ]);
  if (version !== loadVersion) return;
  team = responses[0].status === 'fulfilled' ? responses[0].value : null;
  if (team) {
    const filter = $('team-department');
    const previous = filter.value;
    filter.replaceChildren(new Option('All permitted departments', ''));
    const departments = new Map(team.employees.filter(employee => employee.department).map(employee => [employee.department.id, employee.department.name]));
    departments.forEach((name, id) => filter.append(new Option(name, String(id))));
    filter.value = [...filter.options].some(option => option.value === previous) ? previous : '';
  }
  renderTeam(); renderCalendar(); renderEvents();
  const errors = responses.filter(response => response.status === 'rejected').map(response => response.reason.message);
  if (employeeLoadError) errors.push(employeeLoadError);
  message(errors.join(' ') || (canInspect
    ? 'Calendar loaded. Employee attendance, leave and OT are read-only.'
    : 'Calendar loaded. Attendance is read-only.'));
}

async function exportPdf() {
  const employeeId = selectedId ?? config.user.employee_id;
  if (!employeeId) { message('Select an employee before exporting.'); return; }
  const exportMonth = month;
  const control = $('export-pdf-btn'); control.disabled = true; control.textContent = 'Preparing PDF…';
  try {
    const blob = await request(`/calendar/employees/${employeeId}/pdf?month=${exportMonth}`, { responseType: 'blob' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a'); link.href = url; link.download = `calendar-${employeeId}-${exportMonth}.pdf`;
    document.body.append(link); link.click(); link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    message('Calendar PDF downloaded.');
  } catch (error) { message(error.message); }
  finally { control.disabled = false; control.textContent = 'Export PDF'; }
}

async function loadEmployeeCalendar() {
  const version = ++employeeLoadVersion;
  const employeeId = selectedId ?? config.user.employee_id;
  const requestedMonth = month;
  employeeCalendar = null; employeeLoadError = ''; renderCalendar();
  if (!employeeId) {
    employeeLoadError = 'No employee profile is linked to this account. Select an employee to view their calendar.';
    const exclusiveEnd = new Date(`${requestedMonth}-01T00:00:00Z`);
    exclusiveEnd.setUTCMonth(exclusiveEnd.getUTCMonth() + 1);
    const response = await request(`/calendar/events?start=${requestedMonth}-01&end=${exclusiveEnd.toISOString().slice(0, 10)}`);
    if (version !== employeeLoadVersion) return;
    events = response; renderTeam(); renderEvents(); message(employeeLoadError); return;
  }
  message('Loading employee calendar…');
  try {
    const response = await request(`/calendar/employees/${employeeId}/month?month=${requestedMonth}`);
    if (version !== employeeLoadVersion) return;
    employeeCalendar = response.data; events = response.data.company_events || [];
    renderCalendar(); renderTeam(); renderEvents(); message('Employee calendar loaded. Read-only.');
  } catch (error) {
    if (version === employeeLoadVersion) { employeeLoadError = error.message; message(error.message); throw error; }
  }
}

function renderTeam() {
  if (!canInspect) return;
  $('team-calendar').setAttribute('aria-busy', 'false');
  if (!team) {
    $('team-calendar').textContent = 'Team calendar unavailable. Choose a month to retry.';
    $('team-indicators').textContent = 'Team data unavailable.';
    $('hr-requests-list').replaceChildren(); $('hr-pending-count').textContent = '—'; return;
  }
  const departmentId = $('team-department').value;
  renderTeamCalendar($('team-calendar'), { team, events, month, departmentId, statuses: config.statuses, onInspect: inspectTeamEmployee });
  const employees = team.employees.filter(employee => !departmentId || String(employee.department?.id) === departmentId);
  const ids = new Set(employees.map(employee => employee.id));
  const node = $('team-indicators'); node.replaceChildren();
  node.append(element('p', `Today · ${team.today.date}`, 'muted'));
  for (const [key, label] of [['absent', 'Absent'], ['late', 'Late'], ['on_leave', 'On leave']]) {
    const people = team.today[key].filter(employee => ids.has(employee.id));
    node.append(element('p', `${label} (${people.length}): ${people.map(employee => employee.name).join(', ') || 'None'}`));
  }
  const overlaps = team.leave_overlaps.filter(item => !departmentId || String(item.department_id) === departmentId);
  node.append(element('p', `Approved leave overlaps · ${month}`, 'muted'));
  overlaps.forEach(item => node.append(element('p', `${item.date}: ${item.employee_ids.map(id => team.employees.find(employee => employee.id === id)?.name).join(', ')}`)));
  if (!overlaps.length) node.append(element('p', 'No overlapping approved leaves.'));
  const pending = $('hr-requests-list'); pending.replaceChildren();
  let count = 0;
  for (const [key, label] of [['leave', 'Pending leave'], ['overtime', 'Pending OT']]) {
    team.pending[key].filter(item => ids.has(item.employee_id)).forEach(item => {
      count++;
      const person = team.employees.find(employee => employee.id === item.employee_id);
      pending.append(element('div', `${person.name} · ${label} · ${item.date}${item.hours ? ` · ${item.hours} hours` : ''}`, 'req-item'));
    });
  }
  if (!count) pending.append(element('p', 'No pending indicators in this month.', 'muted'));
  const cards = document.querySelectorAll('#hr-dashboard-view .card-kpi');
  const values = [employees.length, team.today.absent.filter(person => ids.has(person.id)).length, count, new Set(employees.map(person => person.department?.id).filter(Boolean)).size];
  const labels = ['Permitted employees', 'Absent today', 'Pending daily indicators', 'Departments'];
  cards.forEach((card, index) => {
    card.querySelector('.kpi-num').textContent = values[index];
    card.querySelector('.kpi-label').textContent = labels[index];
    card.querySelector('.kpi-subtext').textContent = index === 1 ? team.today.date : 'Within selected department scope';
  });
}

async function inspectTeamEmployee(employee, date) {
  selectedId = employee.id;
  $('hr-search-input').value = employee.name;
  ++searchVersion; searchAbort?.abort(); clearTimeout(searchTimer); results = []; activeResult = -1;
  $('employee-search-results').replaceChildren();
  $('hr-search-input').setAttribute('aria-expanded', 'false');
  $('hr-search-input').removeAttribute('aria-activedescendant');
  const requestedMonth = month;
  const requestedVersion = employeeLoadVersion + 1;
  try {
    await loadEmployeeCalendar();
    if (requestedVersion !== employeeLoadVersion || selectedId !== employee.id || requestedMonth !== month || !employeeCalendar) return;
    if (date) {
      showDay(date, employeeCalendar.days.find(day => day.date === date), events.filter(event => event.start <= date && event.end > date));
    } else {
      $('calendar-inspection-header').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  } catch (error) { message(error.message); }
}

function initSearch() {
  const input = $('hr-search-input');
  input.placeholder = 'Search name, ID or department';
  input.setAttribute('aria-label', input.placeholder);
  input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
  input.setAttribute('aria-controls', 'employee-search-results'); input.setAttribute('aria-expanded', 'false');
  const list = element('div'); list.id = 'employee-search-results'; list.setAttribute('role', 'listbox');
  input.closest('.hr-search-bar').after(list);
  async function search() {
    const version = ++searchVersion;
    searchAbort?.abort(); searchAbort = new AbortController();
    results = []; activeResult = -1; list.replaceChildren(); input.removeAttribute('aria-activedescendant');
    input.setAttribute('aria-expanded', 'false');
    const term = input.value.trim();
    if (term.length < 2 || !canInspect) return;
    try {
      const data = await request(`/hr/employees/search?q=${encodeURIComponent(term)}`, { signal: searchAbort.signal });
      if (version !== searchVersion) return;
      results = data.data;
      list.replaceChildren();
      if (!results.length) list.append(element('p', 'No matching employees.', 'muted'));
      results.forEach((person, index) => {
        const option = button('', () => select(person), 'employee-search-option');
        option.append(element('span', person.name, 'employee-search-name'),
          element('span', `${person.employee_no} · ${person.department}`, 'employee-search-meta'));
        option.id = `employee-result-${index}`; option.setAttribute('role', 'option'); option.setAttribute('aria-selected', 'false');
        list.append(option);
      });
      input.setAttribute('aria-expanded', String(results.length > 0));
    } catch (error) { if (error.name !== 'AbortError' && version === searchVersion) message(error.message); }
  }
  function select(person) {
    selectedId = person.id; input.value = person.name; results = [];
    ++searchVersion; searchAbort?.abort(); clearTimeout(searchTimer);
    list.replaceChildren(); input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant');
    loadEmployeeCalendar().catch(() => {});
  }
  input.addEventListener('input', () => {
    ++searchVersion; searchAbort?.abort(); clearTimeout(searchTimer);
    results = []; activeResult = -1; list.replaceChildren();
    input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant');
    searchTimer = setTimeout(search, 275);
  });
  $('hr-search-btn').addEventListener('click', () => { clearTimeout(searchTimer); search(); });
  $('clear-inspection-btn').addEventListener('click', () => {
    ++searchVersion; searchAbort?.abort(); clearTimeout(searchTimer); results = []; activeResult = -1;
    list.replaceChildren(); input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant');
  });
  input.addEventListener('keydown', event => {
    if (event.key === 'Escape') { ++searchVersion; searchAbort?.abort(); clearTimeout(searchTimer); list.replaceChildren(); results = []; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); }
    if (['ArrowDown', 'ArrowUp'].includes(event.key) && results.length) {
      event.preventDefault();
      activeResult = activeResult < 0 ? (event.key === 'ArrowDown' ? 0 : results.length - 1)
        : (activeResult + (event.key === 'ArrowDown' ? 1 : -1) + results.length) % results.length;
      [...list.children].forEach((option, index) => option.setAttribute('aria-selected', String(index === activeResult)));
      input.setAttribute('aria-activedescendant', `employee-result-${activeResult}`);
      list.children[activeResult].scrollIntoView({ block: 'nearest' });
    }
    if (event.key === 'Enter') {
      event.preventDefault();
      if (activeResult >= 0 && results[activeResult]) select(results[activeResult]); else { clearTimeout(searchTimer); search(); }
    }
  });
}

const statuses = Object.fromEntries(Object.entries(config.statuses).map(([key, status]) => [key, [status.ui_class, status.label]]));

function renderCalendar() {
  const allDates = calendarDates(month);
  const offset = new Date(`${month}-01T00:00:00Z`).getUTCDay();
  const weeks = Math.ceil((offset + allDates.length) / 7);
  calendarWeek = calendarWeek < 0 ? weeks - 1 : Math.min(calendarWeek, weeks - 1);
  const weekDates = Array.from({ length: 7 }, (_, index) => allDates[calendarWeek * 7 + index - offset] || null);
  const title = new Date(`${month}-01T00:00:00Z`).toLocaleDateString('en-US', { month: 'long', year: 'numeric', timeZone: 'UTC' })
    + (calendarView === 'week' ? ` · Week ${calendarWeek + 1}` : '');
  document.querySelector('.cal-month-title').textContent = title;
  const grid = $('staff-calendar-grid');
  grid.setAttribute('aria-label', title);
  grid.closest('.cal-container').setAttribute('aria-label', `${title} attendance calendar`);
  const headers = [...grid.children].slice(0, 7); grid.replaceChildren(...headers);
  const employee = employeeCalendar;
  const inspecting = canInspect && selectedId !== null;
  $('calendar-inspection-header').classList.toggle('hidden', !inspecting);
  $('hr-inspection-banner').classList.toggle('hidden', !inspecting);
  $('calendar-employee-name').textContent = employee?.name || 'Employee records unavailable';
  $('inspected-emp-name').textContent = employee?.name || 'Employee records unavailable';
  $('calendar-employee-meta').textContent = employee ? `${employee.employee_no} · ${employee.department?.name || ''} · ${employee.position || ''}` : '';
  const days = employee?.days || [];
  for (const [id, status] of [['present', 'present'], ['late', 'late'], ['absent', 'absent'], ['leave', 'leave_approved']]) {
    $(`kpi-${id}`).textContent = employee ? (id === 'leave' ? employee.summary.leave_days_used : employee.summary.status_counts[status]) : '—';
  }
  $('kpi-late-minutes').textContent = employee?.summary.late_minutes ?? '—';
  $('kpi-cutoff-ot').textContent = employee?.summary.cutoff_ot_hours ?? '—';
  $('kpi-cutoff-ot').closest('.card').querySelector('.kpi-subtext').textContent = employee?.cutoff ? `${employee.cutoff.start} – ${employee.cutoff.end}` : 'Pay period unavailable';
  renderEmployeeWidgets(employee);
  const displayDates = calendarView === 'week' ? weekDates : [...Array(offset).fill(null), ...allDates];
  for (const date of displayDates) {
    if (!date) { grid.append(element('div', undefined, 'cell empty')); continue; }
    const number = Number(date.slice(-2));
    const row = days.find(day => day.date === date);
    const status = statuses[row?.status] || ['', row?.status || (employee ? 'No record' : 'Not loaded')];
    const dayEvents = events.filter(event => event.start <= date && event.end > date);
    const cell = button('', () => showDay(date, row, dayEvents), `cell${status[0] ? ` s-${status[0]}` : ''}`);
    cell.setAttribute('role', 'gridcell'); cell.setAttribute('aria-label', `${date}: ${status[1]}${dayEvents.length ? `, ${dayEvents.length} company events` : ''}${row?.report.status === 'submitted' ? ', report submitted' : ''}`);
    const tag = element('span', undefined, 'cell-tag');
    tag.append(element('span', status[1].toUpperCase(), 'calendar-full-label'), element('span', config.statuses[row?.status]?.code || '—', 'calendar-short-label'));
    cell.append(element('span', String(number), 'cell-num'), tag);
    row?.additional_statuses.forEach(key => {
      const extra = element('span', undefined, 'calendar-secondary-status');
      extra.title = statuses[key]?.[1] || key;
      extra.append(element('span', statuses[key]?.[1] || key, 'calendar-full-label'), element('span', config.statuses[key]?.code || key, 'calendar-short-label'));
      cell.append(extra);
    });
    if (row?.report.status === 'submitted') {
      const report = element('span', undefined, 'calendar-report-marker'); report.title = 'Report submitted';
      report.append(element('span', 'Report submitted', 'calendar-full-label'), element('span', 'R', 'calendar-short-label'));
      cell.append(report);
    }
    dayEvents.forEach(event => {
      const marker = element('span', undefined, 'company-event-marker'); marker.title = event.title;
      marker.append(element('span', `◆ ${event.title}`, 'calendar-full-label'), element('span', '◆', 'calendar-short-label'));
      cell.append(marker);
    });
    grid.append(cell);
  }
}

function showDay(date, row, dayEvents) {
  $('modal-date-title').textContent = `${employeeCalendar?.name || 'Employee'} · ${date}`;
  $('modal-status').textContent = statuses[row?.status]?.[1] || row?.status || 'No attendance record';
  for (const id of ['shift', 'time', 'hours']) $(`modal-${id}`).textContent = 'Not provided';
  if (row?.shift) $('modal-shift').textContent = `${row.shift.starts_at || '—'} / ${row.shift.ends_at || '—'}${row.shift.overnight ? ' · Overnight' : ''}`;
  if (row?.worked_hours !== null && row?.worked_hours !== undefined) $('modal-hours').textContent = `${row.worked_hours} hours`;
  $('modal-time').textContent = row ? `${row.time_in || '—'} / ${row.time_out || '—'}` : '—';
  $('modal-late').textContent = row ? `${row.late_minutes} minutes` : '—';
  $('modal-ot').textContent = row ? `${row.ot_hours} approved hours · ${row.pending_ot_hours} pending` : '—';
  const box = $('modal-cr-box'); box.replaceChildren(element('p', 'Read-only attendance details.', 'muted'));
  if (row?.remarks) box.append(element('p', `Remarks: ${row.remarks}`));
  if (row?.leave) box.append(element('p', `${row.leave.type} leave · ${row.leave.approval_status}`));
  if (row?.holiday) box.append(element('p', `Holiday: ${row.holiday.name}`));
  if (row?.correction) box.append(element('p', `Correction request: ${row.correction.status}${row.correction.reason ? ` · ${row.correction.reason}` : ''}`));
  const reportStatus = row?.report?.status;
  box.append(element('p', ({ submitted: 'Report submitted', missing: 'No report · Required for this shift', not_required: 'Report not required for this shift', unavailable: 'Report information unavailable' })[reportStatus] || 'Report information unavailable'));
  if (reportStatus === 'submitted' && row.report.url) {
    const link = element('a', 'Open progress report', 'btn btn-secondary');
    const target = new URL(row.report.url, window.location.origin);
    if (target.origin === window.location.origin) { link.href = target.href; link.target = '_blank'; link.rel = 'noopener'; box.append(link); }
  }
  if (row?.additional_statuses.length) box.append(element('p', `Additional statuses: ${row.additional_statuses.map(status => statuses[status]?.[1] || status).join(', ')}`));
  dayEvents.forEach(event => box.append(element('p', `◆ ${event.title}${event.extendedProps.description ? ` · ${event.extendedProps.description}` : ''}`)));
  $('day-modal').classList.add('open'); $('modal-close-btn').focus();
}

function renderEmployeeWidgets(employee) {
  const panel = document.querySelector('.widget-panel .widget-box');
  panel.replaceChildren(element('h2', 'Leave balance', 'widget-title'));
  const balances = employee?.leave_balances || [];
  balances.forEach(balance => {
    const row = element('div', undefined, 'leave-row');
    row.append(element('span', balance.type, 'leave-type'), element('span', `${balance.remaining_days} days`, 'leave-val'));
    panel.append(row);
  });
  if (!balances.length) panel.append(element('p', 'Leave balance unavailable.', 'muted'));
  panel.append(element('h2', 'Next payday', 'widget-title'), element('p', employee?.next_payday || 'Payday unavailable.', 'leave-val'));
}

function renderEvents() {
  const list = $('company-event-list'); list.replaceChildren();
  if (!events.length) list.append(element('p', 'No company events loaded for this month.', 'muted'));
  events.forEach(event => {
    const item = element('div', undefined, 'req-item');
    item.append(element('p', `◆ ${event.title} · ${event.start}`));
    if (canManage) {
      item.append(button('Edit', () => editEvent(event)), button('Delete', async () => {
        if (!window.confirm(`Delete company event “${event.title}”?`)) return;
        try { await request(`/hr/events/${event.extendedProps.eventId}`, { method: 'DELETE' }); await refresh(); }
        catch (error) { message(error.message); }
      }));
    }
    list.append(item);
  });
}

function editEvent(event) {
  const modal = element('div', undefined, 'modal-backdrop open');
  modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true'); modal.setAttribute('aria-labelledby', 'event-form-title');
  const form = element('form', undefined, 'modal-card');
  const title = element('h2', event ? 'Edit company event' : 'Create company event', 'modal-title'); title.id = 'event-form-title';
  form.append(title);
  const inputs = {};
  const inclusiveEnd = event ? new Date(`${event.end}T00:00:00Z`) : null;
  inclusiveEnd?.setUTCDate(inclusiveEnd.getUTCDate() - 1);
  const fields = [
    ['title', 'Title', 'text', event?.title || ''],
    ['type', 'Event type', 'select', event?.extendedProps.type || 'other'],
    ['starts_on', 'Start date', 'date', event?.start || `${month}-01`],
    ['ends_on', 'End date (inclusive)', 'date', inclusiveEnd?.toISOString().slice(0, 10) || `${month}-01`],
    ['description', 'Description', 'textarea', event?.extendedProps.description || '']
  ];
  fields.forEach(([name, label, type, value]) => {
    const wrapper = element('label', label, 'modal-field');
    const input = element(type === 'select' || type === 'textarea' ? type : 'input', undefined, 'hr-search-input');
    if (type === 'select') ['shoot', 'premiere', 'town_hall', 'other'].forEach(option => input.append(new Option(option.replaceAll('_', ' '), option)));
    else if (type !== 'textarea') input.type = type;
    input.name = name; input.value = value; input.required = name !== 'description';
    if (name === 'title') input.maxLength = 150;
    if (name === 'description') input.maxLength = 5000;
    inputs[name] = input; wrapper.append(input); form.append(wrapper);
  });
  const error = element('p', '', 'muted'); error.setAttribute('role', 'alert'); form.append(error);
  const restoreFocus = document.activeElement;
  function close() { modal.remove(); document.removeEventListener('keydown', onKey); restoreFocus?.focus(); }
  function onKey(event) {
    if (event.key === 'Escape') close();
    if (event.key === 'Tab') {
      const focusable = [...form.querySelectorAll('input, select, textarea, button')].filter(node => !node.disabled);
      const first = focusable[0], last = focusable.at(-1);
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  }
  const save = element('button', 'Save event', 'btn btn-primary'); save.type = 'submit';
  form.append(save, button('Cancel', close)); modal.append(form); document.body.append(modal);
  document.addEventListener('keydown', onKey); inputs.title.focus();
  form.addEventListener('submit', async submit => {
    submit.preventDefault(); save.disabled = true; error.textContent = '';
    try {
      await request(event ? `/hr/events/${event.extendedProps.eventId}` : '/hr/events', {
        method: event ? 'PUT' : 'POST', body: Object.fromEntries(Object.entries(inputs).map(([name, input]) => [name, input.value]))
      });
      close(); await refresh();
    } catch (failure) { error.textContent = failure.message; save.disabled = false; }
  });
}
