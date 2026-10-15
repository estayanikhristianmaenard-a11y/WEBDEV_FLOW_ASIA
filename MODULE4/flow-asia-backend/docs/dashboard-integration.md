# Dashboard connection guide

Laravel serves the sibling HTML/CSS/JavaScript frontend at `/m4` and constrains
`/m4/assets/...` to code assets. It injects session identity, CSRF, the application
URL and configured event types. Requests retain the application path when hosted
in a subdirectory and use same-origin session cookies; no separate CORS setup or
frontend credentials are needed. Keep the
directories together; no npm build or new frontend dependency is required.

## Identity

Follow [SETUP.md](../SETUP.md) and open `http://127.0.0.1:8000/m4`.
M4 has no login/logout. Enabled local/testing preview uses the configured seeded
identity; production cannot enable it. Existing main-system identity takes
precedence. Disable preview during integration and retain policies/CSRF.

## Connected controls

- Search: `/hr/employees/search`, 275 ms debounce, cancellation, ten-result limit,
  keyboard selection and text-safe rendering.
- Personal/selected calendar: the combined `/calendar/employees/{employee}/month`
  response supplies days, events, summaries, balances and payday. Authorization
  precedes reads; stale responses are ignored.
- Month/week view, Previous/Next and picker. Team month controls synchronize with
  the employee month. Month summaries/PDF do not change to week totals.
- Day popup: shift, punches, worked hours, late/approved/pending OT, leave decision,
  holiday name, remarks, corrections and report state. Submitted reports open the
  authorized viewer; required missing reports say No report.
- Team matrix: permitted employee rows and daily statuses, secondary statuses,
  overlap markers, pending OT, company-event markers and department filtering.
  Selecting a row/day reloads the authorized individual feed.
- Today's absent/late/on-leave lists remain independent of the selected month.
  Pending employee/date indicators are read-only, not distinct request counts.
- HR events: create/edit/delete through `/hr/events` with CSRF; stored dates are
  inclusive, feed dates exclusive. Events mark every affected day.
- Company events load independently from `/calendar/events`, including for HR
  accounts without linked employee profiles and when employee reads fail.
- Refresh data retries the feeds. Failed event reads show unavailable, distinct
  from an empty month. Event forms/deletes prevent repeated submissions and show
  progress, validation and success messages. HTML session-expiry responses and
  network/server failures have readable recovery instructions.
- Export PDF: authorized employee/month download with readable server errors.
  Direct HTML preview retains browser printing.

The server owns identity. Connected mode removes the role preview and hides MIS
charts/request actions. Source-backed leave/payday widgets replace static values.
Unavailable source context is shown as unavailable; errors never substitute mocks.
Navigation for unavailable modules is disabled until those modules are connected.
Connected mode omits the unused Chart.js CDN script; direct HTML preview retains it.

## Reference dashboard and host navigation

The connected dashboard uses `m4-dashboard-shell.js` and `m4-reference.css` to
match the supplied employee reference: original Flow Asia logo, black 240px
sidebar, 24px main spacing, greeting/server date, compact calendar, inline day
details, today's attendance and leave/payday cards, and five monthly totals below
the calendar. The existing calendar APIs, policies, team inspection, event CRUD,
report viewer and PDF remain in use. Additional indicators are available under
**More indicators**; no data is hidden from day details. Missing days are never
inferred as present, absent, rest days or holidays.

Today's card reads the signed-in employee's current month independently of the
selected/inspected calendar. All dates come from M4's configured timezone.
Summary values use the same source counts as the existing API/PDF, including
simultaneous statuses; the overtime card counts days, while the separate cutoff
metric shows approved hours. Sample Maria/attendance/balance values are removed
from the server HTML before loading. The static HTML preview remains separate.

The sidebar follows the nine reference employee items. My Dashboard and My
Attendance open the existing calendar; HR/supervisors also have Team Calendar.
The other reference screens are unavailable locally, including My Profile editing.
Their client-only prototype functions are not treated as backend implementations.
The calendar still shows the authenticated name and safe source profile position.

The host may add these optional entries to its existing `config/module4.php`:

