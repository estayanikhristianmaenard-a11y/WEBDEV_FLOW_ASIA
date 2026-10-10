# Company Events backend integration

Source is implemented; runtime verification is pending PHP 8.3+, Composer and authentication setup. The frontend has not been modified.

## Endpoints

| Method | Path | Access | Response |
| --- | --- | --- | --- |
| GET | `/hr/events` | HR roles | Paginated records, 20 per page |
| POST | `/hr/events` | HR roles | Created record in `data`, 201 |
| PUT/PATCH | `/hr/events/{id}` | HR roles | Updated record in `data`, 200 |
| DELETE | `/hr/events/{id}` | HR roles | 204 |
| GET | `/calendar/events?start=...&end=...` | Any authenticated user | FullCalendar event array |

Send `Accept: application/json`. These are session-authenticated web routes. Writes require Laravel's CSRF token. The `/login` flow and same-origin `/m4` dashboard connection are now prepared; see [dashboard integration](dashboard-integration.md). The standalone prototype's role toggle is not authentication. Runtime verification remains pending.

POST and updates accept the complete editable record:

```json
{
  "title": "Company Town Hall",
  "type": "town_hall",
  "description": "All employees welcome.",
  "starts_on": "2026-10-12",
  "ends_on": "2026-10-13"
}
```

Titles are required (maximum 150 characters). Types are `shoot`, `premiere`, `town_hall`, `other`. Description is optional (maximum 5000 characters). Stored dates are inclusive, with end on or after start. All events apply company-wide. The server assigns `created_by`; clients cannot assign creators or employee/department targets. No attendance, leave or overtime records are modified.

Feed bounds accept ISO dates/datetimes; the end is exclusive. The feed returns events overlapping the requested calendar dates in Asia/Manila. The returned event end adds one day to the stored inclusive end. Event IDs have a `company-event-` prefix; `extendedProps.eventId` is the numeric ID for management routes. UI code must render user-entered titles/descriptions as text, not raw HTML.

Validation errors return 422; unauthorized JSON requests return 401; forbidden actions return 403; unknown event IDs return 404. Company event feeds do not include creator/account details.

Once the runtime is ready, run `php artisan migrate` and `php artisan test --filter=CompanyEvents`. Use a dedicated demo database. Pest covers HR CRUD, non-HR denials, authentication, invalid create/update data, company-wide feed access, overlapping dates and exclusive end conversion. Tests have been written but have not run yet.
