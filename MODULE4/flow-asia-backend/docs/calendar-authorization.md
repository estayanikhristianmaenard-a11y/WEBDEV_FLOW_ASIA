# Calendar authorization and read-only integration

Source is verified locally: all 89 Laravel tests passed on PHP 8.4 with installed Composer dependencies. This step adds authorization infrastructure without implementing #09's calendar API or changing the frontend.

`EmployeeCalendarPolicy::viewCalendar(User, Employee)` is registered for `Employee` in `AppServiceProvider`. HR roles can view anyone; employees can view their linked employee record; supervisors can view themselves and direct reports whose `supervisor_id` matches their employee ID (not user ID). Missing employee links and unknown roles are denied. Payroll Officers have no calendar permission in the agreed scope.

Apply `auth` and `can:viewCalendar,employee` to every future employee-calendar page, feed and export route with a model-bound `{employee}` parameter. Alternatively, call `Gate::authorize('viewCalendar', $employee)` before reading data. `Employee::visibleTo($user)` scopes lists/search but does not replace authorization for a selected record. Future team endpoints must reject requested IDs outside the permitted set before calling the data source.

`ReadOnlyCalendar` runs as global middleware before route matching, CSRF and controller execution. Paths configured in `calendar.read_only_paths` reject methods other than GET, HEAD and OPTIONS with 403 before CSRF or controller execution. Protected families are `/calendar`, `/calendar/*`, `/hr/calendar`, `/hr/calendar/*`, `/hr/employees/{id}/calendar` and its children. Use these families or update configuration for new calendar routes. Read methods must not mutate data. The guard also rejects writes to `/calendar/events`, which is a feed.

The policy denies `updateAttendance`, `updateLeave` and `updateOvertime` for all roles, including Super Admin. There is no blanket Super Admin bypass. These are M4 abilities; workflows in other modules must use their own policies/routes.

HR event writes remain under `/hr/events`, protected by authentication, the `manage-events` Gate and CSRF. The guard does not restrict other modules' attendance, leave or OT workflows.

Run `php artisan test --filter=CalendarAuthorization` when dependencies are ready, then run the full feature suite. Tests cover HR/employee/supervisor access, team boundaries, missing identities, unknown roles, authentication, denied mutation abilities, rejected write methods before handlers execute and preserved HR event creation. Policy-isolation routes remain test-only; production individual-calendar, team and PDF routes are now available.

Individual calendar, team and PDF routes now enforce policy checks. Module login/logout is removed; direct local preview is verified; shared-system integration remains pending. CR1 Invalid status and CR3 report metadata need coordination with #09/#10 based on the Change Request PDF.
