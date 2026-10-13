# Team and department calendar

`GET /hr/calendar/team?month=2026-10&department_id=1` requires authentication,
`Accept: application/json` and a valid `YYYY-MM` month. Department is optional.

HR reads all employees; supervisors only self/direct reports. A filter for another
department returns an empty permitted roster. Employees/unknown roles are denied.
Each selected employee passes `viewCalendar` before any calendar source read.
Responses are private/no-store.

The response includes permitted `employees` with days, independent Manila `today`
absent/late/on-leave lists, `leave_overlaps` within the same department, and
`pending.leave`/`pending.overtime` employee/date indicators.

Attendance and context are read in batches spanning the month and today.
The 240-employee regression enforces one attendance query, one report query and
bounded total queries. No per-employee attendance/report requests are made.
`CalendarDayPresenter` applies the same priority, Invalid and detail semantics as
the individual feed/PDF. Missing records remain unrecorded.

The connected matrix shows employee rows against month dates with text codes,
secondary statuses, approved overlap markers, pending OT and company events.
Department filtering uses the permitted response. Row/day selection loads the
authorized individual feed and details. Month controls synchronize; the matrix
scrolls inside its container on small screens. Employee accounts have no team UI.

`pending_ot_hours` and JSON context are local source metadata. Indicators are
daily records rather than distinct request IDs. M4 cannot create/approve requests.
Replace the adapters with shared leave/OT records during integration.

Run `php artisan test --filter=TeamCalendar` and
`php artisan test --filter=CalendarAggregation` after setup. Frontend regressions:
`node --test ../flow-asia-dashboard-prototype/tests/*.test.mjs`.
See [dashboard](dashboard-integration.md) and [SETUP.md](../SETUP.md).
