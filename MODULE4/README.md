# Flow Asia: Module 4 Project Guide

## Project overview

Flow Asia is an art and entertainment company whose HR, Payroll and MIS system is divided into 12 modules. Module 4 (M4), the **Calendar & Schedule Dashboard**, displays employee attendance, schedules, leave, overtime, holidays and company events.

This folder contains the existing frontend prototype and the standalone backend foundation for **member #11: HR & Supervisor Tools**. Member #11 is a team assignment within M4, not system Module 11. Frontend files named `m11-*` do not expand this assignment into MIS development.

Until the other modules are available, M4 uses local mock records. Attendance reads go through a replaceable interface so real data can be connected later.

## Team responsibilities

| Owner | Responsibility |
| --- | --- |
| Member #09 | Main calendar aggregation API |
| Member #10 | Employee dashboard UI, day-details popup, month summary widgets, leave balance and next payday widgets |
| Member #11 | HR employee search and read-only calendar access, team/department calendar tools, company events, PDF export and server-side access enforcement |

Reuse teammates' agreed components and contracts during integration. A minimal testing page may be used if needed, but the shared employee dashboard belongs to #10.

Payroll calculations, attendance editing/clock-in, leave approval workflows and overtime approvals are outside member #11's scope. Pending requests are displayed for information only. Existing prototype Approve/Decline controls are not a requirement for this backend.

## Intended user flow

1. An authenticated HR user searches by employee name, employee ID or department.
2. HR selects an employee and opens their read-only calendar, choosing the month to inspect.
3. HR or a supervisor opens the permitted team/department calendar to inspect today's absent, late and on-leave employees, overlapping leaves and pending leave/OT.
4. HR creates, edits or deletes company events. These appear on every calendar with a distinct marker.
5. An authorized user prints or exports the permitted calendar with its legend and summary.

Authorization must be checked on the server for calendar pages, JSON feeds and exports. Hiding a button or switching a preview role in JavaScript is not access enforcement.

## Access rules

| Role | Calendar access | Company event management |
| --- | --- | --- |
| `employee` | Own calendar only; no `/hr/*` access | None |
| `supervisor` | Own calendar and employees whose `supervisor_id` matches their employee ID | None |
| `hr_admin`, `hr_staff`, `super_admin` | Any employee and department | Create, edit and delete |

Employees and supervisors cannot manage company events. Every role has read-only access to attendance, leave and OT through M4. Use `EmployeeCalendarPolicy::viewCalendar(User, Employee)` and the HR-only `manage-events` Gate.

M4 must never expose salary, TIN/SSS/PhilHealth/Pag-IBIG identifiers or bank details.

## Technology and folders

Target stack: Laravel 13, PHP 8.3+, MySQL, Blade, `laravel/ui` with Bootstrap 5, FullCalendar v6, server-side `barryvdh/laravel-dompdf`, and Pest feature tests. Use Asia/Manila time, English UI text and PHP currency. Minimal session login and dashboard connection code are prepared; shared Bootstrap/FullCalendar integration, PDF export and runtime verification remain pending.

```text
MODULE4/
|-- README.md                         Project overview and work guide
|-- flow-asia-dashboard-prototype/     Existing HTML/CSS/JavaScript frontend
|   |-- index.html
|   |-- css/
|   `-- js/
`-- flow-asia-backend/                 Separate Laravel application
    |-- app/Contracts/                Calendar data interface
    |-- app/Services/                 Mock data-source implementation
    |-- app/Models/                   Database models
    |-- app/Http/Controllers/         Endpoint controllers
    |-- app/Http/Requests/            Request validation, when implemented
    |-- app/Policies/                 Authorization, when implemented
    |-- config/calendar.php           Shared statuses and priorities
    |-- database/migrations/          Schema
    |-- database/seeders/             Demo data
    |-- routes/                       Application routes
    |-- tests/Feature/                Feature tests
    `-- SETUP.md                      Local installation details
