# Dashboard connection guide

Laravel serves the sibling HTML/CSS/JavaScript frontend at `/m4` and constrains
`/m4/assets/...` to code assets. It injects session identity and CSRF. Keep the
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
- Export PDF: authorized employee/month download with readable server errors.
  Direct HTML preview retains browser printing.

The server owns identity. Connected mode removes the role preview and hides MIS
charts/request actions. Source-backed leave/payday widgets replace static values.
Unavailable source context is shown as unavailable; errors never substitute mocks.

## Verification and integration

Run `node --test ../flow-asia-dashboard-prototype/tests/*.test.mjs` from the
backend, and `php artisan test --compact`. Browser checks cover employee/HR/
supervisor boundaries, team filtering, Invalid/report details, month/week
boundaries, events, PDF and responsive dark/light views. See SETUP.md for totals.

Local adapters supply deterministic demo data. The M2/M3/M5/M6/M12 source mapping,
main session integration and final shared MySQL/layout checks remain pending.

Screen/PDF counts include each primary/secondary status once per recorded day.
Overlapping categories can exceed recorded-day totals; missing days are unrecorded.
