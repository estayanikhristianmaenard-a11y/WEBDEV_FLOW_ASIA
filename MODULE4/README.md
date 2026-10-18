# Flow Asia: Module 4 Calendar & Schedule Dashboard

M4 provides the employee calendar and HR/supervisor tools assigned to members
#09, #10 and #11. All three members' features are implemented in the standalone
system. Main-system authentication and M2/M3/M5/M6/M12 integration remain pending.
Member #11 is a team assignment within M4, not the separate MIS module.

The M4 integration boundary is ready for the shared application's source adapters.
The existing sidebar/design is preserved. Calendar views refresh on returning to
the tab, or after the host signals `flowasia:calendar-updated` following a confirmed
save. M4 re-reads authorized source records; it does not duplicate other modules'
data. See the [assembly and refresh guide](flow-asia-backend/docs/dashboard-integration.md).
Actual source mapping and live shared-database acceptance still require the owners'
code listed below.

## Run locally

Follow [SETUP.md](flow-asia-backend/SETUP.md). From `flow-asia-backend`, run
`./local.ps1 serve` and open `http://127.0.0.1:8000/m4`.
Opening the prototype's `index.html` directly shows the mock preview.

M4 has no login/logout. Local preview uses a seeded identity only when enabled
in a local/testing environment. Existing authenticated users take precedence.
Disable `M4_STANDALONE_PREVIEW` when integrating; production cannot enable it.

## Task tracker

| Owner | Task | Standalone status | Remaining integration |
| --- | --- | --- | --- |
| #09 | Combined feed | Attendance, leave, OT, holidays, company events, summaries and reports in one employee/month response | Shared M2/M3/M5/M6/M12 sources |
| #09 | Priority/F075 | Holiday > Approved Leave > Invalid > Absent > Late; rejected punches become Invalid with correction metadata | Map M3 rejection data |
| #09 | Performance | Batched reads and a 240-employee regression without per-employee attendance/report queries | Benchmark shared MySQL queries |
| #10 | Employee dashboard | Own calendar on opening; same calendar and summaries for HR inspection | Main-system layout/session |
| #10 | Legend/day details | All statuses, shift, punches, worked hours, late/OT, leave/holiday details, remarks and corrections | Authoritative source metadata |
| #10 | Summaries/widgets | Status counts, total late minutes, cutoff OT, leave days/balances and next supplied payday | M5/payroll read sources |
| #10 | Navigation/mobile | Month/week toggle, picker, Previous/Next and responsive dark/light layouts | Shared component validation |
| #10 | F076 reports | Submitted, required-without-report, not-required and unavailable states; authorized report viewer | M12 report adapter |
| #11 | Employee search | Name/ID/department, 10 results, debounce, cancellation and keyboard selection | Shared employee schema |
| #11 | Read-only inspection | Authorized feed, summaries, day details and PDF | Retain policies |
| #11 | Team calendar | Multi-employee matrix, today lists, approved leave overlaps and pending leave/OT markers | Shared request data |
| #11 | Company events | Validated HR CRUD, CSRF and markers on employee/team/PDF views | Event schema ownership |
| #11 | PDF | Authorized landscape export, shared counts, late/OT totals, legend and generated-by footer | Final shared feed |
| All | Handoff | Local features tested and documented | External integrations and final client demo |

Payroll calculations, attendance editing/clock-in, leave/OT approval and report
submission belong to other modules. M4 reads their results and shows pending
indicators without approval controls. No salary, statutory IDs, bank details
or credentials are exposed by calendar responses.

## User flow and permissions

1. Employees open their own calendar. HR searches by name, ID or department.
2. Choose Month/Week, Previous/Next or a month. Summaries cover the selected month.
3. Click a day for read-only details. Invalid includes a text label/correction
   reason; submitted reports open the protected report viewer.
4. HR/supervisors filter the team matrix and select an employee/day to inspect.
   Today's indicators stay independent of the selected month.
5. HR manages company events; permitted users download the month's PDF.

| Role | Calendar/report/PDF access | Company event management |
| --- | --- | --- |
| `employee` | Own calendar; no `/hr/*` access | None |
| `supervisor` | Self and direct reports whose `supervisor_id` matches their employee ID | None |
| `hr_admin`, `hr_staff`, `super_admin` | Any employee/department | Create/edit/delete |

`EmployeeCalendarPolicy::viewCalendar` runs before selected-employee reads.
Team lists are server-scoped and individually authorized; reports also check
ownership. `manage-events` and CSRF protect HR writes. `ReadOnlyCalendar` rejects
attendance/leave/OT/calendar/report writes before route matching.

## Stack and code

Laravel 13, PHP 8.4, MySQL-compatible migrations, Blade, DomPDF and Pest.
The existing HTML/CSS/JavaScript calendar is reused without a new frontend
dependency/build. Events retain FullCalendar-compatible JSON for the shared
component. UI text is English; calendar dates use Asia/Manila.

