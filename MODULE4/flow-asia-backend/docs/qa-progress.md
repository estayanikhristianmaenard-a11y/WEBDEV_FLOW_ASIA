# M4 QA task and progress tracker

Baseline date: **14 October 2026**. Scope: Module 4 work for members #09, #10
and #11. This tracker covers the employee calendar, HR/supervisor tools and their
integration with the group's system.

## Your QA responsibility

Verify that calendars show the supplied records accurately, roles cannot access
unauthorized employees, events/reports/PDFs work, and the UI remains usable on
mobile. Record reproducible defects, retest fixes, and sign off the shared build
after its external data and authentication are connected.

| Owner | Features you verify |
| --- | --- |
| #09 | Combined feed, status priority, rejected punches and query performance |
| #10 | Employee calendar, navigation, popup, summaries, widgets, reports and mobile layout |
| #11 | HR search/inspection, supervisor scope, team calendar, events and PDF export |
| All | Shared data/authentication, regression checks and final demonstration |

## Current system progress

| Measure | Latest recorded result | Meaning |
| --- | --- | --- |
| Laravel feature/unit tests | 102 passed; 423 assertions | Existing automated checks passed against isolated SQLite |
| JavaScript tests | 13 passed | Transport and team-calendar regressions passed |
| Browser checks | Passed | HR/employee/supervisor flows, month/week navigation, Invalid/reports, events, PDF, mobile and themes |
| PDF | Passed | Actual landscape export generated, parsed and reviewed |
| Local demo readiness | About 95% | Planning estimate for the standalone build |
| Overall M4 completion | About 75% | Planning estimate including unfinished shared integration |

Percentages are estimates against the project scope, not code coverage. A passing
suite does not replace your independent QA sign-off. See [SETUP.md](../SETUP.md)
for the baseline evidence and [project requirements](../../README.md).

## Start your QA run

1. Open `http://127.0.0.1:8000/m4`. If the server is stopped, run
   `./local.ps1 serve` from `MODULE4/flow-asia-backend`.
2. Use **October 2026** for the seeded scenarios below. Test with each identity:

| Preview email | Role | Expected scope |
| --- | --- | --- |
| `hr@test.com` | HR administrator | All 40 seeded employees; event management |
| `supervisor@test.com` | Supervisor | Self and seven direct reports; no event management |
| `employee@test.com` | Employee | Own calendar only; no HR tools |

3. Select the identity through `M4_PREVIEW_USER_EMAIL` in the ignored local
   backend `.env`, then reload the dashboard. There is no M4 login/logout or
   browser role switch. Keep `APP_ENV=local` and preview enabled for demo tests.
   If the server was started with `--no-reload`, restart it after changing `.env`.
4. Capture the build/commit, role, date/month, browser and evidence for your run.
   Use the configured demo database; leave the group's database untouched.

| Run detail | Your entry |
| --- | --- |
| Tester/date | |
| Build or commit | |
| Browser/device | |
| Dataset/environment | |
| Evidence location | |

## Manual acceptance checklist: 24 cases

Change `[ ]` to `[x]` only after you have independently passed that case. Keep
failed, blocked and untested cases unchecked and record their disposition below.
The initial manual checklist awaits your sign-off; the baseline above records
the completed automated/browser checks.

### Employee calendar and data

- [ ] **QA-01 — Opening:** employee preview immediately shows its own calendar and identity.
- [ ] **QA-02 — Navigation:** month picker, Month/Week and Previous/Next work, including September/October boundaries; summaries and PDF remain monthly.
- [ ] **QA-03 — Details:** EMP-002 on 1 October shows actual punches, the supplied shift, 15 late minutes and 7.75 worked hours; leave/holiday/correction details appear where supplied.
- [ ] **QA-04 — Summaries:** EMP-002 in October has 3 Present, 4 Late, 4 Absent, 4 approved leave days, 60 late minutes and 4 approved OT hours for the 1–15 October cutoff. Counts match the feed and never infer missing records.
- [ ] **QA-05 — Widgets:** EMP-002 shows the seeded Vacation/Sick balances of 8/10 days and next supplied payday of 20 October, when testing against the 14 October baseline. Unavailable source context is clearly identified.

### HR and team tools

- [ ] **QA-06 — Search:** name, employee number and department queries work; keyboard selection works; results are capped at ten and stale searches do not replace newer selections.
- [ ] **QA-07 — Inspection:** selecting an employee updates identity, month details, summaries and PDF selection; Clear inspection returns to the signed-in employee.
- [ ] **QA-08 — Team filtering:** HR sees 40 seeded employees; Creative filtering shows eight. Team and employee month controls remain synchronized.
- [ ] **QA-09 — Leave overlap:** Creative shows EMP-002/EMP-003 overlapping approved leave on 12 October. Today's lists follow Manila today even while another month is selected.
- [ ] **QA-10 — Pending indicators:** EMP-002 has a 2-hour pending OT marker on 16 October; pending leave/OT remain informational and have no approval controls.

