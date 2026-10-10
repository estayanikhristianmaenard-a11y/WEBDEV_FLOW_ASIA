# Dashboard connection guide

The existing prototype is served through Laravel at `/m4`, using its original
HTML, CSS and JavaScript files. A constrained `/m4/assets/...` route serves CSS
and JS from the sibling prototype directory. Keep both directories together.
Laravel injects a same-origin asset base, session user configuration and CSRF
token. No files are copied and no npm build or new package is required.

## Run and sign in

Follow `SETUP.md`: install dependencies with PHP 8.3+, configure the isolated
database, generate the app key, migrate and seed, then `php artisan serve`.
Open `http://127.0.0.1:8000/m4`; unauthenticated visitors go to `/login`.
Local demo credentials are `hr@test.com`, `supervisor@test.com` or
`employee@test.com`, with password `password`. These accounts are local/testing
fixtures. Login uses Laravel session authentication, CSRF protection, five
attempts per minute, and session regeneration; logout invalidates the session.
Production identity integration remains a separate task with the shared team.

## Connected controls

- Search uses `/hr/employees/search`, 275 ms debounce, cancellation, a maximum
  of ten results, arrow navigation, Enter selection and Escape dismissal.
- Selected employee days use `/calendar/employees/{employee}/month`. The
  server authorizes each employee before reading. Month changes reload the
  individual calendar, team indicators and company-event feed. Missing days stay unrecorded.
- Department filters operate on the authorized batch already loaded; supervisors
  cannot obtain other employees through the filter. Today remains Manila today,
  independent of the selected month. Approved overlaps and pending daily leave/OT
  indicators appear without approval buttons.
- Company events use `/calendar/events` for display. HR can create, edit and
  delete through `/hr/events`; writes include the session's CSRF token. Stored
  end dates are inclusive, while feed end dates are exclusive. Events appear on
  every affected day without replacing attendance labels.
- User identity comes from the server. The preview role switch becomes Sign out.
  The server continues enforcing all access rules, regardless of browser state.

Original colors and dashboard components are reused. Backend mode hides the
unconnected MIS charts, static leave balances and personal request actions, and
replaces mock HR totals with permitted-roster indicators. Standalone `index.html`
remains a mock preview; open `/m4` for the connected code path. Errors never
silently substitute mock records. There is no browser storage of auth tokens.

## Current limits and verification

Staff can view their own attendance and company events. HR and supervisors
can inspect employees permitted by the calendar policy. See [individual calendar](employee-calendar.md). Shift times, worked hours and report links are not supplied by the team
response and are shown as unavailable. The calendar preserves primary and
additional statuses; final #09 aggregation/priority integration is pending.
Export PDF uses the authorized server endpoint for the current employee/month; see [PDF export](pdf-export.md). Pending daily
indicators are not counts of distinct request IDs.

Seven Node transport tests pass (`node --test
../flow-asia-dashboard-prototype/tests/api.test.mjs`, from the backend folder).
PHP/JS syntax checks pass. Laravel feature tests, migrations and browser testing
remain unrun because PHP 8.3+/Composer dependencies are unavailable here.
Run `php artisan test` when the environment is ready, including
`DashboardIntegrationTest`, then check these flows in a browser:

1. Sign in as HR; search by name/ID/department and select with mouse or keyboard.
2. Change month and department; verify employee days, today's date and indicators.
3. Create a multi-day event, edit it, delete it and reload to verify persistence.
4. Sign in as supervisor; verify only self/direct reports are visible and event
   management is absent. Staff cannot access search/team/event management.
5. Verify sign-out, invalid credentials, expired sessions and validation messages.

Code connection is prepared; it is not yet an end-to-end verified deployment.
