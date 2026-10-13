# Employee search integration

`GET /hr/employees/search?q=...` is a session-authenticated JSON endpoint. Send `Accept: application/json`. The `/m4` dashboard opens directly using local preview or shared-system identity; standalone runtime verification passed on 12 October 2026. See [dashboard integration](dashboard-integration.md).

HR roles (`hr_admin`, `hr_staff`, `super_admin`) can search all employees. Supervisors can search themselves and direct reports, based on their linked employee ID. Employees and unknown roles receive 403; unauthenticated JSON requests receive 401. Supervisors without employee links receive no results.

The trimmed query requires 2-100 characters. Search matches name, employee number or department, case-insensitively. Multiple words must all match; `%` and `_` are literal characters, not wildcard shortcuts. Invalid queries return 422. Results are ordered by last name, first name and ID, capped at 10, with no pagination.

```json
{
  "data": [
    {
      "id": 2,
      "employee_no": "EMP-002",
      "name": "Demo Employee 02",
      "position": "Team Member",
      "department": "Creative"
    }
  ]
}
```

No matches return `{"data": []}`. The numeric `id` selects the authorized individual month endpoint; the team response supplies the multi-employee matrix and indicators. No salary, bank, statutory identifiers or account credentials are returned.

The connected frontend uses 275 ms debounce, requires two characters, cancels stale requests, supports keyboard navigation and renders result text safely. Team selection clears pending searches before loading the employee.

After setup with PHP 8.4+ and the locked dependencies, run `php artisan test --filter=EmployeeSearch`. Tests cover queries, result limits, safe response fields, role scope, missing employee links, invalid input and unchanged records. The final shared MySQL source/schema still needs integration verification.
