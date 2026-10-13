<?php

namespace App\Services;

use App\Contracts\CalendarContextSource;
use App\Contracts\CalendarDataSource;
use App\Models\CompanyEvent;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CalendarMonthService
{
    public function __construct(private CalendarDataSource $source, private CalendarContextSource $context, private CalendarDayPresenter $presenter) {}

    public function build(Employee $employee, string $month): array
    {
        $from = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'))->startOfMonth();
        $to = $from->endOfMonth();
        $context = $this->context->forEmployees([$employee->id], $from, $to)->get($employee->id, []);
        $events = CompanyEvent::query()->overlapping($from->toDateString(), $to->toDateString())
            ->orderBy('starts_on')->orderBy('id')->get()->map(fn (CompanyEvent $event): array => $event->toCalendarEvent());
        $employee->loadMissing('department:id,name');
        $profile = $context['profile'] ?? [];
        $today = CarbonImmutable::today(config('app.timezone'))->toDateString();
        $reference = str_starts_with($today, $month) ? $today : $to->toDateString();
        $cutoff = collect($profile['pay_periods'] ?? [])->first(fn (array $period): bool => $period['start'] <= $reference && $period['end'] >= $reference);
        $readFrom = $cutoff ? $from->min(CarbonImmutable::parse($cutoff['start'], config('app.timezone'))) : $from;
        $readTo = $cutoff ? $to->max(CarbonImmutable::parse($cutoff['end'], config('app.timezone'))) : $to;
        $allDays = $this->source->forEmployee($employee->id, $readFrom, $readTo)
            ->map(fn ($row): array => $this->presenter->present($row, $context['reports'][$row->date->toDateString()] ?? null))->values();
        $days = $allDays->filter(fn (array $day): bool => str_starts_with($day['date'], $month))->values();
        $summary = $this->summary($days, $from->daysInMonth);
        $summary['cutoff_ot_hours'] = $cutoff ? $allDays->filter(fn (array $day): bool => $day['date'] >= $cutoff['start'] && $day['date'] <= $cutoff['end'])->sum('ot_hours') : null;

        return [
            'id' => $employee->id, 'employee_no' => $employee->employee_no,
            'name' => trim($employee->first_name.' '.$employee->last_name), 'position' => $employee->position,
            'department' => $employee->department ? ['id' => $employee->department->id, 'name' => $employee->department->name] : null,
            'days' => $days, 'company_events' => $events,
            'summary' => $summary,
            'range' => ['start' => $from->toDateString(), 'end' => $to->addDay()->toDateString()],
            'leave_balances' => collect($profile['leave_balances'] ?? [])->map(fn (array $balance): array => [
                'type' => $balance['type'], 'remaining_days' => $balance['remaining_days'],
            ])->values(),
            'next_payday' => collect($profile['pay_periods'] ?? [])->pluck('payday')->filter(fn (string $date): bool => $date >= $today)->sort()->first(),
            'cutoff' => $cutoff ? ['start' => $cutoff['start'], 'end' => $cutoff['end']] : null,
        ];
    }

    private function summary(Collection $days, int $daysInMonth): array
    {
        $counts = array_fill_keys([...array_keys(config('calendar.statuses')), 'invalid'], 0);
        $leaveDays = 0;
        foreach ($days as $day) {
            $statuses = array_unique([$day['status'], ...$day['additional_statuses']]);
            foreach ($statuses as $status) {
                $counts[$status] = ($counts[$status] ?? 0) + 1;
            }
            if (in_array('leave_approved', $statuses, true)) {
                $leaveDays += (float) ($day['leave']['days'] ?? 1);
            }
        }

        return [
            'status_counts' => $counts, 'recorded_days' => $days->count(), 'missing_days' => $daysInMonth - $days->count(),
            'late_minutes' => $days->sum('late_minutes'), 'approved_ot_hours' => $days->sum('ot_hours'),
            'leave_days_used' => $leaveDays,
        ];
    }
}
