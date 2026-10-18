// Connected dashboard components reuse the calendar and its existing controls.
const $ = id => document.getElementById(id);
function node(tag, text, className, id) {
  const result = document.createElement(tag);
  if (text !== undefined) result.textContent = text;
  if (className) result.className = className;
  if (id) result.id = id;
  return result;
}

function hostUrl(value) {
  if (!value) return null;
  try {
    const url = new URL(value, location.href);
    return url.origin === location.origin && ['http:', 'https:'].includes(url.protocol) ? url.href : null;
  } catch { return null; }
}

export function mountDashboardShell(config, canInspect) {
  const brand = document.querySelector('.brand');
  const logo = node('span', undefined, 'flow-asia-logo');
  logo.setAttribute('role', 'img'); logo.setAttribute('aria-label', 'Flow Asia');
  brand.replaceChildren(logo);
  const nav = document.querySelector('.nav-group'); nav.replaceChildren();
  const items = [
    ['dashboard', 'My Dashboard'], ['punch', 'Time In / Out'], ['attendance', 'My Attendance'],
    ['leave', 'My Leave'], ['overtime', 'Overtime and Schedule'], ['payslips', 'Payslips'],
    ['announcements', 'Announcements'], ['notifications', 'Notifications'], ['profile', 'My Profile']
  ];
  if (canInspect) items.push(['team', 'Team Calendar']);
  for (const [key, label] of items) {
    const url = hostUrl(config.navigation?.[key]);
    const control = node(url ? 'a' : 'button', label, `nv${key === 'dashboard' ? ' on' : ''}`);
    control.dataset.page = key;
    if (url) control.href = url;
    else {
      control.type = 'button';
      if (key === 'dashboard' || key === 'attendance' || key === 'team') {
        control.addEventListener('click', () => {
          nav.querySelectorAll('.nv').forEach(item => item.classList.toggle('on', item === control));
          const target = key === 'team' ? $('team-calendar-card') : key === 'attendance' ? $('staff-calendar-grid') : document.querySelector('.bar');
          target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      } else {
        control.disabled = true;
        control.title = `${label} is unavailable until its module is connected.`;
      }
    }
    if (key === 'dashboard') control.setAttribute('aria-current', 'page');
    nav.append(control);
  }
  const footer = document.querySelector('.aside-footer');
  const signout = node('button', 'Sign out', 'btn btn-secondary signout-btn', 'signout-btn');
  signout.type = 'button';
  const logoutUrl = hostUrl(config.logoutUrl);
  if (logoutUrl) {
    const form = node('form'); form.method = 'post'; form.action = logoutUrl;
    const csrf = node('input'); csrf.type = 'hidden'; csrf.name = '_token';
    csrf.value = document.querySelector('meta[name="csrf-token"]').content;
    signout.type = 'submit'; form.append(csrf, signout); footer.append(form);
  } else {
    signout.disabled = true; signout.title = 'Sign out is unavailable in this module preview.';
    footer.append(signout);
  }
  const requestLeave = node('button', 'Request leave', 'btn btn-primary', 'request-leave-btn');
  requestLeave.type = 'button';
  const leaveUrl = hostUrl(config.navigation?.leave);
  if (leaveUrl) requestLeave.addEventListener('click', () => { location.href = leaveUrl; });
  else { requestLeave.disabled = true; requestLeave.title = 'Leave requests are unavailable until the leave module is connected.'; }
  document.querySelector('.bar > .header-actions').append(requestLeave);
  $('role-badge').hidden = true;

  const calendar = document.querySelector('.cal-container');
  calendar.prepend(node('h2', 'My calendar', 'widget-title calendar-heading'));
  const detail = node('section', undefined, 'selected-day-details', 'selected-day-details');
  detail.setAttribute('aria-live', 'polite');
  detail.append(node('h3', 'Loading day details…', undefined, 'selected-day-title'), node('p', '', undefined, 'selected-day-description'));
  calendar.append(detail);

  const summary = node('section', undefined, 'monthly-summary');
  summary.setAttribute('aria-labelledby', 'summary-title');
  summary.append(node('h2', 'Monthly summary', 'widget-title', 'summary-title'));
  const cards = document.querySelector('#staff-dashboard-view > .g4');
  cards.classList.add('summary-cards');
  ['Present', 'Late', 'Absent', 'Leave days'].forEach((label, index) => { cards.children[index].querySelector('.kpi-label').textContent = label; });
  const overtime = node('article', undefined, 'card card-kpi');
  overtime.append(node('span', '—', 'kpi-num', 'kpi-overtime'), node('span', 'Overtime', 'kpi-label'));
  cards.append(overtime); summary.append(cards);
  const metrics = node('div', undefined, 'summary-metrics', 'summary-metrics'); summary.append(metrics);
  document.querySelector('.cal-layout').after(summary);

  const tools = node('div', undefined, 'calendar-tools', 'calendar-tools');
  tools.append($('export-pdf-btn'));
  summary.after(tools);
  const supplementary = node('div', undefined, 'supplementary-cards', 'supplementary-cards'); tools.after(supplementary);
  const attendance = node('section', undefined, 'widget-box attendance-today', 'attendance-today');
  attendance.setAttribute('aria-labelledby', 'attendance-today-label');
  attendance.append(node('p', 'Attendance today', 'muted', 'attendance-today-label'),
    node('h2', 'Loading…', undefined, 'attendance-today-status'),
    node('p', '', 'muted', 'attendance-today-detail'));
  const punch = node('button', 'Time In', 'btn btn-primary', 'time-in-btn'); punch.type = 'button';
  const punchUrl = hostUrl(config.navigation?.punch);
  if (punchUrl) punch.addEventListener('click', () => { location.href = punchUrl; });
  else { punch.disabled = true; punch.title = 'Time capture is unavailable until the attendance module is connected.'; }
  attendance.append(punch);
  if (!punchUrl) attendance.append(node('p', 'Time capture is unavailable here.', 'module-unavailable'));
  document.querySelector('.widget-panel').prepend(attendance);
  document.querySelectorAll('#staff-dashboard-view .kpi-subtext').forEach(item => { item.hidden = true; });
  return { leaveUrl, punchUrl };
}

export function renderSelectedDay(date, row, status, employee, error, dayEvents) {
  const label = new Date(`${date}T00:00:00Z`).toLocaleDateString('en-US', { month: 'long', day: 'numeric', timeZone: 'UTC' });
  $('selected-day-title').textContent = `${label} · ${status}`;
  const lines = [];
  if (error || !employee) lines.push(error || 'Loading employee calendar…');
  else if (!row) lines.push('No attendance record is available for this date.');
  else {
    if (row.remarks) lines.push(row.remarks);
    if (row.time_in || row.time_out) lines.push(`Time in: ${row.time_in || '—'} · Time out: ${row.time_out || '—'}`);
    if (row.leave) lines.push(`${row.leave.type} leave · ${row.leave.approval_status}`);
    if (row.holiday) lines.push(row.holiday.name);
    if (!lines.length) lines.push('Select this date to see its recorded attendance details.');
  }
  dayEvents.forEach(event => lines.push(`Company event: ${event.title}`));
  $('selected-day-description').textContent = lines.join(' ');
  document.querySelectorAll('#staff-calendar-grid button').forEach(cell => {
    const selected = cell.dataset.date === date;
    cell.classList.toggle('selected', selected); cell.setAttribute('aria-selected', String(selected));
  });
}

export function renderToday(config, data, error, loading) {
  const row = data?.days.find(day => day.date === config.today);
  const rejected = row?.status === 'invalid' || row?.additional_statuses.includes('invalid');
  $('attendance-today-status').textContent = loading ? 'Loading…' : error ? 'Attendance unavailable'
    : rejected ? 'Punch not accepted' : row?.time_out ? 'Timed out' : row?.time_in ? 'Timed in' : 'No time in recorded';
  $('attendance-today-detail').textContent = error || (row ? `Time in: ${row.time_in || '—'} · Time out: ${row.time_out || '—'}` : loading ? '' : 'No accepted punch is shown in the calendar.');
  $('attendance-today-detail').hidden = !error && !(row?.time_in || row?.time_out);
  $('time-in-btn').textContent = row?.time_in && !row.time_out && !rejected ? 'Time Out' : 'Time In';
  $('attendance-today').setAttribute('aria-busy', String(loading));
}