### Invalid punches and reports

- [ ] **QA-11 — F075:** EMP-002 on 13 October shows Invalid and the rejection reason, without Present/Late counts. On 12 October, Holiday is primary and Approved Leave remains visible as a secondary status. The automated aggregation suite also covers Holiday/Approved Leave taking priority over Invalid.
- [ ] **QA-12 — F076 submitted:** EMP-002 on 10 October shows Report submitted; its link opens the correct read-only report. HR and the permitted supervisor see the same report.
- [ ] **QA-13 — F076 missing:** EMP-002 on 1 October shows No report for the required shift; not-required and unavailable states remain distinguishable when supplied.
- [ ] **QA-14 — Report ownership:** an unauthorized employee cannot open another employee's report. Changing a report URL's employee to a different authorized employee does not expose the original report.

### Access, events and exports

- [ ] **QA-15 — Employee scope:** employee preview has no HR tools; requests for HR endpoints or another employee's calendar/PDF are denied by the server.
- [ ] **QA-16 — Supervisor scope:** supervisor preview shows only self/direct reports; another department's employee is denied and event management is unavailable.
- [ ] **QA-17 — Event CRUD:** HR creates, edits and deletes a uniquely named QA event. End-before-start is rejected; a multi-day event covers its inclusive last date and appears on permitted calendars. Delete the QA event afterward.
- [ ] **QA-18 — PDF download:** the selected employee/month exports a readable landscape document with identity, events, legend and generated-by footer.
- [ ] **QA-19 — Mobile/themes:** at about 390 px wide, controls and day details remain usable, status codes have a legend, the team matrix scrolls within its card, and dark/light themes remain readable.
- [ ] **QA-20 — Read-only writes:** attendance, leave, OT, calendar and report writes are rejected; viewing or exporting does not alter source records.
- [ ] **QA-21 — CSRF:** an HR event write without a valid CSRF token is rejected. A valid HR write succeeds; a non-HR write with a valid token is still forbidden.
- [ ] **QA-22 — Privacy:** browser Network responses and exports contain no salary, statutory IDs, bank details, credentials or unexpected private metadata.
- [ ] **QA-23 — Missing records:** EMP-040 has no attendance; EMP-039 has no records before 15 October. Neither becomes Present or Absent automatically.
- [ ] **QA-24 — Consistency/errors:** screen/feed/PDF statuses and summary totals agree, including secondary statuses. Failed requests show an error/unavailable state without mock substitution; there are no JavaScript exceptions during the tested flows.

## Track your manual progress

| Outcome | Number of cases |
| --- | --- |
| Passed | 0 |
| Failed | 0 |
| Blocked | 0 |
| Not run | 24 |
| Total | 24 |

Update these counts as you work. **Manual QA pass progress = Passed ÷ 24 × 100.**
The four outcome counts must add up to 24. Record each failure/blocker separately
so an unchecked case can be distinguished from a case you have not run.

## Defects and blocked cases

Use severity: Critical (unauthorized data/source mutation), High (major flow or
incorrect calendar result), Medium (partial behavior/usability), Low (minor copy
or visual issue).

| Case/defect ID | Outcome/severity | Role and steps to reproduce | Expected vs actual | Evidence | Owner/status | Retest |
| --- | --- | --- | --- | --- | --- | --- |
| | | | | | | |

A defect record should include the employee/month, input or URL, browser/device,
response/error, and whether it repeats after reload. Retest the original case and
the nearby affected behavior before closing it.

## Shared-system release gates

These gates are pending until the other module owners supply their contracts
and the complete build can be tested. Do not sign them off against seeded data.

- [ ] **INT-01:** main-system session works with preview disabled; employee/supervisor/HR permissions are retained.
- [ ] **INT-02:** real M2/M3 attendance, holidays, rejected punches and corrections match the calendar.
- [ ] **INT-03:** real M5/M6 leave, balances, approved/pending OT and source decisions match the calendar and team indicators.
- [ ] **INT-04:** supplied payroll cutoffs and next payday are correct, including month boundaries.
- [ ] **INT-05:** actual M12 report states/links open the authorized employee's report.
- [ ] **INT-06:** shared MySQL queries are timed against representative data and the complete group demonstration/regression run passes.

Approve the standalone demo after its applicable acceptance cases pass and open
defects are reviewed. Approve the shared release only after all six integration
gates pass and Critical/High defects are resolved.

## Repeat automated checks

From `MODULE4/flow-asia-backend`:

```powershell
.\local.ps1 test
node --test ..\flow-asia-dashboard-prototype\tests\*.test.mjs
```

Record the new totals and build/date for each run. Keep screenshots and PDFs with
the case evidence; project review artifacts currently live under ignored
`.tools/review`.
