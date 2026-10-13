# Flow Asia M4 backend

Standalone Laravel Calendar & Schedule Dashboard for members #09, #10 and #11.
Start with [SETUP.md](SETUP.md) and the [project guide](../README.md).

The backend serves the sibling frontend at `/m4`. Features include combined
employee calendars, summaries/widgets, HR search, team calendars, company
events, protected report viewing and landscape PDF export.

Policies enforce access; HR event writes require CSRF. Attendance, leave,
overtime and reports are read-only. Local preview uses seeded identities;
shared authentication and M2/M3/M5/M6/M12 integration remain pending. Do not
migrate local stub tables into the group's existing database.

Guides:

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
