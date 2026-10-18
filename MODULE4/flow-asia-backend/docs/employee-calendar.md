# Combined employee calendar

`GET /calendar/employees/{employee}/month?month=2026-10` requires session
authentication and `viewCalendar` before any source read. Employees read self;
supervisors self/direct reports; HR any employee. Invalid months return 422,
denied employees 403, missing employees 404 and unauthenticated JSON requests 401.
Responses are private/no-store; calendar writes are forbidden.

The response includes `month`, `read_only: true` and safe employee `data`:

- Identity, department and recorded `days`; unrecorded dates remain absent.
- `company_events` in FullCalendar-compatible shape with exclusive end dates.
- `summary`: status counts, recorded/missing days, total late minutes, approved
  month/cutoff OT and approved leave duration.
- Source-supplied `leave_balances`, `next_payday` and `cutoff`.
- `range` with the month's start and exclusive end.

Days include primary/secondary statuses, punches, late/OT, shift, worked minutes/
hours, leave type/decision, holiday name, remarks, correction metadata and report
state/link. Nested metadata is whitelisted. Bodies and sensitive fields are not
included in the feed.

`CalendarMonthService` combines `CalendarDataSource`,
`CalendarContextSource` and company events. `CalendarDayPresenter` is shared with
team/PDF views. Priority is Holiday > Approved Leave > Invalid > Absent > Late,
then the lower configured statuses. Rejected punches become Invalid without
source writes or Present/Late counts; other simultaneous statuses are preserved.

Worked hours use authoritative worked minutes, or complete punches and a supplied
break. Overnight calculation requires an explicit overnight shift. Missing
metadata and rejected punches have no computed hours. Approved cutoff OT includes
the complete source-supplied period, even across month boundaries.

Reports are `submitted` with a protected viewer link, `missing` when the shift
requires one, `not_required`, or `unavailable`. The viewer rechecks calendar access
and employee ownership, escapes report text, and cannot mutate reports.

The dashboard uses this combined response for personal/selected-employee
calendars, summaries, balances and payday. Company events also load independently
so they remain available when an employee source fails. It ignores stale selections.
Month/week controls change the presentation; summaries and PDF remain monthly.
Team indicators use the batched endpoint. HR gets the same detail/report behavior.

Run `php artisan test --filter=CalendarAggregation` and
`php artisan test --filter=EmployeeCalendar` after setup. Shared
M2/M3/M5/M6/M12 adapters and main-system sessions still require group integration.

## Local source examples

Source rows keep their existing attendance fields and may include this
`calendar_details` snapshot:

```json
{
  "punch_status": "accepted",
  "shift": {
    "starts_at": "09:00:00",
    "ends_at": "18:00:00",
    "break_minutes": 60,
    "overnight": false,
    "report_required": true
  },
  "leave": null,
  "holiday": null,
  "correction": null
}
```

M3 rejected punches use `punch_status: "rejected"`; corrections include `status`
and `reason`. Leave metadata uses `type`, `approval_status` and optional `days`;
holidays use `name`. Cancelled/rejected leave is removed from status membership;
a row with no remaining status shows No attendance status without inventing
presence/absence. Unknown source context stays unavailable.

The context adapter returns employee-keyed `profile` and date-keyed `reports`.
Profiles contain `leave_balances: [{type, remaining_days}]` and
`pay_periods: [{start, end, payday}]`. Submitted report references contain `id`
and `submitted_at`; their bodies are retrieved only by the ownership-scoped
report method. Replace these snapshots with agreed shared records, preserving
the same safe output and access checks.
