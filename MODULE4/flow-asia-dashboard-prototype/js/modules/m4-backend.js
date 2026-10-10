import { request } from '../core/api.js';

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

function bounds(value) {
  const [year, number] = value.split('-').map(Number);
  const next = new Date(Date.UTC(year, number, 1)).toISOString().slice(0, 10);
  return { start: `${value}-01`, end: next, year, number };
}

function message(text) { $('backend-status').textContent = text; }

export async function initBackendDashboard() {
  const status = element('p', '', 'muted');
  status.id = 'backend-status';
  status.setAttribute('role', 'status');
  document.querySelector('main').prepend(status);
  const switcher = $('role-toggle-btn').closest('.role-switcher-card');
  switcher.replaceChildren(button('Sign out', async () => {
    try { await request('/logout', { method: 'POST' }); location.href = '/login'; }
    catch (error) { message(error.message); }
  }));
  $('user-display-name').textContent = config.user.name;
  $('user-display-role').textContent = config.user.role.replaceAll('_', ' ');
  $('user-avatar').textContent = config.user.name.split(' ').map(part => part[0]).slice(0, 2).join('');
  $('role-badge').textContent = canManage ? 'HR VIEW' : canInspect ? 'SUPERVISOR VIEW' : 'STAFF VIEW';
  $('header-dashboard-title').textContent = canInspect ? 'Calendar Management Dashboard' : 'Schedule Dashboard';
  $('header-dashboard-sub').textContent = canInspect ? 'Employee calendars, team indicators and company events' : 'Company events and schedule';
  $('hr-dashboard-view').classList.toggle('hidden', !canInspect);
  $('personal-quick-actions').hidden = true;
  document.querySelector('.mis-grid').hidden = true;
  document.querySelector('.widget-panel .widget-box').hidden = true;
  $('export-pdf-btn').textContent = 'Export PDF';
  $('export-pdf-btn').addEventListener('click', exportPdf);
  document.querySelectorAll('#staff-dashboard-view .card-kpi .kpi-subtext').forEach(node => { node.textContent = 'Recorded days in selected month'; });

  const oldMonthButton = document.querySelector('.cal-header button');
  const monthInput = element('input', undefined, 'btn btn-secondary');
  monthInput.type = 'month'; monthInput.value = month;
  monthInput.setAttribute('aria-label', 'Calendar month');
  oldMonthButton.replaceWith(monthInput);
  monthInput.addEventListener('change', () => {
    if (!/^\d{4}-\d{2}$/.test(monthInput.value)) return;
    month = monthInput.value;
    refresh();
  });

  const teamCard = element('section', undefined, 'card');
  teamCard.append(element('h2', 'Team / department', 'widget-title'));
  const department = element('select', undefined, 'btn btn-secondary');
  department.id = 'team-department';
  department.setAttribute('aria-label', 'Filter department');
  department.append(new Option('All permitted departments', ''));
  department.addEventListener('change', renderTeam);
  const indicators = element('div'); indicators.id = 'team-indicators';
  teamCard.append(department, indicators);
  $('hr-dashboard-view').insertBefore(teamCard, document.querySelector('.mis-grid'));
  document.querySelector('#hr-requests-list').previousElementSibling.textContent = 'Pending leave / OT · Read-only';
  const eventCard = element('section', undefined, 'widget-box');
  eventCard.append(element('h2', 'Company events', 'widget-title'));
  if (canManage) eventCard.append(button('Create event', () => editEvent(), 'btn btn-primary'));
  const list = element('div'); list.id = 'company-event-list'; eventCard.append(list);
  document.querySelector('.widget-panel').append(eventCard);
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
    $('team-indicators').textContent = 'Loading team…'; $('hr-requests-list').replaceChildren();
    document.querySelectorAll('#hr-dashboard-view .kpi-num').forEach(node => { node.textContent = '—'; });
  }
  $('company-event-list').textContent = 'Loading events…';
  message('Loading calendar…');
  const range = bounds(selectedMonth);
  const responses = await Promise.allSettled([
    canInspect ? request(`/hr/calendar/team?month=${selectedMonth}`) : Promise.resolve(null),
    request(`/calendar/events?start=${range.start}&end=${range.end}`),
    loadEmployeeCalendar()
  ]);
  if (version !== loadVersion) return;
  team = responses[0].status === 'fulfilled' ? responses[0].value : null;
  events = responses[1].status === 'fulfilled' ? responses[1].value : [];
  if (team) {
    const filter = $('team-department');
    const previous = filter.value;
    filter.replaceChildren(new Option('All permitted departments', ''));
    const departments = new Map(team.employees.filter(employee => employee.department).map(employee => [employee.department.id, employee.department.name]));
    departments.forEach((name, id) => filter.append(new Option(name, String(id))));
    filter.value = [...filter.options].some(option => option.value === previous) ? previous : '';
  }
  renderTeam(); renderCalendar(); renderEvents();
  const errors = responses.slice(0, 2).filter(response => response.status === 'rejected').map(response => response.reason.message);
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
    message(employeeLoadError); return;
  }
  message('Loading employee calendar…');
  try {
    const response = await request(`/calendar/employees/${employeeId}/month?month=${requestedMonth}`);
    if (version !== employeeLoadVersion) return;
    employeeCalendar = response.data; renderCalendar(); message('Employee calendar loaded. Read-only.');
  } catch (error) {
    if (version === employeeLoadVersion) { employeeLoadError = error.message; message(error.message); throw error; }
  }
}

