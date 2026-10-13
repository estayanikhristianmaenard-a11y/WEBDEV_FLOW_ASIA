<?php

namespace App\Services;

use App\Contracts\CalendarDataSource;
use App\Models\CompanyEvent;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;

class CalendarPdfData
{
    public function __construct(private CalendarDataSource $source) {}

    public function build(Employee $employee, User $generatedBy, string $month): array
    {
        $from = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'))->startOfMonth();
        $employee->load('department:id,name');
        $rows = $this->source->forEmployee($employee->id, $from, $from->endOfMonth())->keyBy(fn ($row) => $row->date->toDateString());
        $events = CompanyEvent::query()->overlapping($from->toDateString(), $from->endOfMonth()->toDateString())
            ->orderBy('starts_on')->orderBy('id')->get();
        $statuses = config('calendar.statuses');
        $statuses['invalid'] ??= config('calendar.invalid_status');
        $summary = array_fill_keys(array_keys($statuses), 0);
        $cells = array_fill(0, $from->dayOfWeek, null);
        for ($day = 1; $day <= $from->daysInMonth; $day++) {
            $date = $from->addDays($day - 1);
            $row = $rows->get($date->toDateString());
            if ($row) {
                foreach (array_unique([$row->status, ...($row->additional_statuses ?? [])]) as $status) {
                    $summary[$status] = ($summary[$status] ?? 0) + 1;
                }
            }
            $codes = $row ? array_map(fn ($status) => $statuses[$status]['code'] ?? $status,
                array_unique([$row->status, ...($row->additional_statuses ?? [])])) : [];
            $cells[] = [
                'day' => $day, 'codes' => $codes,
                'events' => $events->filter(fn ($event) => $event->starts_on->lte($date) && $event->ends_on->gte($date))->values(),
            ];
        }
        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return compact('employee', 'generatedBy', 'statuses', 'summary') + [
            'monthTitle' => $from->format('F Y'), 'weeks' => array_chunk($cells, 7),
            'generatedAt' => CarbonImmutable::now(config('app.timezone')),
            'missingDays' => $from->daysInMonth - $rows->count(),
        ];
    }
}
