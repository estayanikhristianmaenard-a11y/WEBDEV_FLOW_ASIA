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

## M4 group task tracker ? #09, #10 and #11

Status here reflects this repository, not claims about work stored elsewhere.
**Verified locally** means connected to the standalone `/m4` backend and tested
with seeded data. **Shared integration pending** means not yet connected to the
complete group's system. M4 has no login/logout feature.

| Owner | Assigned task | Current status here | Next action |
| --- | --- | --- | --- |
| #09 | Combined attendance/leave/OT/holiday/event feed | Partial support: replaceable data-source interface and stub employee/month endpoint exist; final aggregator not available | Supply shared feed and agreed field contract |
| #09 | Status priority, including CR1 Invalid punch (F075) | Primary/additional statuses preserved; Invalid can be displayed; rejection-to-status and priority computation not implemented here | Implement priority in shared feed; HR/PDF consume results |
| #09 | One-month query performance | Batched retrieval has tests; realistic-volume timing/stress checks pending | Benchmark shared queries and indexes |
| #10 | Employee calendar/dashboard | Standalone personal calendar is connected; final shared component integration pending | Reuse or replace preview with agreed shared component |
| #10 | Color legend and day popup | Legend/status labels connected; status, punch times, late/OT and remarks shown | Add shift, worked hours, leave/holiday detail and correction metadata |
| #10 | Month summaries and leave/payday widgets | Basic recorded-day counts connected; screen/PDF counting rule aligned | Add total late minutes, cutoff OT, real leave balances and next payday |
| #10 | Month/week and date navigation | Month picker and Previous/Next are connected and browser-tested; week toggle absent | Implement week view if required by the agreed UI |
| #10 | CR3 report status/link (F076) | Not connected to real M12 reports | Add submitted/no-report/required states and authorized report links, including HR view |
| #10 | Mobile layout | Responsive styling exists; comprehensive small-screen validation pending | Verify shared screens and HR controls on small viewports |
| #11 | HR employee autocomplete | Verified locally: name/ID/department, 10 results, debounce/cancellation, keyboard selection | Connect real employee schema/feed |
| #11 | Read-only employee month view | Verified locally: selected employee, month navigation, day details and access policy | Retain authorization when using #09 feed |
| #11 | Team/department calendar | Verified local indicators: today lists, approved overlaps and daily pending leave/OT; visual multi-employee calendar still pending | Build multi-employee calendar presentation and map real request records |
| #11 | Company event management | Verified locally: create/edit/delete, validation and shared markers | Map event fields into agreed shared schema |
| #11 | Print/PDF export | Verified locally: authorized landscape PDF, events, summary, legend and generated-by footer | Verify exports against final shared feed |
| #11 | Screen/PDF consistency | Common server labels, original dark-palette colors and status-membership counts implemented | Light theme retains readable theme variants; agree final palette as a group |
| #11 | Access enforcement | Policies, server scope, CSRF and blocked attendance/leave/OT writes verified | Use main system's user session; disable standalone preview |
| All three | Shared schema, data and authentication integration | Pending: other members' backend source is not in this repository | Agree contract, replace stub data source, then run combined tests |
| All three | Final demo and regression verification | Standalone tests/browser/PDF checks passed; combined-system checks pending | Test complete group build with realistic data |

The prototype's MIS charts and approval buttons are outside M4 member #11 scope
and are hidden in connected mode. No payroll, leave approval or overtime approval
workflow is delivered here. PDF codes can be shorter than screen labels (for
example AL), but both use the same status meaning. Counts include primary and
additional statuses once each per day, so categories may overlap; missing days
are not inferred as Present or Absent. Final priority remains #09's responsibility.

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

Target stack: Laravel 13, PHP 8.3+, MySQL, Blade, `laravel/ui` with Bootstrap 5, FullCalendar v6, server-side `barryvdh/laravel-dompdf`, and Pest feature tests. Use Asia/Manila time, English UI text and PHP currency. Dashboard connections and local identity preview are available; standalone runtime and PDF export are verified; shared Bootstrap/FullCalendar integration remains pending.

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

