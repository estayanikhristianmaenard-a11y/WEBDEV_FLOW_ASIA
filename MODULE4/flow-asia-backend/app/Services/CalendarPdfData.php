<?php

namespace FlowAsia\Module4\Services;

use Carbon\CarbonImmutable;
use FlowAsia\Module4\Contracts\CalendarIdentitySource;
use FlowAsia\Module4\Models\Employee;
use Illuminate\Contracts\Auth\Authenticatable;

class CalendarPdfData
{
    public function __construct(private CalendarMonthService $calendar) {}

    public function build(Employee|CalendarEmployee $employee, Authenticatable $generatedBy, string $month): array
    {
        $employee = $employee instanceof Employee ? CalendarEmployee::fromModel($employee) : $employee;
        $generatedBy = (object) app(CalendarIdentitySource::class)->forUser($generatedBy);
        $from = CarbonImmutable::createFromFormat('!Y-m', $month, config('module4.timezone'))->startOfMonth();
        $calendar = $this->calendar->build($employee, $month);
        $rows = $calendar['days']->keyBy('date');
        $events = $calendar['company_events'];
        $statuses = config('module4_calendar.statuses');
        $statuses['invalid'] ??= config('module4_calendar.invalid_status');
        $statuses['unrecorded'] ??= config('module4_calendar.unrecorded_status');
        $summary = $calendar['summary']['status_counts'];
        $cells = array_fill(0, $from->dayOfWeek, null);
        for ($day = 1; $day <= $from->daysInMonth; $day++) {
            $date = $from->addDays($day - 1);
            $row = $rows->get($date->toDateString());
            $codes = $row ? array_map(fn ($status) => $statuses[$status]['code'] ?? $status,
                array_unique([$row['status'], ...$row['additional_statuses']])) : [];
            $cells[] = [
                'day' => $day, 'codes' => $codes,
                'events' => $events->filter(fn (array $event): bool => $event['start'] <= $date->toDateString() && $event['end'] > $date->toDateString())->values(),
            ];
        }
        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return compact('employee', 'generatedBy', 'statuses', 'summary') + [
            'monthTitle' => $from->format('F Y'), 'weeks' => array_chunk($cells, 7),
            'generatedAt' => CarbonImmutable::now(config('module4.timezone')),
            'missingDays' => $from->daysInMonth - $rows->count(),
            'totals' => $calendar['summary'],
        ];
    }
}
