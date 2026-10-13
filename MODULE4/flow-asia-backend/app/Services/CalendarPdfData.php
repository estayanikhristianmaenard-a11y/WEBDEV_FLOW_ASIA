<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;

class CalendarPdfData
{
    public function __construct(private CalendarMonthService $calendar) {}

    public function build(Employee $employee, User $generatedBy, string $month): array
    {
        $from = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'))->startOfMonth();
        $calendar = $this->calendar->build($employee, $month);
        $rows = $calendar['days']->keyBy('date');
        $events = $calendar['company_events'];
        $statuses = config('calendar.statuses');
        $statuses['invalid'] ??= config('calendar.invalid_status');
        $statuses['unrecorded'] ??= config('calendar.unrecorded_status');
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
            'generatedAt' => CarbonImmutable::now(config('app.timezone')),
            'missingDays' => $from->daysInMonth - $rows->count(),
            'totals' => $calendar['summary'],
        ];
    }
}
