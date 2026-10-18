# Calendar authorization and read-only integration

Authorization is implemented and verified locally on PHP 8.4. Employee aggregation, team calendars, report viewing and PDF export use the same access rules. See SETUP.md for current suite results.

`EmployeeCalendarPolicy::viewCalendar(Authenticatable, CalendarEmployee)` is registered by `FlowAsia\Module4\Providers\Module4ServiceProvider`. The standalone Employee model is also supported. Identity is normalized by `CalendarIdentitySource`, so a host user model need not inherit from M4's demo User. HR roles can view anyone; employees can view their linked employee record; supervisors can view themselves and direct reports whose `supervisor_id` matches their employee ID (not user ID). Missing employee links and unknown roles are denied. Payroll Officers have no calendar permission in the agreed scope.

M4 routes apply the configured host auth middleware and `can:viewCalendar,calendarEmployee`; their unique `{calendarEmployee}` binding calls the employee-directory interface, leaving the host's `{employee}` binding intact. Alternatively, call `Gate::authorize('viewCalendar', $employee)` before reading data. The directory scopes lists/search but does not replace authorization for a selected record. Team records are individually authorized before source reads.

`ReadOnlyCalendar` is registered globally by the module provider before route matching, CSRF and controller execution. Paths configured in `module4_calendar.read_only_paths` reject methods other than GET, HEAD and OPTIONS with 403 before CSRF or controller execution. Protected families are `/calendar`, `/calendar/*`, `/hr/calendar`, `/hr/calendar/*`, `/hr/employees/{id}/calendar` and its children, under `module4.route_prefix` when set. Shared mode therefore protects `/modules/m4/calendar/*` while leaving the owning modules' root routes untouched. Read methods must not mutate data. The guard also rejects writes to the company-event feed.

The policy denies `updateAttendance`, `updateLeave` and `updateOvertime` for all roles, including Super Admin. There is no blanket Super Admin bypass. These are M4 abilities; workflows in other modules must use their own policies/routes.

HR event writes remain under `/hr/events` (prefixed in shared mode), protected by authentication, the `m4.manage-events` Gate and CSRF. The Gate name does not replace a host `manage-events` ability. The guard does not restrict other modules' attendance, leave or OT workflows.

Run `php artisan test --filter=CalendarAuthorization` when dependencies are ready, then run the full feature suite. Tests cover HR/employee/supervisor access, team boundaries, missing identities, unknown roles, authentication, denied mutation abilities, rejected write methods before handlers execute and preserved HR event creation. Policy-isolation routes remain test-only; production individual-calendar, team and PDF routes are now available.

Individual calendar, team, report and PDF routes enforce policy checks. Report retrieval additionally requires that the report belongs to the selected employee, escapes text and sends private/no-store responses. Module login/logout is absent; local preview is verified. F075 Invalid and F076 report states are implemented against local adapters; main-system authentication and M3/M12 mapping still require integration.
