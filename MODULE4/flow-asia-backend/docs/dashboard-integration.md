# Dashboard connection guide

The existing prototype is served through Laravel at `/m4`, using its original
HTML, CSS and JavaScript files. A constrained `/m4/assets/...` route serves CSS
and JS from the sibling prototype directory. Keep both directories together.
Laravel injects a same-origin asset base, session user configuration and CSRF
token. No files are copied and no npm build or new package is required.

## Open the standalone module

Follow `SETUP.md`, then open `http://127.0.0.1:8000/m4` directly.
There is no M4 login/logout. In local preview mode, the configured seeded identity
(`M4_PREVIEW_USER_EMAIL=hr@test.com` by default) is provided to M4 requests.
This requires `M4_STANDALONE_PREVIEW=true` and local/testing environment; production
cannot activate it. An existing main-system user always takes precedence.
Disable preview mode when integrating and retain the main system's session.
CSRF protection and M4 policies remain enforced.

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
- User identity comes from the server. The preview role switch is removed in backend mode.
  The server continues enforcing all access rules, regardless of browser state.

Original colors and dashboard components are reused. Backend mode hides the
unconnected MIS charts, static leave balances and personal request actions, and
replaces mock HR totals with permitted-roster indicators. Standalone `index.html`
remains a mock preview; open `/m4` for the connected code path. Errors never
silently substitute mock records. There is no browser storage of auth tokens.

## Current limits and verification

Staff can view their own attendance and company events. HR and supervisors
can inspect employees permitted by the calendar policy. See [individual calendar](employee-calendar.md). Stored punch times and remarks are connected to the individual day popup. Shift times, worked hours and report links still require the shared #09/#10 feed and are shown as unavailable. The calendar preserves primary and
additional statuses; final #09 aggregation/priority integration is pending.
Export PDF uses the authorized server endpoint for the current employee/month; see [PDF export](pdf-export.md). Pending daily
indicators are not counts of distinct request IDs.

Seven Node transport tests pass (`node --test
../flow-asia-dashboard-prototype/tests/api.test.mjs`, from the backend folder).
PHP/JS syntax checks, all 89 Laravel tests, MySQL migrations/seeding and browser checks passed on 12 October 2026.
Run `php artisan test` when the environment is ready, including
`DashboardIntegrationTest`, then check these flows in a browser:

1. Open the local HR preview; search by name/ID/department and select with mouse or keyboard.
2. Change month and department; verify employee days, today's date and indicators.
3. Create a multi-day event, edit it, delete it and reload to verify persistence.
4. Test with a supervisor identity; verify only self/direct reports are visible and event
   management is absent. Staff cannot access search/team/event management.
5. Verify preview is disabled in production, shared identity is preserved, and validation messages are shown.

Standalone frontend/backend connections are verified locally. This is not a deployed or group-integrated system.

Screen/PDF summary rule: count each primary or additional status once per day. Overlapping categories may exceed recorded-day totals. No status priority is computed by the presenter.