HR inspection shows the selected employee's name, ID, department and position with a read-only badge. The original six-entry legend includes Present, Late, Absent, Leave, Invalid Punch and Overtime / Holiday. Connected mode uses authorized team records, leaves missing days unrecorded, and removes the preview role switch in backend mode. Mock charts, balances and approval controls are hidden in connected mode. Standalone runtime and browser verification passed on 12 October 2026.

| Piece | Current state | Remaining work |
| --- | --- | --- |
| Backend foundation | Source prepared: Laravel starter, stub schema/models, data-source service, status config, DemoSeeder and Pest tests | Verified locally; shared schema integration pending |
| HR employee autocomplete | Endpoint and UI connection prepared: role scope, name/ID/department, 10-result limit, debounce, cancellation and keyboard navigation | Verified locally; group integration pending |
| Team/department view | Batched endpoint and UI connection prepared: today's indicators, approved leave overlaps and pending leave/OT | Verified locally; replace stub sources with agreed team data |
| Company events | Backend CRUD/feed and frontend creation/editing modal, deletion and calendar markers prepared | Verified locally; shared schema mapping pending |
| PDF export | Authorized DomPDF endpoint, landscape template and frontend download button prepared | Verified locally; final shared palette agreement pending |
| Read-only enforcement | Calendar policy, write guard and event Gate prepared; team reads authorize selected employees | Tests passed; individual calendar, team and PDF enforce access rules |

Portable PHP 8.4.26, Composer 2.10.3 and locked dependencies are installed locally. MySQL migrations/seeding, all 89 Laravel tests (330 assertions), seven Node transport tests, Chrome frontend/backend checks and real PDF generation/review passed on 12 October 2026. M4 has no login/logout. Local preview supplies a seeded identity; main-system session/data integration remains pending.

The demo month is October 2026, with five departments and 40 employees. It includes every configured status, an employee with no attendance, a mid-month hire, and simultaneous holiday/approved leave. See [SETUP.md](flow-asia-backend/SETUP.md) for setup commands and test accounts.

## Build checklist

Complete one build-order step at a time and review it before starting the next.

- [x] **1. Verify the foundation:** provide PHP 8.3+ and Composer, install dependencies, configure a dedicated MySQL database, run migrations and deterministic seeders, and pass foundation tests. Prepare the agreed authentication setup before protected endpoints are exercised, without generating frontend changes. An optional 200+ employee stress seeder remains future work.
- [x] **2. Company Events:** verify the prepared migration, model, overlap feed, validation, HR-only CRUD, creation/editing modal and calendar markers.
- [x] **3. HR employee search:** verify the prepared name/ID/department endpoint, role scope, 10-result limit and UI with debounce, keyboard navigation and cancellation.
- [x] **4. Calendar access and read-only enforcement:** implement `EmployeeCalendarPolicy`, prevent access to other employees/teams, and reject attendance/leave/OT writes. Test policies before exposing calendar data.
- [x] **5. Read-only employee calendar:** connect the permitted month data, legend and summary to the agreed calendar components; integrate month selection without taking over #10's dashboard.
- [x] **6. Team/department calendar:** retrieve attendance in one batch, add today's indicators, pending requests and overlap handling, then integrate the frontend view.
- [x] **7. PDF export:** produce authorized landscape PDFs with table-based Blade layouts, shared status codes/colors, legend, summary and generated-by footer.
- [ ] **8. Final validation and write-up:** run the complete feature suite, check screen/PDF consistency, verify integrations and document the module for demonstration.

**Immediate next action:** coordinate the shared schema/data-source contract with #09 and shared calendar/authentication components with #10. The standalone implementation is verified; consult [SETUP.md](flow-asia-backend/SETUP.md) for local startup and verification results. Do not migrate stub tables into the group database.

## Data and integration contracts