```php
'navigation' => [
    'employee' => [
        'punch' => 'your.existing.attendance.page',
        'leave' => 'your.existing.leave.page',
        'overtime' => 'your.existing.overtime.page',
        'payslips' => 'your.existing.payslips.page',
        'announcements' => 'your.existing.announcements.page',
        'notifications' => 'your.existing.notifications.page',
        'profile' => 'your.existing.profile.page',
    ],
    // Configure other normalized roles independently, using their actual routes.
],
'logout_route' => 'your.existing.post.logout.route',
```

These are illustrative route names, not shipped routes. Use existing named GET
page routes with no required parameters. Only entries for the authenticated
normalized role are emitted; missing/non-GET targets stay unavailable. Links must
be same-origin. A leave page enables Request leave; an attendance page enables
Time In/Out as a handoff to that page. M4 creates no QR or attendance record.
The host must authorize every target route on the backend; this map is not an
access-control rule. The host owns all administration screens beyond M4's scoped
calendar/team/events/report/PDF tools. Do not infer new role permissions from the
reference's single HR demo role.

Sign out is a normal POST form with the existing CSRF token to a configured host
POST route. It is disabled in the standalone preview; no fake local sign-out or
replacement authentication is introduced. Live host session termination remains
unverified until M1 is available.

The reference's employee management, organization/recruitment/contracts,
attendance capture/scanner/manual records/corrections, leave/OT approvals,
payroll/contributions/loans, MIS, announcements/notifications, profile editing,
users/roles/audit/settings require the corresponding owners' code and contracts.
The HTML uses in-memory records, toast-only actions, a demo signing secret, and
simulated device/location checks. None is copied into the connected dashboard.
QR expiration/replay, atomic punches, selfies, contribution calculations and
approval writes cannot be implemented or verified safely from M4's read contracts.

## Verification and integration

Run `node --test ../flow-asia-dashboard-prototype/tests/*.test.mjs` from the
backend, and `php artisan test --compact`. Browser checks cover employee/HR/
supervisor boundaries, team filtering, Invalid/report details, month/week
boundaries, events, PDF and responsive dark/light views. See SETUP.md for totals.

Local adapters supply deterministic demo data. The M2/M3/M5/M6/M12 source mapping,
main session integration and final shared MySQL/layout checks remain pending.

`tests/dashboard-shell.browser.cjs` adds employee dashboard checks against a
running `employee@test.com` standalone preview. It checks real API totals,
permissions, month/week selection, independent today, selected-day details,
keyboard focus, unavailable actions, 1920/1024/768/390px overflow and light mode.
It injects a failed response to test retry and synthetic navigation configuration
to verify same-origin links and POST/CSRF handoff only; it does not verify the
absent host modules or actual host logout. Use the same Playwright environment
variables as the browser regression below; its default port is 8035.

Screen/PDF counts include each primary/secondary status once per recorded day.
Overlapping categories can exceed recorded-day totals; missing days are unrecorded.
Rejected punches contribute no worked hours, lateness or approved overtime to the
employee, team or PDF presentation; M4 does not alter the source attendance row.

## Browser regression

`../flow-asia-dashboard-prototype/tests/dashboard.browser.cjs` exercises the seeded
HR dashboard against a running local backend. It creates uniquely named test
events and deletes them, checks persistence after reload, and injects HTTP failures
and an unlinked frontend identity to check recovery. Run against the isolated demo
database, with Playwright available through `PLAYWRIGHT_MODULE` (an absolute module
path or the default installed `playwright`). `M4_BASE_URL` defaults to
`http://127.0.0.1:8000`; `M4_BROWSER_PATH` optionally selects a browser executable.
It assumes `hr@test.com` preview identity and the 40-employee seed.

```powershell
node ..\flow-asia-dashboard-prototype\tests\dashboard.browser.cjs
```

## Assemble M4 inside the shared Laravel application

M4's PHP namespace is `FlowAsia\Module4`; its provider is
`FlowAsia\Module4\Providers\Module4ServiceProvider`. It does not replace the host's
`App` namespace, user model, authentication provider, session configuration or
root dashboard. Views use `m4::`; module display configuration uses
`module4_calendar`. Only the standalone shell registers its `/` redirect.

