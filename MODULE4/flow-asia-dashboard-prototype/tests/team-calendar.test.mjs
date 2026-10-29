import { test } from 'node:test';
import assert from 'node:assert/strict';
import { calendarDates, buildTeamCalendar, eventOccursOnDate } from '../js/modules/m4-team-calendar.js';

function fixture() {
  return {
    today: { date: '2026-11-01' },
    employees: [
      { id: 1, name: 'Alex', department: { id: 1, name: 'Creative' }, days: [
        { date: '2026-10-12', status: 'holiday', additional_statuses: ['leave_approved', 'holiday'] },
      ] },
      { id: 2, name: 'Blair', department: { id: 1, name: 'Creative' }, days: [] },
      { id: 3, name: 'Casey', department: { id: 2, name: 'Production' }, days: [] },
    ],
    pending: { leave: [{ employee_id: 2, date: '2026-10-12' }], overtime: [{ employee_id: 1, date: '2026-10-12', hours: 2 }] },
    leave_overlaps: [{ date: '2026-10-12', department_id: 1, employee_ids: [1, 2] }],
  };
}

test('month boundaries include leap day and early years without rolling into another century', () => {
  assert.equal(calendarDates('2028-02').at(-1), '2028-02-29');
  assert.equal(calendarDates('2026-02').length, 28);
  assert.equal(calendarDates('0001-01').at(-1), '0001-01-31');
  assert.equal(calendarDates('9999-12').at(-1), '9999-12-31');
  for (const invalid of ['2026-13', '2026-00', '0000-01', 'October']) assert.throws(() => calendarDates(invalid), RangeError);
});

test('department selection retains only the permitted response roster and can be empty', () => {
  const result = buildTeamCalendar(fixture(), [], '2026-10', '1');
  assert.deepEqual(result.rows.map(row => row.employee.name), ['Alex', 'Blair']);
  assert.equal(buildTeamCalendar(fixture(), [], '2026-10', '999').rows.length, 0);
});

test('missing attendance remains unrecorded while simultaneous statuses remain visible once each', () => {
  const result = buildTeamCalendar(fixture(), [], '2026-10');
  assert.deepEqual(result.rows[0].cells[11].statuses, ['holiday', 'leave_approved']);
  assert.equal(result.rows[1].cells[11].day, null);
  assert.deepEqual(result.rows[1].cells[11].statuses, []);
});

test('company event coverage includes the stored end day and excludes the feed end day', () => {
  const events = [{ title: 'Town hall', start: '2026-10-11', end: '2026-10-13' }];
  const result = buildTeamCalendar(fixture(), events, '2026-10');
  assert.equal(result.dates[10].events.length, 1);
  assert.equal(result.dates[11].events.length, 1);
  assert.equal(result.dates[12].events.length, 0);
  assert.equal(result.rows[1].cells[11].events[0].title, 'Town hall');
});

test('timed MySQL events mark their first day and respect exclusive midnight ends', () => {
  const timed = { start: '2026-10-12T10:00:00+08:00', end: '2026-10-12T11:00:00+08:00', allDay: false };
  assert.equal(eventOccursOnDate(timed, '2026-10-12'), true);
  assert.equal(eventOccursOnDate(timed, '2026-10-13'), false);
  const overnight = { ...timed, end: '2026-10-13T00:00:00+08:00' };
  assert.equal(eventOccursOnDate(overnight, '2026-10-12'), true);
  assert.equal(eventOccursOnDate(overnight, '2026-10-13'), false);
  assert.equal(eventOccursOnDate({ ...timed, end: null }, '2026-10-12'), true);
  assert.equal(eventOccursOnDate({ ...timed, end: null }, '2026-10-13'), false);
  assert.equal(buildTeamCalendar(fixture(), [timed], '2026-10').dates[11].events.length, 1);
});

test('overlap and pending markers belong to the matching employee and date', () => {
  const result = buildTeamCalendar(fixture(), [], '2026-10');
  assert.equal(result.rows[0].cells[11].pendingOvertime, 2);
  assert.equal(result.rows[1].cells[11].pendingLeave, true);
  assert.equal(result.rows[1].cells[11].overlappingLeave, true);
  assert.equal(result.rows[2].cells[11].overlappingLeave, false);
  assert.equal(result.rows[0].cells[12].pendingOvertime, 0);
});

test('today stays independent of the selected month and rendering does not mutate source data', () => {
  const source = fixture();
  const snapshot = structuredClone(source);
  const result = buildTeamCalendar(source, [], '2026-10');
  assert.equal(result.dates.some(date => date.today), false);
  assert.deepEqual(source, snapshot);
});
