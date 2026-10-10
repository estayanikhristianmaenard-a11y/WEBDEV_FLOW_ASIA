# Employee search integration

`GET /hr/employees/search?q=...` is a session-authenticated JSON endpoint. Send `Accept: application/json`. The `/m4` dashboard connection and `/login` session flow are prepared; runtime verification remains pending. See [dashboard integration](dashboard-integration.md).

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

No matches return `{"data": []}`. The numeric `id` selects an employee from the authorized team-calendar response in the connected dashboard. No salary, bank, statutory identifiers or account credentials are returned.

During frontend integration, debounce input by 250-300 ms, do not request fewer than two characters, cancel stale requests, provide keyboard navigation and render result text safely. These frontend changes are not part of this backend step.

After PHP 8.3+, Composer and dependencies are available, run `php artisan test --filter=EmployeeSearch`. The prepared tests cover queries, result limits, safe response fields, role scope, missing employee links, invalid input and unchanged records. SQLite tests also need a MySQL runtime check before integration because production uses MySQL.
