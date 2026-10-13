# Individual employee calendar

`GET /calendar/employees/{employee}/month?month=2026-10` requires session
authentication and the `viewCalendar` policy before reading attendance.
Employees can read themselves; supervisors themselves and direct reports; HR
roles any employee. Invalid months return 422, unauthorized access 403, missing
employees 404, and unauthenticated JSON requests 401. Calendar writes are denied.

The response contains `month`, `read_only: true` and `data` with employee identity,
department and recorded `days`. Days preserve primary/additional statuses, late
minutes, approved OT, pending OT, punch times and attendance remarks. Missing days are not inferred as Present.
Reads use `CalendarDataSource::forEmployee` with inclusive monthly boundaries.
Company events continue using the separate shared feed.

The connected `/m4` dashboard now uses this endpoint for the signed-in employee,
search selection, clearing selection and month changes. Responses from earlier
selections are ignored. Team indicators retain the batched team endpoint. No
layout or color changes are included.

PHP/JS checks and Laravel tests passed on 12 October 2026. Run `php artisan test` after setup,
including `EmployeeCalendarTest`, and check employee/supervisor/HR sessions at
`/m4`. PDF export is verified; shared #09 aggregation integration remains pending.

The connected HR day popup now displays stored time-in/time-out and remarks. Shift, worked-hours and report fields are not invented; those still need the shared feed. The latest targeted run passed 61 relevant Laravel tests and seven request tests, and Chrome verified the no-login controls, event validation/CRUD, PDF download and popup data.
