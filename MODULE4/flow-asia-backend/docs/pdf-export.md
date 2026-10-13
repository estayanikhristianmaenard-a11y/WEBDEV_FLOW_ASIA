# Calendar PDF export

`GET /calendar/employees/{employee}/pdf?month=2026-10` downloads an A4 landscape
calendar. Session authentication and `viewCalendar` precede reads/rendering.
Employees export self; supervisors self/direct reports; HR permitted employees.
Responses are private/no-store.

The connected Export PDF button uses the selected employee (otherwise self) and
month, even in Week view. It shows preparation/errors. Direct HTML preview retains
browser printing.

`CalendarPdfData` consumes `CalendarMonthService`, so priority, rejected Invalid
punches, simultaneous statuses and summary counts match the screen/team feed.
The export includes identity, department, month, company events, shared codes/
colors, recorded/missing-day counts, total late minutes, approved OT/cutoff OT,
legend and generated-by/time footer in Asia/Manila. Missing days are not inferred.

The Blade layout uses HTML tables and local DejaVu Sans, escaped text, no remote
assets and no embedded PHP execution. Long event content may create extra pages.
The original dark palette is preserved; screen light variants stay readable.

Run `php artisan test --filter=CalendarPdf` and
`php artisan test --filter=CalendarAggregation`. Actual downloads and landscape
PDF rendering have been checked locally; see [SETUP.md](../SETUP.md).
Shared-source mapping and final group verification remain pending.
