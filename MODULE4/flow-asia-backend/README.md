# Flow Asia M4 backend

Laravel Calendar & Schedule Dashboard for members #09, #10 and #11, with standalone
development and shared-application assembly modes.
Start with [SETUP.md](SETUP.md) and the [project guide](../README.md).

The backend serves the sibling frontend at `/m4`. Features include combined
employee calendars, summaries/widgets, HR search, team calendars, company
events, protected report viewing and landscape PDF export.

Policies enforce access; HR event writes require CSRF. Attendance, leave,
overtime and reports are read-only. Local preview uses seeded identities;
shared mode accepts the host's authenticated user and replaceable employee,
attendance, context and event adapters. Run `php artisan m4:check` to validate
wiring. Follow the [assembly contract](docs/dashboard-integration.md#assemble-m4-inside-the-shared-laravel-application)
when joining the group application. Actual upstream data acceptance remains pending.
Shared mode excludes all standalone migrations and demo identities.

Guides:

- [MySQL integration summary and handoff](docs/mysql-integration-summary.md)
- [Dashboard](docs/dashboard-integration.md)
- [Combined employee calendar](docs/employee-calendar.md)
- [Search](docs/employee-search.md)
- [Team calendar](docs/team-calendar.md)
- [Company events](docs/company-events.md)
- [Authorization](docs/calendar-authorization.md)
- [PDF export](docs/pdf-export.md)

Run `./local.ps1 test` or `php artisan test --compact` after installing locked
dependencies. Frontend tests:
`node --test ../flow-asia-dashboard-prototype/tests/*.test.mjs`.