function renderTeam() {
  if (!canInspect) return;
  if (!team) { $('team-indicators').textContent = 'Team data unavailable.'; $('hr-pending-count').textContent = '—'; return; }
  const departmentId = $('team-department').value;
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

function initSearch() {
  const input = $('hr-search-input');
  input.placeholder = 'Search name, ID or department';
  input.setAttribute('aria-label', input.placeholder);
  input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
  input.setAttribute('aria-controls', 'employee-search-results'); input.setAttribute('aria-expanded', 'false');
  const list = element('div'); list.id = 'employee-search-results'; list.setAttribute('role', 'listbox');
  input.closest('.hr-search-bar').after(list);
  let timer;
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
    ++searchVersion; searchAbort?.abort(); clearTimeout(timer);
    list.replaceChildren(); input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant');
    loadEmployeeCalendar().catch(() => {});
  }
  input.addEventListener('input', () => {
    ++searchVersion; searchAbort?.abort(); clearTimeout(timer);
    results = []; activeResult = -1; list.replaceChildren();
    input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant');
    timer = setTimeout(search, 275);
  });
  $('hr-search-btn').addEventListener('click', () => { clearTimeout(timer); search(); });
  $('clear-inspection-btn').addEventListener('click', () => {
    ++searchVersion; searchAbort?.abort(); clearTimeout(timer); results = []; activeResult = -1;
    list.replaceChildren(); input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant');
  });
  input.addEventListener('keydown', event => {
    if (event.key === 'Escape') { ++searchVersion; searchAbort?.abort(); clearTimeout(timer); list.replaceChildren(); results = []; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); }
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
      if (activeResult >= 0 && results[activeResult]) select(results[activeResult]); else { clearTimeout(timer); search(); }
    }
  });
}

const statuses = {
  present: ['P', 'Present'], late: ['L', 'Late'], absent: ['A', 'Absent'], leave_approved: ['LV', 'Leave'],
  leave_pending: ['PL', 'Pending leave'], overtime: ['OT', 'Overtime'], holiday: ['H', 'Holiday'], rest_day: ['RD', 'Rest day'], half_day: ['HD', 'Half day'], invalid: ['INV', 'Invalid punch']
};

function renderCalendar() {
  const range = bounds(month);
  const title = new Date(range.year, range.number - 1, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
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
    $(`kpi-${id}`).textContent = employee ? days.filter(day => day.status === status || day.additional_statuses.includes(status)).length : '—';
  }
  const offset = new Date(range.year, range.number - 1, 1).getDay();
  for (let i = 0; i < offset; i++) grid.append(element('div', undefined, 'cell empty'));
  const count = new Date(range.year, range.number, 0).getDate();
  for (let number = 1; number <= count; number++) {
    const date = `${month}-${String(number).padStart(2, '0')}`;
    const row = days.find(day => day.date === date);
    const status = statuses[row?.status] || ['', row?.status || (employee ? 'No record' : 'Not loaded')];
    const dayEvents = events.filter(event => event.start <= date && event.end > date);
    const cell = button('', () => showDay(date, row, dayEvents), `cell${status[0] ? ` s-${status[0]}` : ''}`);
    cell.setAttribute('role', 'gridcell'); cell.setAttribute('aria-label', `${date}: ${status[1]}${dayEvents.length ? `, ${dayEvents.length} company events` : ''}`);
    cell.append(element('span', String(number), 'cell-num'), element('span', status[1].toUpperCase(), 'cell-tag'));
    dayEvents.forEach(event => cell.append(element('span', `◆ ${event.title}`, 'company-event-marker')));
    grid.append(cell);
  }
}

function showDay(date, row, dayEvents) {
  $('modal-date-title').textContent = date;
  $('modal-status').textContent = statuses[row?.status]?.[1] || row?.status || 'No attendance record';
  for (const id of ['shift', 'time', 'hours']) $(`modal-${id}`).textContent = 'Not provided';
  $('modal-late').textContent = row ? `${row.late_minutes} minutes` : '—';
  $('modal-ot').textContent = row ? `${row.ot_hours} approved hours · ${row.pending_ot_hours} pending` : '—';
  const box = $('modal-cr-box'); box.replaceChildren(element('p', 'Read-only attendance details.', 'muted'));
  if (row?.additional_statuses.length) box.append(element('p', `Additional statuses: ${row.additional_statuses.map(status => statuses[status]?.[1] || status).join(', ')}`));
  dayEvents.forEach(event => box.append(element('p', `◆ ${event.title}${event.extendedProps.description ? ` · ${event.extendedProps.description}` : ''}`)));
  $('day-modal').classList.add('open'); $('modal-close-btn').focus();
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
