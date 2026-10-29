export function calendarDates(month) {
  if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(month) || month.startsWith('0000')) {
    throw new RangeError('Choose a valid calendar month.');
  }
  const end = new Date(`${month}-01T00:00:00Z`);
  end.setUTCMonth(end.getUTCMonth() + 1, 0);
  return Array.from({ length: end.getUTCDate() }, (_, index) => `${month}-${String(index + 1).padStart(2, '0')}`);
}

export function eventOccursOnDate(event, date) {
  const start = event.start.slice(0, 10);
  if (!event.end) return start === date;
  const end = event.end.slice(0, 10);
  const endsDuringDay = event.end.includes('T') && !/T00:00(?::00(?:\.0+)?)?(?:Z|[+-]\d{2}:?\d{2})?$/.test(event.end);
  return start <= date && (end > date || (end === date && endsDuringDay));
}

export function buildTeamCalendar(team, events, month, departmentId = '') {
  const dates = calendarDates(month);
  const employees = team.employees.filter(employee => !departmentId || String(employee.department?.id) === String(departmentId));
  const eventsByDate = new Map(dates.map(date => [date, events.filter(event => eventOccursOnDate(event, date))]));
  const pendingLeave = new Set(team.pending.leave.map(item => `${item.employee_id}:${item.date}`));
  const pendingOvertime = new Map(team.pending.overtime.map(item => [`${item.employee_id}:${item.date}`, item.hours]));
  const overlaps = new Set(team.leave_overlaps.flatMap(item => item.employee_ids.map(id => `${id}:${item.date}`)));
  return {
    dates: dates.map(date => ({ date, events: eventsByDate.get(date), today: date === team.today.date })),
    rows: employees.map(employee => {
      const days = new Map(employee.days.map(day => [day.date, day]));
      return {
        employee,
        cells: dates.map(date => {
          const day = days.get(date) ?? null;
          const key = `${employee.id}:${date}`;
          return {
            date, day,
            statuses: day ? [...new Set([day.status, ...(day.additional_statuses ?? [])])] : [],
            events: eventsByDate.get(date),
            pendingLeave: pendingLeave.has(key),
            pendingOvertime: pendingOvertime.get(key) ?? 0,
            overlappingLeave: overlaps.has(key),
          };
        }),
      };
    }),
  };
}

function element(tag, text, className) {
  const node = document.createElement(tag);
  if (text !== undefined) node.textContent = text;
  if (className) node.className = className;
  return node;
}

function statusTag(status, statuses) {
  const definition = statuses[status];
  const tag = element('span', definition?.code || status, `team-status team-status-${definition?.ui_class || 'unknown'}`);
  tag.title = definition?.label || status;
  return tag;
}

export function renderTeamCalendar(container, { team, events, month, departmentId, statuses, onInspect }) {
  const view = buildTeamCalendar(team, events, month, departmentId);
  container.replaceChildren();
  if (!view.rows.length) {
    container.append(element('p', 'No permitted employees in this department.', 'muted'));
    return;
  }
  const table = element('table', undefined, 'team-calendar-table');
  const monthName = new Date(`${month}-01T00:00:00Z`).toLocaleDateString('en-US', { month: 'long', year: 'numeric', timeZone: 'UTC' });
  table.append(element('caption', `${monthName} · ${view.rows.length} permitted employees · Read-only`));
  const head = element('thead');
  const headings = element('tr');
  const identityHeading = element('th', 'Employee', 'team-employee-heading');
  identityHeading.scope = 'col'; headings.append(identityHeading);
  for (const column of view.dates) {
    const heading = element('th', undefined, column.today ? 'team-today' : '');
    heading.scope = 'col';
    if (column.today) heading.setAttribute('aria-current', 'date');
    heading.append(element('span', column.date.slice(-2)));
    heading.append(element('span', new Date(`${column.date}T00:00:00Z`).toLocaleDateString('en-US', { weekday: 'short', timeZone: 'UTC' }), 'team-weekday'));
    if (column.events.length) {
      const marker = element('span', `◆ ${column.events.length}`, 'company-event-marker');
      marker.title = column.events.map(event => event.title).join('\n');
      marker.setAttribute('aria-label', `${column.events.length} company events: ${column.events.map(event => event.title).join(', ')}`);
      heading.append(marker);
    }
    headings.append(heading);
  }
  head.append(headings); table.append(head);
  const body = element('tbody');
  for (const entry of view.rows) {
    const row = element('tr');
    const identity = element('th', undefined, 'team-employee-heading');
    identity.scope = 'row';
    const select = element('button', entry.employee.name, 'team-employee-link');
    select.type = 'button'; select.addEventListener('click', () => onInspect(entry.employee));
    select.setAttribute('aria-label', `Inspect ${entry.employee.name}, ${entry.employee.employee_no}`);
    identity.append(select, element('span', `${entry.employee.employee_no} · ${entry.employee.department?.name || 'No department'}`, 'team-employee-meta'));
    row.append(identity);
    for (const cell of entry.cells) {
      const holder = element('td', undefined, cell.overlappingLeave ? 'team-leave-overlap' : '');
      const control = element('button', undefined, 'team-calendar-day');
      control.type = 'button';
      control.dataset.employeeId = entry.employee.id; control.dataset.date = cell.date;
      control.addEventListener('click', () => onInspect(entry.employee, cell.date));
      const labels = cell.statuses.map(status => statuses[status]?.label || status);
      if (!labels.length) { labels.push('No record'); control.append(element('span', '—', 'muted')); }
      cell.statuses.forEach(status => control.append(statusTag(status, statuses)));
      if (cell.pendingLeave && !cell.statuses.includes('leave_pending')) {
        labels.push('Pending leave');
        control.append(statusTag('leave_pending', statuses));
      }
      if (cell.pendingOvertime > 0) {
        labels.push(`${cell.pendingOvertime} hours pending overtime`);
        control.append(element('span', `OT? ${cell.pendingOvertime}h`, 'team-pending-ot'));
      }
      if (cell.overlappingLeave) {
        labels.push('Overlapping approved leave in this department');
        control.append(element('span', 'Overlap', 'team-overlap-marker'));
      }
      if (cell.events.length) labels.push(`Company events: ${cell.events.map(event => event.title).join(', ')}`);
      const description = `${entry.employee.name}, ${cell.date}: ${labels.join('; ')}`;
      control.setAttribute('aria-label', description); control.title = description;
      holder.append(control); row.append(holder);
    }
    body.append(row);
  }
  table.append(body); container.append(table);
}

export function renderTeamLegend(container, statuses) {
  container.replaceChildren();
  for (const [status, definition] of Object.entries(statuses)) {
    const item = element('span', undefined, 'lg-item');
    item.append(statusTag(status, statuses), element('span', definition.label));
    container.append(item);
  }
  container.append(element('span', '— No record', 'lg-item'), element('span', 'OT? Pending overtime', 'lg-item'),
    element('span', 'Overlap: simultaneous approved leave in one department', 'lg-item'), element('span', '◆ Company event', 'lg-item'));
}
