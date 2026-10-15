# Flow Asia M4 setup

The standalone system covers members #09, #10 and #11. Laravel serves the
existing sibling frontend at `/m4`. Direct `index.html` remains a mock preview.

M4 now also has a shared-application assembly mode. Follow the
[module assembly contract](docs/dashboard-integration.md#assemble-m4-inside-the-shared-laravel-application)
to register `FlowAsia\Module4\Providers\Module4ServiceProvider` in the host and
configure the source adapters. Shared mode mounts at `/modules/m4`, accepts the
host's user model/session, excludes demo migrations and disables demo identity.
Run `php artisan m4:check` before the group integration test.

## Current checkout: 15 October 2026

Portable PHP 8.4.26 and Composer 2.10.3 are in ignored root `.tools`.
Dependencies are installed from `composer.lock` (Laravel 13.35.0).
The local `.env` uses isolated SQLite at `.tools/m4.sqlite`, migrated and seeded.
XAMPP PHP and the shared MySQL database were not changed.

The feature/unit suite, 16 Node regressions, desktop/mobile browser flows,
employee/supervisor/HR access, dark/light themes and actual landscape PDF
generation are verified. The suite includes priority, report ownership,
cross-month cutoff OT, metadata privacy and a 240-employee query-count regression.

- **119 Laravel tests passed, 547 assertions** (isolated in-memory SQLite).
- **21 Node tests passed**.
- Laravel Pint and whitespace checks passed (preserving existing Windows line endings).
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

The 15 October integration checks additionally cover persistent event create/edit/
delete with reloads, duplicate submissions, independent event reads, failure/retry
states, HTML session expiry, missing records, real CSRF enforcement and a browser
PDF download. Employee/supervisor scope and mobile layouts were rechecked in
Edge. Subdirectory URL configuration is covered by Laravel and Node regressions;
an actual Apache subdirectory deployment was not tested. The new optional browser
regression and specific shared-schema blockers are described in the
[dashboard guide](docs/dashboard-integration.md).

The module assembly regressions additionally verify a different authenticated user
model and field/role mappings, adapter-supplied calendar/team/report/PDF/event
records with no demo tables or business SQL, protected peer-module routes and
Gates, rejected demo seeding/fallback, independent calendar timezone, diagnostic
failures and middleware registration before or after HTTP-kernel creation.
These are contract tests with substitute adapters, not live peer-module acceptance.

Synchronization readiness adds a tested `flowasia:calendar-updated` refresh hook
and return-to-tab refresh, coalescing signals and waiting for open forms, dialogs,
downloads and in-flight reads. Shared-mode tests change upstream attendance,
approved leave/balances, OT/cutoff, payday and report references and verify fresh
employee/team/PDF results without demo tables or business SQL. The browser checks
the update signal with a simulated response; live peer modules remain untested.
The existing sidebar, styles, `.env`, dependencies and database schema are unchanged.
Source checkpoint: `.tools/checkpoints/20261015-231240-before-sync-3b526d67/workspace-source.zip`
(164 verified entries, excluding populated environment files and databases).

The reference dashboard update was compared at 1920×965 and checked at
1024/768/390px with the existing database fixtures. It preserves the reference's
layout, branding and status styling; names, dates, balances and totals come from
the actual feed, so they differ from the screenshot's sample records. Additional
calendar controls and unavailable-action notices remain visible. This is not a
claim of pixel-identical output or completion of the other modules.

Before that update, 115 Laravel tests/510 assertions, 16 Node tests and the HR
browser regression passed. The source checkpoint is
`.tools/checkpoints/20261015-213635-b31232a1/workspace-source.zip` at the project
root, with 161 verified entries, Git status/HEAD and staged/unstaged patches.
It is ignored by Git, excludes populated environment files and databases, and
preserves existing uncommitted source work. No environment files, dependencies,
database schema or existing business records were replaced by the UI update.

The PHP namespace changed to `FlowAsia\Module4` to avoid colliding with the host's
`App` classes. After updating an existing checkout, run `composer dump-autoload`
before starting it. Fresh `composer install` performs this automatically. Existing
local migration names are unchanged; moving them under `database/migrations/standalone`
does not require resetting or reseeding an existing demo database.

## Open and test

From this backend folder:

```powershell
.\local.ps1 serve
```

If PowerShell blocks `.ps1` scripts, run PHP directly without changing execution
policy. From this same backend directory:

```powershell
& ..\..\.tools\php\php.exe artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

To inspect the employee reference view without editing `.env`, set
`$env:M4_PREVIEW_USER_EMAIL='employee@test.com'` in that terminal before starting
the server. This uses the already-enabled local standalone preview only.

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
