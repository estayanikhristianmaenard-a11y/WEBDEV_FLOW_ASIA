# Flow Asia M4 backend — member 11

This is a separate Laravel 13 application. Source includes the standalone mock-data foundation, Company Events endpoints, role-scoped employee search, calendar policies, the read-only guard and the batched team calendar endpoint. The existing dashboard now has a same-origin connection through `/m4`, with session sign-in at `/login`. The individual employee month endpoint is also connected. Server PDF export is now prepared and connected to Export PDF. Runtime verification remains pending. See [dashboard integration](docs/dashboard-integration.md).

## Requirements and local setup

- PHP 8.3+ with Laravel's required extensions, including PDO MySQL; PDO SQLite is used only for isolated feature tests.
- Composer 2 and a local MySQL/MariaDB server.
- Create a new, dedicated empty database named `flow_asia_m4`. Do not point this demo at another module's database.

Run from this folder after PHP 8.3+ and Composer are available on PATH:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Edit `.env` for your local database credentials, then run:

```powershell
php artisan migrate --seed
php artisan test
php artisan serve
```

Do not run `composer run setup` against an existing `.env` without reviewing its settings. Setup does not run migrations automatically. No npm commands are needed for this backend foundation. Laravel's default resources are unused starter files; do not integrate or generate frontend scaffolding yet.

## Folder ownership

| Folder | Responsibility |
| --- | --- |
| `app/Contracts` | Replaceable `CalendarDataSource` contract |
| `app/Services` | Batched mock attendance reads |
| `app/Models` | Stub employee, department and attendance records |
| `config/calendar.php` | Shared status colors, print codes, priority, demo month |
| `database/migrations` | Database structure |
| `database/seeders` | Local demo roster, accounts and attendance |
| `tests/Feature` | Pest foundation checks |
| `app/Http/Controllers`, `app/Http/Requests`, `app/Policies` | Standard locations for later endpoints, validation and authorization |

The last three application layers will be populated when their build steps start. No empty placeholder classes are needed.

All application reads of attendance must use `CalendarDataSource`; only its stub implementation queries `attendance_daily`. The seeder writes fixtures directly. The contract returns rows grouped by employee ID, including empty collections for employees without attendance. Date bounds are inclusive. This internal contract is not yet the FullCalendar event JSON contract.

`additional_statuses` allows a holiday and approved leave on the same date while retaining one attendance row per employee per date. Later presenters must apply the configured priority and preserve secondary statuses.

## Demo data

October 2026 is fixed in configuration. There are five departments, eight employees per department, and one supervisor per department. EMP-040 has no records; EMP-039 joins October 15; EMP-002 has holiday and approved leave on October 12. Values are deterministic without a random generator. Re-running the seeder updates fixtures instead of duplicating them.

Local/testing accounts: `employee@test.com`, `supervisor@test.com`, `hr@test.com`; password: `password`. Open `/m4` and sign in through `/login` after setup. Roles and employee links are assigned by the seeder; role is deliberately excluded from User mass assignment.

## Next steps

Verify the prepared foundation, Company Events, employee search, authorization, team calendar and dashboard integration tests, then check the `/m4` flows in a browser. Include EmployeeCalendarTest, and CalendarPdfTest, then check the PDF download and printed layout. The current minimal login uses Laravel session authentication without extra packages; shared Bootstrap authentication remains a later integration task. The team endpoint's pending OT field is temporary stub storage, not a request approval workflow.

No salaries, statutory identifiers or bank details are stored in the M4 stub tables. Calendar endpoints require authenticated, authorized access.

## Verification status

Source syntax can be checked with the existing PHP 8.2 executable, but Laravel 13 cannot be booted on it. Dependency resolution, migrations and Pest execution remain pending PHP 8.3+ and Composer. There is no Composer lock file until dependencies are resolved. Frontend connection code and seven passing Node transport tests are present; the backend is not yet running or verified end to end.

## PDF export

The `barryvdh/laravel-dompdf` dependency is declared and will be installed by
`composer install` after PHP 8.3+ is ready. The `/m4` Export PDF control requests
the selected employee/month through an authorized endpoint. See
[PDF export guide](docs/pdf-export.md). Actual generation has not run here.