1. Keep this backend and `flow-asia-dashboard-prototype` together, for example
   inside `modules/m04-calendar/` in the shared repository. The host needs the
   compatible Laravel/PHP and DomPDF versions in this module's lockfile.
2. Add this PSR-4 entry to the **host's** existing `composer.json` autoload map:

   ```json
   "FlowAsia\\Module4\\": "modules/m04-calendar/flow-asia-backend/app/"
   ```

3. Add `FlowAsia\Module4\Providers\Module4ServiceProvider::class` to the host's
   existing `bootstrap/providers.php` list. Keep its other providers. Do not copy
   M4's `bootstrap/app.php`, `config/auth.php`, `.env`, factories or seeders into
   the host. The provider registers M4 routes, views, policies, read-only middleware
   and diagnostic command itself.
4. Configure the **host's** environment:

   ```dotenv
   M4_MODE=shared
   M4_STANDALONE_PREVIEW=false
   M4_ROUTE_PREFIX=modules/m4
   M4_ROUTE_NAME_PREFIX=m4.
   M4_AUTH_MIDDLEWARE=auth:web
   M4_EMPLOYEE_DIRECTORY='App\Modules\M4\Adapters\EmployeeDirectory'
   M4_ATTENDANCE_SOURCE='App\Modules\M4\Adapters\AttendanceSource'
   M4_CONTEXT_SOURCE='App\Modules\M4\Adapters\ContextSource'
   M4_EVENT_SOURCE='App\Modules\M4\Adapters\EventStore'
   ```

   These adapter names are examples for the integration team to implement against
   the owners' actual models. They are not supplied implementations. Alternatively
   bind each contract to a host implementation in a service provider; explicit
   container bindings are respected. Shared mode refuses the bundled demo sources
   and ignores preview identity even if its flag is accidentally enabled.
5. Map the authenticated M1 identity in the host's optional `config/module4.php`.
   The default fields are `name`, `role`, `employee_id`. For other field names:

   ```php
   return [
       'identity' => [
           'fields' => ['name' => 'display_name', 'role' => 'access_role', 'employee_id' => 'staff_id'],
           'roles' => ['TeamLead' => 'supervisor', 'HR' => 'hr_admin'],
       ],
   ];
   ```

   Complex role relations can implement `CalendarIdentitySource` and set
   `M4_IDENTITY_SOURCE` or bind that interface. Any Laravel `Authenticatable` model
   is supported; no inheritance from M4's demo User is required. Employee and
   supervisor IDs must be M2 employee IDs, not M1 user IDs. Unknown roles fail
   permission checks. The host owns login/logout and the existing session/CSRF flow.
6. Run `composer dump-autoload`, clear stale config/routes using the host's normal
   workflow, then `php artisan m4:check` and
   `php artisan route:list --path=modules/m4`. Add the named `m4.dashboard` route to
   the host navigation. The dashboard is `/modules/m4`; feeds and event management
   are under the same prefix and their names start with `m4.`.

Do not run the module's demo migrations or seeds on the shared database. They live
under `database/migrations/standalone` and the provider loads them **only** in
standalone mode. Shared mode registers no schema migrations, including user,
employee, attendance, session or event tables. Shared event storage is supplied
through the event adapter and the schema owner's migrations.

## Connection contract for the module teams

All interfaces below are in `FlowAsia\Module4\Contracts`; the PHP interfaces and
`CalendarEmployee` constructor define the callable boundary. Configuration is in
`module4.sources`; no edits to controllers or presenters are needed to swap sources.

| Owner/input | Interface | Required behavior |
| --- | --- | --- |
| M1 session identity | `CalendarIdentitySource` | Normalize authenticated user name, role and employee ID; no client-supplied identity |
| M2 employees/org | `CalendarEmployeeDirectory` | Return safe `CalendarEmployee` values for lookup, scoped search/team lists and department existence |
| M3 attendance + M2 holidays + M5 leave + M6 shifts/OT | `CalendarDataSource` | One row per employee/date, combined primary/secondary states and optional metadata; batched range reads |
| M5 balances + M7 pay periods + M12 reports | `CalendarContextSource` | Batch supplied balance/payday/report references; retrieve report text only for the matching employee/report |
| Company event storage owned by M4/schema team | `CompanyEventSource` | Inclusive range lookup, paginated list, create/update/delete and exclusive FullCalendar feed ends |