```

Keep backend classes in their standard Laravel folders. Dashboard connection was authorized and uses the existing layout and palette. Verify the prepared endpoints and browser flows before describing the integration as working end to end.

## Current progress

Calendar text and status colors have been restored to the original prototype styling: plain Present labels, solid status tags, contrasting Late cells, red Absent, blue Leave, yellow Invalid Punch and neon green Overtime/Holiday. Backend connections and read-only inspection remain in place. Shared screen/PDF palette alignment still needs review.

Frontend polish: emoji and text-symbol icons have been replaced with a small local SVG icon set. Dashboard connection code now uses `/m4` for session-authenticated search, team data and company events while preserving the layout and palette. Directly opening the standalone HTML still runs the mock preview. See [dashboard connection guide](flow-asia-backend/docs/dashboard-integration.md).

HR inspection shows the selected employee's name, ID, department and position with a read-only badge. The original six-entry legend includes Present, Late, Absent, Leave, Invalid Punch and Overtime / Holiday. Connected mode uses authorized team records, leaves missing days unrecorded, and replaces preview role switching with Sign out. Mock charts, balances and approval controls are hidden in connected mode. Runtime and browser verification remain pending.

| Piece | Current state | Remaining work |
| --- | --- | --- |
| Backend foundation | Source prepared: Laravel starter, stub schema/models, data-source service, status config, DemoSeeder and Pest tests | Install dependencies on PHP 8.3+, run migrations/seeders and execute tests |
| HR employee autocomplete | Endpoint and UI connection prepared: role scope, name/ID/department, 10-result limit, debounce, cancellation and keyboard navigation | Runtime and browser verification |
| Team/department view | Batched endpoint and UI connection prepared: today's indicators, approved leave overlaps and pending leave/OT | Runtime verification; replace stub sources with agreed team data |
| Company events | Backend CRUD/feed and frontend creation/editing modal, deletion and calendar markers prepared | Run migrations/tests and verify persistence in a browser |
| PDF export | Authorized DomPDF endpoint, landscape template and frontend download button prepared | Install dependencies, run PDF feature tests and inspect the generated file |
| Read-only enforcement | Calendar policy, write guard and event Gate prepared; team reads authorize selected employees | Run tests; individual calendar and team policies are attached; authorize future PDF export |

PHP/JS syntax checks and seven Node request-transport tests pass. Laravel migrations, Pest tests and browser verification have not run; PHP 8.3+ and Composer dependencies are still required. Login/logout source is now prepared, and demo identities can be used after setup. No Composer lock file has been resolved yet.

The demo month is October 2026, with five departments and 40 employees. It includes every configured status, an employee with no attendance, a mid-month hire, and simultaneous holiday/approved leave. See [SETUP.md](flow-asia-backend/SETUP.md) for setup commands and test accounts.

## Build checklist

Complete one build-order step at a time and review it before starting the next.

- [ ] **1. Verify the foundation:** provide PHP 8.3+ and Composer, install dependencies, configure a dedicated MySQL database, run migrations and deterministic seeders, and pass foundation tests. Prepare the agreed authentication setup before protected endpoints are exercised, without generating frontend changes. An optional 200+ employee stress seeder remains future work.
- [ ] **2. Company Events:** verify the prepared migration, model, overlap feed, validation, HR-only CRUD, creation/editing modal and calendar markers.
- [ ] **3. HR employee search:** verify the prepared name/ID/department endpoint, role scope, 10-result limit and UI with debounce, keyboard navigation and cancellation.
- [ ] **4. Calendar access and read-only enforcement:** implement `EmployeeCalendarPolicy`, prevent access to other employees/teams, and reject attendance/leave/OT writes. Test policies before exposing calendar data.
- [ ] **5. Read-only employee calendar:** connect the permitted month data, legend and summary to the agreed calendar components; integrate month selection without taking over #10's dashboard.
- [ ] **6. Team/department calendar:** retrieve attendance in one batch, add today's indicators, pending requests and overlap handling, then integrate the frontend view.
- [ ] **7. PDF export:** produce authorized landscape PDFs with table-based Blade layouts, shared status codes/colors, legend, summary and generated-by footer.
- [ ] **8. Final validation and write-up:** run the complete feature suite, check screen/PDF consistency, verify integrations and document the module for demonstration.

**Immediate next action:** provide PHP 8.3+ and Composer and run the foundation, Company Events, search, authorization and team calendar suite. Source is prepared, but steps remain unchecked until verified. See the [Company Events guide](flow-asia-backend/docs/company-events.md), [search guide](flow-asia-backend/docs/employee-search.md), [authorization guide](flow-asia-backend/docs/calendar-authorization.md) and [team calendar guide](flow-asia-backend/docs/team-calendar.md). Future employee-calendar and export routes must authorize access before reading data. Align their contracts with the ERD and CR1/CR3 requirements reviewed in the PDFs.

## Data and integration contracts

- Stub tables: `users`, `departments`, `employees`, `attendance_daily`. The module-owned `company_events` migration is prepared in step 2.
- Controllers, calendar presenters and PDF code must read attendance through `App\Contracts\CalendarDataSource`, never directly from `attendance_daily`.
- `forEmployee()` reads one employee; `forEmployees()` reads a batch in one attendance query. Both use inclusive date bounds. Batched results retain empty collections for employees with no records.
- One attendance row represents one employee/date. `additional_statuses` preserves simultaneous statuses; it is a stub extension to review with #09 when replacing the data source.
- FullCalendar events must use `{id, title, start, end, allDay, classNames, extendedProps: {kind, ...}}`. Company events use `kind: 'company_event'` and class `company-event`.
- FullCalendar all-day end dates are exclusive: an inclusive stored event end date must be increased by one day in the feed.
- Team views use one batched request, not a request per employee. Agree on endpoint paths, response shapes and shared file ownership with teammates before integration.
- Event route source: authenticated `GET /calendar/events`, with HR-only `/hr/events` index/store/update/destroy routes. `/m4` uses these endpoints and sends the session CSRF token for writes; runtime verification remains pending.

## Calendar display rules

Keep colors, labels, short print codes and priority in `config/calendar.php`; use the same configuration for screens and PDFs.

| Status | Display |
| --- | --- |
| `present` | White; plain label in the frontend per the latest design preference |
| `late` | Orange |
| `absent` | Red |
| `leave_approved` | Blue |
| `leave_pending` | Light blue/striped |
| `overtime` | Purple |
| `holiday` | Green |
| `rest_day` | Grey |
| `half_day` | Yellow |

When several statuses apply, priority is **Holiday > Approved Leave > Absent > Late**. Company events use a distinct marker instead of an attendance status color. PDFs include short codes so they remain legible in black and white. DomPDF layouts use HTML tables because flexbox/grid are unsupported.

## Working and branching guide

Keep `main` for shared reviewed work. A suggested integration branch is `m4/member11-hr-tools`; create small feature branches from it, such as `m4/member11-company-events`. Merge each tested feature into that branch, then coordinate merging the completed part into `main`. These branch names are a recommendation, not branches already created.

Use small meaningful commits, Form Requests for validation, Policies/Gates for authorization, Eloquent models and migrations. Keep secrets and local `.env` files out of commits. Update this guide as features are implemented and tested; distinguish prepared code from verified behavior.

## Team calendar backend progress

The member #11 team endpoint source is prepared at `GET /hr/calendar/team` with
required `month` and optional `department_id`. It returns authorized employee
days, today's absent/late/on-leave lists, approved leave overlaps, and pending
leave/OT indicators through a batched attendance read. The dashboard connection
uses this response for team indicators and selected employee calendars. See [the backend guide](flow-asia-backend/docs/team-calendar.md) for
response semantics, stub limitations and test instructions. Runtime verification
still requires PHP 8.3+ and installed Composer dependencies. Next: verify these
endpoints, then prepare the read-only employee calendar and PDF export integration.

## Completion criteria

- HR can search by name, ID or department and view a month-filtered read-only employee calendar.
- Supervisors see only themselves and their team; employees cannot view other employees or access HR routes.
- Team calendars show today's absent/late/on-leave employees, overlapping leaves and pending leave/OT using batched retrieval.
- HR can manage company events that appear on all calendars with a distinct marker.
- Attendance, leave and OT cannot be changed through M4 endpoints.
- PDFs match the permitted calendar's statuses, legend and summary and print clearly.
- Feature tests pass, frontend integration is verified, and the module write-up explains the implemented flow.

## Individual calendar progress

The individual month endpoint and dashboard connection are prepared at
`GET /calendar/employees/{employee}/month?month=2026-10`. It supports personal
calendars and authorized HR/supervisor selections without using the team batch
as the calendar source. PHP/JS syntax checks passed; Laravel feature tests and
browser checks remain pending runtime setup. See the
[individual calendar guide](flow-asia-backend/docs/employee-calendar.md).
PDF source is now prepared; next is runtime verification and PDF review.

## Frontend-to-backend control map

Open `/m4` through Laravel for these connections; standalone `index.html` is a mock preview.

| Control | Backend connection |
| --- | --- |
| Sign in / Sign out | `POST /login`, `POST /logout` |
| Employee autocomplete | `GET /hr/employees/search?q=...` |
| Select employee / month / close employee view | `GET /calendar/employees/{employee}/month?month=...` |
| Team indicators | `GET /hr/calendar/team?month=...`; department filtering uses the authorized response |
| Company event markers | `GET /calendar/events?start=...&end=...` |
| Create / edit / delete event | `POST /hr/events`, `PUT /hr/events/{event}`, `DELETE /hr/events/{event}` |
| Day details | Opens a read-only modal using the fetched employee records and events |
| Export PDF | `GET /calendar/employees/{employee}/pdf?month=...`; standalone preview retains browser printing |

Prepared connection code is not proof of end-to-end behavior. PHP 8.3+, Composer dependencies,
migrations, Laravel tests and browser checks are still required. Sidebar links for other
modules remain prototype navigation, and leave/OT approvals remain outside member #11's scope.

## PDF progress

Authorized employee/month PDF export and the connected download button are
prepared. The landscape document includes company events, a code/color legend,
primary-status summary and generated-by footer. PHP/JS syntax checks and seven
Node transport tests passed. Composer installation, Laravel tests and actual
PDF generation/visual review remain pending. See
[PDF guide](flow-asia-backend/docs/pdf-export.md).
