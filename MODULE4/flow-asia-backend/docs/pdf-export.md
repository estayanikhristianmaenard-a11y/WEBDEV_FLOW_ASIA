# Calendar PDF export

`GET /calendar/employees/{employee}/pdf?month=2026-10` returns an A4 landscape
download. Session authentication and `viewCalendar` run before attendance reads
or PDF rendering. Employees can export themselves, supervisors themselves/direct
reports, and HR permitted employees. Responses are private and not cached.

The connected `/m4` Export PDF button exports the current selection (otherwise
the signed-in employee) and selected month. It shows a preparing state and
surfaces server errors. Standalone HTML retains its browser-print preview.
The export includes identity, department, month grid, company events on inclusive
stored dates, status code/color legend, primary-status counts, missing-record
count and generated-by/time footer in Asia/Manila. Secondary statuses are retained.
Missing records are not inferred. Final shared status aggregation belongs to #09.

The table-based Blade template uses local DejaVu Sans, no remote assets, and no
embedded PHP execution. User text is escaped by Blade. Status colors come from
backend calendar configuration; alignment with the restored frontend palette
remains a shared integration task. Many long company events can expand the PDF
to additional pages; one page is not guaranteed.

`barryvdh/laravel-dompdf` ^3.1.2 is declared in composer.json. Its
[official release notes](https://github.com/barryvdh/laravel-dompdf/releases)
confirm Laravel 13 support. Once PHP 8.3+ and Composer are available, run
`composer install`, then the setup/migrations and `php artisan test` including
`CalendarPdfTest`. No Composer dependencies were installed in this environment.

PHP/JS syntax checks and seven Node transport tests are available. Laravel tests,
actual PDF generation and visual PDF/browser review remain pending runtime
setup. Verify HR/supervisor/employee access and multi-day events before merging.
