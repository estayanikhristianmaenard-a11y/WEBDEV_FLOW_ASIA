# M4 MySQL integration summary

Updated: 29 October 2026.

M4 now supports the supplied `flow_asia_mis` schema through five adapters for
employees, calendar records, balances/pay periods, company events and identity.
It reads the shared field names directly, without renaming tables or adding the
standalone demo columns to the shared schema.

## Current local setup

- MySQL `flow_asia_mis` contains the supplied **46 tables and zero records**.
  No demo records were imported and the supplied SQL file was not edited.
- M4 business data uses the separate `m4_mis` connection. Existing standalone
  preview accounts and sessions continue using the default SQLite connection.
- The original SQLite business records were verified unchanged: **40 employees,
  1,195 attendance rows, 257 reports, 3 users and 5 departments**.
- A verified local database/environment backup is under
  `.tools/checkpoints/20261029-before-mis-connection/` at the repository root.
  Backups, populated environment files and database files remain ignored by Git.

The calendar and team list are empty until the shared database has employee and
account records. An empty shared database never falls back to demo attendance.

## Changes included

- Map canonical employee IDs, departments and positions while retaining employee,
  supervisor and HR access boundaries. Searches escape wildcard characters.
- Combine attendance, schedules, holidays, leave, overtime and correction metadata
  in batched reads. Convert overtime minutes to hours without counting the same
  approved overtime twice. Scheduled days without attendance remain unrecorded.
- Read supplied leave balances and payroll dates without calculating payroll.
- Map active MySQL accounts and roles. Local preview accounts match shared
  accounts by email; their SQLite numeric IDs are never reused as shared IDs.
  Event writes require a linked active shared account.
- Preserve timed events, creators and locations. The date-only event editor
  manages all-day events; cancellation retains the underlying event record.
  Timed events appear on the correct days in calendars and PDF exports.
- Disable demo migrations and demo seeding when `M4_DATA_SCHEMA=mis`.
  `m4:check` also verifies the required shared tables and columns.

## Connection and handoff

1. Keep the default application/session database separate from the MIS connection.
   For the existing local preview, retain the working SQLite setup in
   [SETUP.md](../SETUP.md).
2. Obtain the original `flow_asia_mis.sql` from the group. Import it only into a
   verified new, empty database: the supplied dump includes `DROP TABLE`
   statements. Do not reimport it over populated tables.
3. Set the following in the machine's ignored `.env`, using that machine's own
   database credentials:

   ```dotenv
   M4_DATA_SCHEMA=mis
   M4_MIS_CONNECTION=m4_mis
   M4_MIS_HOST=127.0.0.1
   M4_MIS_PORT=3306
   M4_MIS_DATABASE=flow_asia_mis
   M4_MIS_USERNAME=your_database_user
   M4_MIS_PASSWORD=your_database_password
   ```

4. Clear cached configuration if applicable, run `php artisan m4:check`, and
   start the standalone dashboard with `./local.ps1 serve` at
   `http://127.0.0.1:8000/m4`.

Changing `M4_DATA_SCHEMA` back to `demo` selects the preserved demo sources.
For the shared application, also follow the
[assembly contract](dashboard-integration.md#assemble-m4-inside-the-shared-laravel-application):
the host still owns authentication, sessions and navigation, and must disable
standalone preview. This local connection is not a deployment or completed
main-system login integration.

## Remaining source limitations

- The supplied schema has no progress-report source, so report status is
  unavailable. No substitute report table or sample report was added to MySQL.
- Attendance status strings must match the adapter's supported mappings; unknown
  values fail explicitly. Role names normalize spaces/hyphens to underscores;
  different group role names need explicit `module4.identity.roles` mappings.
- Multi-day leave totals are apportioned across the inclusive request range
  because the schema has no per-day allocation. Confirm this convention with
  the leave module before relying on per-day or partial-month leave totals.

## Verification

- Full Laravel suite: **126 tests passed**. After the final event/PDF changes,
  the **12 affected tests passed** again.
- Supplied schema imported into a separate MySQL test database: **7 integration
  tests passed**. Fixtures were rolled back and that test database was removed.
- Frontend regressions: **22 tests passed**; Laravel Pint and whitespace checks
  passed.
- Browser checks passed for the live empty MySQL calendar, team and event feeds,
  no demo fallback, mobile dark/light layouts and no JavaScript errors.
- Exact row comparisons confirmed the preserved SQLite business tables were
  unchanged; all 46 live MySQL tables were verified empty.