Dates are local calendar dates in `module4.timezone` (default Asia/Manila), with
inclusive adapter bounds. Employee IDs and report/event IDs are canonical positive
integers. Attendance `date` values are Carbon dates; punches are nullable time
strings, lateness is integer **minutes**, approved/pending OT is numeric **hours**,
and `calendar_details` is an array. Convert upstream overtime minutes to hours in
the adapter. Missing attendance stays missing. Shared failures throw; empty data
must not disguise a failed dependency. See [the employee source examples](employee-calendar.md)
for the metadata/context shapes and the interface PHPDoc for required row fields.

Search and team lists must be server-scoped; M4 also runs its policy against each
returned employee. Selected calendars, reports and PDFs are authorized before
attendance/context reads. Events are the only writes exposed by this module;
the server supplies the authenticated user identifier to the event store. M4 never
writes attendance, leave ledgers, overtime approvals, payroll or progress reports.

`m4:check` validates adapter resolution and shared-mode safeguards without reading
business records. It returns a failure exit code for missing/invalid adapters or
demo configuration. A passing result verifies wiring, not external data accuracy.
The shared-mode regression uses a different authenticated user model, no demo
tables and zero business SQL queries while exercising calendar, team, report,
PDF and event operations. Actual upstream/MySQL acceptance remains a group task.

## Refresh after another module saves a record

M4 reads the owning modules through the five contracts above on each request.
It does not maintain a second attendance/leave/OT ledger or require a data-copy
job. Once the host adapters are bound, subsequent employee, team and PDF reads
use the current upstream records and retain the existing server-side policies.

The connected calendar now refreshes when the user returns to its tab/window.
When the host runs another module in the same page, it can also request refresh
**after its backend confirms a successful save/approval**:

```javascript
window.dispatchEvent(new Event('flowasia:calendar-updated'));
```

No payload is accepted: employee IDs, roles, records and totals still come from
the authenticated APIs. Multiple signals are coalesced. Hidden tabs wait until
visible, and open dialogs/forms, active inputs, downloads and in-flight reads
delay refresh so user work is preserved. Updates during a read queue one further
read. Existing loading/error/retry handling remains in use; a refresh failure
does not create a success message or an endless network retry loop. This is a
refresh hook, not a WebSocket service or a cross-origin event protocol.

`tests/Unit/ModuleIntegrationTest.php` verifies changed upstream attendance,
approved leave/balances, OT/cutoff, payday and report references propagate to
employee/team/PDF reads using an external user model, no demo tables and zero
business SQL. Node tests cover refresh scheduling, form protection and failures;
the employee browser test verifies the refresh signal using a simulated changed
response without touching existing database records. These checks verify the M4
boundary; the actual peer-module adapters and shared MySQL remain a group task.

## Schema mapping to complete during group assembly

The supplied ERD is an image-based design, not the installed shared database or
source implementation. For example, it names attendance fields `attendance_date`,
`clock_in`, `overtime_minutes` and `attendance_daily_id`; local stub storage uses
`date`, `time_in`, `ot_hours` and `id`. The company-event ERD uses
`company_event_id`, `event_title`, `start_date`, `end_datetime`, `is_all_day` and
`status`, while local CRUD intentionally uses the documented all-day M4 contract.
Mapping these requires the actual shared migrations/models and agreed status,
date/time and approval contracts, including the later CR1/CR3 fields.

M1 session/login/role implementation and M2/M3/M5/M6/M7/M12 read sources are absent
from this checkout. Implement the adapters above when the owners' code is joined;
the module itself no longer requires their local table or user-model shape.
Shared MySQL behavior, live
authentication and external notifications/uploads/billing remain unverified;
those write workflows belong to their respective modules.
