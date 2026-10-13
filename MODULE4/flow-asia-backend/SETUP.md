# Flow Asia M4 backend ? member #11

The standalone backend is installed and verified locally. The dashboard at `/m4`
uses employee search, individual calendars, team indicators, company event CRUD
and authorized PDF export. Standalone `index.html` remains a mock preview.

## Verified on 12 October 2026

- Portable PHP 8.4.26 and Composer 2.10.3 installed in ignored root `.tools`; XAMPP PHP was not replaced.
- Laravel 13.35.0 and DomPDF installed; `composer.lock` records resolved versions.
- Dedicated MySQL/MariaDB `flow_asia_m4` database migrated and seeded.
- **89 Laravel tests passed, 330 assertions**, using isolated in-memory SQLite.
- **7 Node transport tests passed**.
- Chrome passed search/selection, month changes, department filters, event create/edit/delete and multi-day markers, PDF download, CSRF rejection, supervisor scope, staff calendar and day details; no JavaScript errors. Login/logout was subsequently removed; direct preview opening was rechecked.
- A real PDF was downloaded and visually reviewed: one landscape page for the tested fixture, containing employee identity, events, legend, summary and generated-by footer.

Runtime checks fixed date-only storage (last-day inclusion and repeatable seeding),
full-name search matching and read-only writes before route matching.

## Open the local dashboard

Start MySQL in XAMPP. From this backend folder:

```powershell
.\local.ps1 serve
```

Open `http://127.0.0.1:8000/m4`. The server may already be running from verification;
do not start a second process on port 8000. M4 opens directly with the seeded HR preview user when `M4_STANDALONE_PREVIEW=true`
and `APP_ENV=local`. There is no module login/logout. `M4_PREVIEW_USER_EMAIL` selects
a seeded identity for local testing. The main system owns authentication after integration.

```powershell
.\local.ps1 test
node --test ..\flow-asia-dashboard-prototype\tests\api.test.mjs
```

The review PDF/screenshots are in ignored `.tools`, not committed project assets.

## Setup on another machine

Install PHP/Composer compatible with `composer.lock` (validated runtime: PHP 8.4),
with PDO MySQL, PDO SQLite, mbstring, fileinfo, XML, GD, ZIP and OpenSSL. Keep the
backend beside `flow-asia-dashboard-prototype`, whose CSS/JS Laravel serves.

```powershell
composer install
```

Copy `.env.example` to `.env` only if it does not already exist. Configure a
**dedicated empty** `flow_asia_m4` database and its credentials. Do not migrate or
seed these stub tables into the group's existing shared database.

```powershell
php artisan key:generate
php artisan migrate --seed
php artisan test --compact
php artisan serve
```

Local `.env`, credentials, dependencies and portable tools are ignored. Commit
`composer.lock` for reproducible dependency versions.

## Group integration still required

Member #11's standalone features are verified. This repository contains no
shared backend implementation from #09/#10, so group integration remains pending.
Disable `M4_STANDALONE_PREVIEW` on integration and use the main system's session
while retaining M4 authentication and policies. Local preview does not activate
in production or override an existing authenticated user. Replace `StubCalendarDataSource` through
`CalendarDataSource` and align employee, department, attendance, leave/OT requests
and company-event fields with the agreed ERD. Pending OT is stub metadata.
Shared priority, CR1/CR3 metadata, FullCalendar components and screen/PDF palette
agreement require coordination. No payroll or request-approval workflow was added.

Guides: [dashboard](docs/dashboard-integration.md),
[individual calendar](docs/employee-calendar.md), [team](docs/team-calendar.md),
[events](docs/company-events.md), [permissions](docs/calendar-authorization.md),
[PDF](docs/pdf-export.md).
