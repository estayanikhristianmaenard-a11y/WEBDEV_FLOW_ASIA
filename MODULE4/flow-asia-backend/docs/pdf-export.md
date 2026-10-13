# Calendar PDF export

`GET /calendar/employees/{employee}/pdf?month=2026-10` returns an A4 landscape
download. Session authentication and `viewCalendar` run before attendance reads
or PDF rendering. Employees can export themselves, supervisors themselves/direct
reports, and HR permitted employees. Responses are private and not cached.

The connected `/m4` Export PDF button exports the current selection (otherwise
the signed-in employee) and selected month. It shows a preparing state and
surfaces server errors. Standalone HTML retains its browser-print preview.
The export includes identity, department, month grid, company events on inclusive
stored dates, status code/color legend, status-membership counts, missing-record
count and generated-by/time footer in Asia/Manila. Secondary statuses are retained.
Missing records are not inferred. Final shared status aggregation belongs to #09.

The table-based Blade template uses local DejaVu Sans, no remote assets, and no
embedded PHP execution. User text is escaped by Blade. Labels come from shared backend calendar configuration; PDF colors match the restored original dark-theme legend. Light-theme variants remain for readability. Many long company events can expand the PDF
to additional pages; one page is not guaranteed.

`barryvdh/laravel-dompdf` ^3.1.2 is declared in composer.json. Its
[official release notes](https://github.com/barryvdh/laravel-dompdf/releases)
confirm Laravel 13 support. Once PHP 8.3+ and Composer are available, run
`composer install`, then the setup/migrations and `php artisan test` including
`CalendarPdfTest`. Composer dependencies are installed and locked in this environment.

All 89 Laravel tests and seven Node transport tests passed. Chrome verified PDF download and role restrictions. The generated one-page landscape PDF was rendered and visually reviewed on 12 October 2026. Shared-system integration remains pending.

Screen/PDF summary rule: count each primary or additional status once per day. Overlapping categories may exceed recorded-day totals. No status priority is computed by the presenter.
