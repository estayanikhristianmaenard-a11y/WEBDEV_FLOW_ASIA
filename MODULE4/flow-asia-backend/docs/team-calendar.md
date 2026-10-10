# Team and department calendar backend

Prepared endpoint: `GET /hr/calendar/team?month=2026-10&department_id=1`.
Use an authenticated session and `Accept: application/json`. `month` is required
(`YYYY-MM`); `department_id` is optional and must exist.

HR roles can read all employees or filter by department. Supervisors can read
themselves and direct reports only. Filtering by another department returns an
empty authorized roster. Employees and unknown roles cannot use this endpoint.
Every selected employee also passes `viewCalendar` authorization before reading.

The response includes:

- `month`, `read_only`, and `employees`: safe identities and recorded days in the selected month.
- `today`: Manila date and absent, late, and approved-leave employee lists, independent of selected month.
- `leave_overlaps`: dates with two or more approved leaves within the same department and authorized roster.
- `pending.leave`: employee/date indicators for pending leave within the selected month.
- `pending.overtime`: employee/date/hours indicators for pending OT within the selected month.

Attendance is loaded through one `CalendarDataSource::forEmployees` call, using
an inclusive range spanning the selected month and today. No per-employee
attendance queries are made. Empty days stay empty; missing punches are not
inferred as Present. Primary and additional statuses are preserved. Today's
groups show membership in the recorded statuses, so an employee may appear in
multiple groups. Display priority and FullCalendar event conversion remain #09's
responsibility. Company events use the existing separate `/calendar/events` feed.

`pending_ot_hours` is local stub metadata, added through migration 000003.
It represents requested hours, separate from approved `ot_hours`. This endpoint
does not create or approve requests. Replace this storage with your team's leave
and overtime request sources when integrating the shared ERD. Indicators are
daily records, not counts of distinct request IDs or request date ranges.

Folders: request validation in `app/Http/Requests`, route handler in
`app/Http/Controllers`, aggregation in `app/Services`, storage changes in
`database/migrations`, verification in `tests/Feature/TeamCalendarTest.php`.

After PHP 8.3+ and Composer dependencies are available, migrate the isolated demo
database and run `php artisan test --filter=TeamCalendar`. Tests are prepared but
have not run in the current environment. The `/m4` dashboard connection now uses
this response; see [dashboard integration](dashboard-integration.md).