- Stub tables: `users`, `departments`, `employees`, `attendance_daily`. The module-owned `company_events` migration is prepared in step 2.
- Controllers, calendar presenters and PDF code must read attendance through `App\Contracts\CalendarDataSource`, never directly from `attendance_daily`.
- `forEmployee()` reads one employee; `forEmployees()` reads a batch in one attendance query. Both use inclusive date bounds. Batched results retain empty collections for employees with no records.
- One attendance row represents one employee/date. `additional_statuses` preserves simultaneous statuses; it is a stub extension to review with #09 when replacing the data source.
- FullCalendar events must use `{id, title, start, end, allDay, classNames, extendedProps: {kind, ...}}`. Company events use `kind: 'company_event'` and class `company-event`.
- FullCalendar all-day end dates are exclusive: an inclusive stored event end date must be increased by one day in the feed.
- Team views use one batched request, not a request per employee. Agree on endpoint paths, response shapes and shared file ownership with teammates before integration.
- Event route source: authenticated `GET /calendar/events`, with HR-only `/hr/events` index/store/update/destroy routes. `/m4` uses these endpoints and sends the session CSRF token for writes; standalone verification passed.

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
passed with the installed local runtime. Individual calendar and PDF connections are also verified; next is group integration.

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
as the calendar source. PHP/JS checks, Laravel tests and browser checks passed on 12 October 2026. See the
[individual calendar guide](flow-asia-backend/docs/employee-calendar.md).
PDF export and visual review passed; next is shared-system integration.

## Frontend-to-backend control map

Open `/m4` through Laravel for these connections; standalone `index.html` is a mock preview.

| Control | Backend connection |
| --- | --- |
| Open M4 | `/m4` opens directly in local preview; shared authentication belongs to the main system |
| Employee autocomplete | `GET /hr/employees/search?q=...` |
| Select employee / month / close employee view | `GET /calendar/employees/{employee}/month?month=...` |
| Team indicators | `GET /hr/calendar/team?month=...`; department filtering uses the authorized response |
| Company event markers | `GET /calendar/events?start=...&end=...` |
| Create / edit / delete event | `POST /hr/events`, `PUT /hr/events/{event}`, `DELETE /hr/events/{event}` |
| Day details | Opens a read-only modal using the fetched employee records and events |
| Export PDF | `GET /calendar/employees/{employee}/pdf?month=...`; standalone preview retains browser printing |

Standalone connections were verified on 12 October 2026 with local PHP/Composer, MySQL migrations, Laravel tests and Chrome checks. Group integration is still required. Sidebar links for other
modules remain prototype navigation, and leave/OT approvals remain outside member #11's scope.

## PDF progress

Authorized employee/month PDF export and the connected download button are
prepared. The landscape document includes company events, a code/color legend,
status-membership summary and generated-by footer. PHP/JS syntax checks and seven
Node transport tests passed. Composer installation, all 89 Laravel tests and actual
PDF generation/visual review also passed on 12 October 2026. See
[PDF guide](flow-asia-backend/docs/pdf-export.md).

Standalone verification is complete. Checklist items 1?7 are checked for local functionality; item 8 remains open for final group integration and demonstration.

M4 login/logout was removed on 12 October 2026. Local preview uses the configured seeded identity; disable preview mode when integrating with the main system. Existing session users are preserved, and production cannot activate the preview.

## Requirement check against the PDFs

Project Plan page 7 assigns HR employee search/calendar view, team/department
calendar, company events and print/PDF export to member #11. These controls
are implemented and verified locally without module login/logout. The latest
check passed 61 relevant Laravel tests and seven request tests; Chrome checked
search/selection, month/department filtering, invalid event dates, event CRUD,
markers, PDF download, CSRF rejection and actual punch times/remarks in day details.

Change Request page 7 assigns F075 Invalid status/priority to #09 and F076 report
submitted/report links to #10. HR must consume the same shared results when those
contracts are supplied; this implementation does not fabricate reports, shifts
or computed worked hours. The PDF is the specification, not the other members'
backend source. Shared schema mapping and final team feed/component integration
remain a handoff requirement. Local verification does not guarantee every
unseen production dataset or integration.
