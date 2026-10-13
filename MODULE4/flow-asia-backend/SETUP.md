# Flow Asia M4 setup

The standalone system covers members #09, #10 and #11. Laravel serves the
existing sibling frontend at `/m4`. Direct `index.html` remains a mock preview.

## Current checkout: 14 October 2026

Portable PHP 8.4.26 and Composer 2.10.3 are in ignored root `.tools`.
Dependencies are installed from `composer.lock` (Laravel 13.35.0).
The local `.env` uses isolated SQLite at `.tools/m4.sqlite`, migrated and seeded.
XAMPP PHP and the shared MySQL database were not changed.

The feature/unit suite, 13 Node regressions, desktop/mobile browser flows,
employee/supervisor/HR access, dark/light themes and actual landscape PDF
generation are verified. The suite includes priority, report ownership,
cross-month cutoff OT, metadata privacy and a 240-employee query-count regression.

- **102 Laravel tests passed, 423 assertions** (isolated in-memory SQLite).
- **13 Node tests passed**.
- Laravel Pint and `git diff --check` passed.
- Real Edge browser checks passed for HR, supervisor and employee identities,
  month/week navigation, team scope, Invalid/report details, events, PDF,
  mobile widths and light/dark themes, with no JavaScript errors.
- A real exported PDF was parsed, rendered and visually reviewed: one A4
  landscape page for the tested fixture, with Invalid/secondary codes, events,
  legend, late/OT/cutoff totals and generated-by footer.

Browser checks cover team filtering, Invalid/correction details, worked hours,
submitted/missing reports, protected report links, month/week boundaries,
event date validation/create/delete and PDF downloads. Review artifacts are in
ignored `.tools/review`; they are not committed assets. Shared-system integration
and realistic shared MySQL benchmarking are still pending.

## Open and test

From this backend folder:

```powershell
.\local.ps1 serve
```

Open `http://127.0.0.1:8000/m4`. Avoid starting a second server on the same port.
There is no module login/logout. `M4_STANDALONE_PREVIEW=true` enables a seeded
identity in local/testing environments. `M4_PREVIEW_USER_EMAIL=hr@test.com` is
the default; `employee@test.com` and `supervisor@test.com` exercise other roles.

Set the identity in the local `.env`, or use `php artisan serve --no-reload`
when supplying it through an environment variable. Existing authenticated
main-system users always take precedence; production cannot enable preview.

```powershell
.\local.ps1 test
node --test ..\flow-asia-dashboard-prototype\tests\*.test.mjs
```

## Fresh checkout

Provide PHP 8.4+ compatible with the lockfile and Composer, with PDO MySQL/PDO
SQLite, mbstring, fileinfo, XML, GD, ZIP and OpenSSL. XAMPP's PHP 8.2 is insufficient.
Keep the backend beside `flow-asia-dashboard-prototype`.

1. Run `composer install`.
2. Copy `.env.example` to `.env` only if it does not already exist.
3. Configure a dedicated empty MySQL database (example: `flow_asia_m4`) and its
   credentials, or select SQLite with an existing writable database file.
4. Run the commands below. Do not migrate/seed stub tables into the group's
   existing shared database.

```powershell
php artisan key:generate
php artisan migrate --seed
php artisan test --compact
php artisan serve
```

Populated `.env`, credentials, dependencies, SQLite databases and tools stay
ignored. Commit the lockfile; do not update dependency versions just for setup.

## Demo fixtures and handoff

Five departments and 40 employees are seeded deterministically, including an
empty calendar, mid-month hire, holiday/approved-leave overlap, overlapping team
leave and pending OT. Employee 2 on 13 October has a rejected punch. Shift days
include submitted and required-without-report fixtures. Leave balances and
pay periods are explicit demo records; M4 performs no payroll or balance-ledger
writes. Calling the seeder repeatedly preserves the same records.

Replace `StubCalendarDataSource` with M2/M3/M5/M6 reads and
`StubCalendarContextSource` with M5/payroll/M12 context/report reads. Disable
preview and retain the main system's session, policies and CSRF. Context JSON
columns and `calendar_reports` are local stub schema, not the group's final ERD.

Guides: [dashboard](docs/dashboard-integration.md),
[employee feed](docs/employee-calendar.md), [team](docs/team-calendar.md),
[search](docs/employee-search.md), [events](docs/company-events.md),
[authorization](docs/calendar-authorization.md), [PDF](docs/pdf-export.md).