| Location | Purpose |
| --- | --- |
| `flow-asia-dashboard-prototype/js/modules/m4-backend.js` | Employee/HR dashboard controls |
| `flow-asia-dashboard-prototype/js/modules/m4-team-calendar.js` | Multi-employee month matrix |
| `flow-asia-backend/app/Contracts` | Attendance/context interfaces |
| `flow-asia-backend/app/Services` | Aggregation, day presentation, batching and PDF |
| `flow-asia-backend/app/Policies` | Employee access |
| `flow-asia-backend/config/calendar.php` | Shared labels, codes and priority |
| `flow-asia-backend/database` | Isolated stub schema and deterministic seeds |

## Feed and source contracts

`GET /calendar/employees/{employee}/month?month=2026-10` returns `month`,
`read_only` and safe employee `data` with `days`, `company_events`, `summary`,
`leave_balances`, `next_payday`, `cutoff` and `range` (exclusive end).

- `CalendarDataSource` supplies one employee or a batch with inclusive bounds.
  Each source row represents one employee/date. Optional `calendar_details`
  carries shift, punch decision, leave, holiday and correction metadata.
- `CalendarContextSource` batches safe balance/pay-period profiles and report
  references, and supplies ownership-scoped report contents.
- `CalendarDayPresenter` applies priority and emits the same safe day shape for
  employee/team/PDF consumers, retaining secondary statuses without source writes.
- Worked hours use authoritative `worked_minutes`, or complete punches minus an
  explicitly supplied break. Overnight calculation needs an explicit overnight
  shift; rejected punches/insufficient metadata produce no computed value.
- Balances/paydays are supplied records, not ledger/payroll calculations. Cutoff
  OT includes the complete supplied period, including a month boundary.
- Reports are `submitted`, `missing` for a required shift, `not_required` or
  `unavailable`. Report bodies are excluded from calendar feeds.
- Company events use `{id,title,start,end,allDay,classNames,extendedProps}` with
  `kind: 'company_event'`. Inclusive stored ends become exclusive feed ends.
- Pending leave/OT are employee/date indicators, not distinct request counts.

For group assembly, register `FlowAsia\Module4\Providers\Module4ServiceProvider`
in the host and set `M4_MODE=shared`. Employee, attendance, context, identity and
event interfaces are replaceable through configuration or host container bindings.
The host keeps its own models, session and root routes; M4 mounts at `/modules/m4`.
Run `php artisan m4:check` and follow the [assembly contract](flow-asia-backend/docs/dashboard-integration.md#assemble-m4-inside-the-shared-laravel-application).
Context JSON columns and
`calendar_reports` are local stub storage. Do not migrate stub tables into the
existing group database. All demo migrations are isolated under
`database/migrations/standalone` and are not loaded in shared mode.

## Display and counting rules

`config/calendar.php` supplies labels, codes and priority. The latest prototype
design is retained: plain Present, contrasting Late, red Absent, blue Leave,
yellow Invalid/Half-Day, neon green OT/Holiday, grey Rest Day and striped Pending
Leave. Text/codes accompany colors; light-theme variants maintain readability.

Each primary/secondary status counts once per day; categories may overlap.
Missing days remain unrecorded. Approved leave uses supplied duration, including
half-days. Rejected Invalid punches do not count as Present/Late. Cancelled leave
with no remaining status shows No attendance status without implying presence or
absence. Screens and PDFs share the presenter/summary. PDFs escape text and use
table-based Blade layouts;
long event content can create extra pages.

## Endpoints

| Control | Endpoint |
| --- | --- |
| Dashboard | `GET /m4` |
| Employee search | `GET /hr/employees/search?q=...` |
| Combined employee month | `GET /calendar/employees/{employee}/month?month=...` |
| Team | `GET /hr/calendar/team?month=...&department_id=...` |
| Events | `GET /calendar/events?start=...&end=...` |
| Event management | `GET/POST /hr/events`, `PUT/PATCH/DELETE /hr/events/{event}` |
| Report | `GET /calendar/employees/{employee}/reports/{report}` |
| PDF | `GET /calendar/employees/{employee}/pdf?month=...` |

## Verification and handoff

Use the [QA task and progress tracker](flow-asia-backend/docs/qa-progress.md) to
check the 24 manual acceptance cases, record defects and track shared release gates.

See [SETUP.md](flow-asia-backend/SETUP.md) for current results and commands.
Checks cover regressions, actual browser flows, role boundaries, mobile layouts
and real PDFs. The demo has five departments, 40 employees, an empty calendar,
a mid-month hire, holiday/leave overlap, overlapping team leave, a rejected
punch, submitted/missing reports and pending OT. Query regression covers 240
employees.

- [x] Combined feed, shared priority and F075
- [x] Employee month/week UI, details, summary and source-backed widgets
- [x] F076 states and authorized report viewer
- [x] HR search, inspection and multi-employee team calendar
- [x] Events, access enforcement and screen/PDF consistency
- [ ] Replace adapters with shared data/authentication contracts
- [ ] Benchmark/validate the complete shared MySQL build
- [ ] Final group demonstration

Keep `main` for reviewed shared work and use small tested feature commits.
Remaining handoff needs M2/M3/M5/M6/M12 contracts and main authentication.
